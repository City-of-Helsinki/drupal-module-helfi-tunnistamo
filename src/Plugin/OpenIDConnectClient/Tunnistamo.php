<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\openid_connect\Plugin\OpenIDConnectClientBase;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Implements OpenID Connect Client plugin for Tunnistamo.
 *
 * @OpenIDConnectClient(
 *   id = "tunnistamo",
 *   label = @Translation("Tunnistamo")
 * )
 */
final class Tunnistamo extends OpenIDConnectClientBase {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) : self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->eventDispatcher = $container->get('event_dispatcher');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'client_scopes' => 'openid,email',
      'environment_url' => '',
      'auto_login' => FALSE,
      'client_roles' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) : void {
    $this->configuration = array_merge($this->defaultConfiguration(), $configuration);
  }

  /**
   * Whether 'auto_login' setting is enabled or not.
   *
   * @return bool
   *   TRUE if we should auto login.
   */
  public function autoLogin(): bool {
    return (bool) $this->configuration['auto_login'];
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoints(): array {
    static $endpoints = [];

    if (!$endpoints) {
      if (empty($this->configuration['environment_url'])) {
        throw new \InvalidArgumentException('Missing required "environment_url" configuration.');
      }
      $configuration = $this
        ->autoDiscover
        ->fetch(rtrim($this->configuration['environment_url'], '/') . '/');

      $endpoints = [
        'authorization' => '',
        'token' => '',
        'userinfo' => '',
        'end_session' => '',
      ];

      foreach ($endpoints as $type => $value) {
        $key = sprintf('%s_endpoint', $type);

        if (!isset($configuration[$key])) {
          throw new \InvalidArgumentException(sprintf('Missing required "%s" endpoint configuration.', $type));
        }
        $endpoints[$type] = $configuration[$type . '_endpoint'];
      }

    }
    return $endpoints;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['auto_login'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto login on 403 pages'),
      '#default_value' => $this->configuration['auto_login'],
    ];

    $form['client_scopes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client scopes'),
      '#description' => $this->t('A comma separated list of client scopes.'),
      '#default_value' => $this->configuration['client_scopes'],
      '#size' => 255,
      '#maxlength' => 255,
    ];

    $form['environment_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenID Connect Authorization server / Issuer'),
      '#description' => [
        [
          '#markup' => $this->t('Url to auth server. See README.md for more information.'),
        ],
      ],
      '#default_value' => $this->configuration['environment_url'],
      '#size' => 255,
      '#maxlength' => 255,
    ];

    $roleOptions = [];
    foreach (Role::loadMultiple() ?? [] as $role) {
      // Skip anonymous role, but leave authenticated user role, so we can
      // use it to remove all other roles in case someone wants to use this
      // feature to remove manually given roles on login.
      if ($role->id() === AccountInterface::ANONYMOUS_ROLE) {
        continue;
      }
      $roleOptions[$role->id()] = $role->label();
    }

    $form['ad_roles_disabled_amr'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Disable AD role mapping for AMR. This must be done code. See README.md for more information'),
    ];

    $form['loa_roles'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Map Level of Assurance to Drupal role. This must be done code. See README.md for more information'),
    ];

    $form['ad_roles'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Map AD role to Drupal role. This must be done code. See README.md for more information'),
    ];

    $form['client_roles'] = [
      '#type' => 'checkboxes',
      '#multiple' => TRUE,
      '#options' => $roleOptions,
      '#title' => $this->t('Client roles'),
      '#description' => $this->t('Select roles to be assigned users logging in with this client.'),
      '#default_value' => $this->getClientRoles(),
    ];

    return $form;
  }

  /**
   * Gets AD roles mapping.
   *
   * @return array
   *   The AD to Drupal role map.
   */
  public function getAdRoles() : array {
    return array_filter($this->configuration['ad_roles'] ?? []);
  }

  /**
   * Gets loa roles mapping.
   *
   * @return array
   *   The loa to Drupal role map.
   */
  public function getLoaRoles() : array {
    return array_filter($this->configuration['loa_roles'] ?? []);
  }

  /**
   * Gets AMRs where ad role mapping is disabled.
   *
   * @return array
   *   The AMR list.
   */
  public function getDisabledAmr() : array {
    return array_filter($this->configuration['ad_roles_disabled_amr'] ?? []);
  }

  /**
   * Grant given roles to user.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account.
   * @param array $context
   *   The context.
   */
  public function mapRoles(UserInterface $account, array $context) : void {
    // Skip role mapping for configured authentication methods.
    if (array_intersect($context['userinfo']['amr'] ?? [], $this->getDisabledAmr())) {
      return;
    }

    $roles = $this->getClientRoles();
    $adRoles = $this->getAdRoles();
    $loaRoles = $this->getLoaRoles();

    if (!$roles && !$adRoles && !$loaRoles) {
      return;
    }

    // Remove all existing roles.
    array_map(
      fn (string $rid) => $account->removeRole($rid),
      $account->getRoles(FALSE)
    );

    foreach ($this->mapAdRoles($context) as $role) {
      $roles[] = $role;
    }

    foreach ($this->mapLoaRoles($context) as $role) {
      $roles[] = $role;
    }

    foreach ($roles as $rid) {
      // Trying to add the authenticated/anonymous role will throw an
      // exception.
      if (in_array($rid, [
        AccountInterface::AUTHENTICATED_ROLE,
        AccountInterface::ANONYMOUS_ROLE,
      ])) {
        continue;
      }
      $account->addRole($rid);
    }

    $account->save();
  }

  /**
   * Maps ad groups to drupal roles.
   *
   * @param array $context
   *   The context.
   *
   * @return string[]
   *   Drupal role ids.
   */
  private function mapAdRoles(array $context) : array {
    $adRoles = $this->getAdRoles();
    $roles = [];

    if ($adRoles && !empty($context['userinfo']['ad_groups'])) {
      foreach ($adRoles as $map) {
        ['ad_role' => $adRole, 'roles' => $drupalRoles] = $map;

        if (!in_array($adRole, $context['userinfo']['ad_groups'])) {
          continue;
        }
        foreach ($drupalRoles as $value) {
          $roles[] = $value;
        }
      }
    }

    return $roles;
  }

  /**
   * Maps context userinfo to drupal roles.
   *
   * @param array $context
   *   The context.
   *
   * @return string[]
   *   Drupal role ids.
   */
  private function mapLoaRoles(array $context) : array {
    $loaRoles = $this->getLoaRoles();
    $roles = [];

    if (!$loaRoles || empty($context['userinfo']['loa'])) {
      return [];
    }

    foreach ($loaRoles as $map) {
      ['loa' => $loa, 'roles' => $drupalRoles] = $map;

      if ($loa !== $context['userinfo']['loa']) {
        continue;
      }
      foreach ($drupalRoles as $value) {
        $roles[] = $value;
      }
    }

    return $roles;
  }

  /**
   * Gets the configured client roles.
   *
   * @return null|array
   *   An array of enabled client roles.
   */
  public function getClientRoles() : ?array {
    return array_filter($this->configuration['client_roles'] ?? []);
  }

  /**
   * {@inheritdoc}
   */
  public function getClientScopes(): array {
    $scopes = $this->configuration['client_scopes'] ?? [];

    if (!$scopes) {
      return parent::getClientScopes();
    }
    return explode(',', $this->configuration['client_scopes']);
  }

  /**
   * Set user preferred admin langcode if not set.
   *
   * @param \Drupal\user\UserInterface $account
   *   Account.
   */
  public function setUserPreferredAdminLanguage(UserInterface $account) : void {
    try {
      if (!$account->getPreferredAdminLangcode(FALSE)) {
        $account->set('preferred_admin_langcode', 'fi');
        $account->save();
      }
    }
    catch (\Exception) {
    }
  }

}
