<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\helfi_tunnistamo\Event\RedirectUrlEvent;
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
   * Testing environment address.
   *
   * @var string
   */
  public const TESTING_ENVIRONMENT = 'https://api.hel.fi/sso-test';

  /**
   * Production environment address.
   *
   * @var string
   */
  public const PRODUCTION_ENVIRONMENT = 'https://api.hel.fi/sso';

  /**
   * Whether to send silent authentication or not.
   *
   * @var bool
   */
  private bool $silentAuthentication = FALSE;

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
    $plugin_definition
  ) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->eventDispatcher = $container->get('event_dispatcher');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'is_production' => FALSE,
      'client_scopes' => 'openid,email',
      'environment_url' => 'https://tunnistamo.test.hel.ninja',
      'auto_login' => FALSE,
      'client_roles' => [],
    ] + parent::defaultConfiguration();
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
  public function getRedirectUrl(
    array $route_parameters = [],
    array $options = []
  ): Url {
    $url = parent::getRedirectUrl($route_parameters, $options);
    /** @var \Drupal\helfi_tunnistamo\Event\RedirectUrlEvent $urlEvent */
    $urlEvent = $this->eventDispatcher->dispatch(new RedirectUrlEvent(
      $url,
      $this->requestStack->getCurrentRequest()
    ));
    return $urlEvent->getRedirectUrl();
  }

  /**
   * Attempt to authenticate silently without prompt.
   *
   * @return $this
   *   The self.
   */
  public function setSilentAuthentication(): self {
    $this->silentAuthentication = TRUE;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function getUrlOptions(
    string $scope,
    GeneratedUrl $redirect_uri
  ): array {
    $options = parent::getUrlOptions($scope, $redirect_uri);

    if ($this->silentAuthentication) {
      $options['query'] += [
        'prompt' => 'none',
      ];
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoints(): array {

    $base = $this->isProduction() ?
      self::PRODUCTION_ENVIRONMENT :
      self::TESTING_ENVIRONMENT;

    if (!empty($this->configuration['environment_url'])) {
      $base = $this->configuration['environment_url'];
    }

    return [
      'authorization' => sprintf('%s/openid/authorize/', $base),
      'token' => sprintf('%s/openid/token/', $base),
      'userinfo' => sprintf('%s/openid/userinfo/', $base),
    ];
  }

  /**
   * Checks whether we're operating on production environment.
   *
   * @return bool
   *   TRUE if we're operating on production environment.
   */
  public function isProduction(): bool {
    return (bool) $this->configuration['is_production'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state
  ): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['is_production'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use production environment'),
      '#default_value' => $this->isProduction(),
    ];

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
    ];

    $form['environment_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenID Connect Authorization server / Issuer.'),
      '#description' => $this->t('Url to auth server.<br /> DEV: https://tunnistamo.test.hel.ninja<br /> PROD: https://api.hel.fi/sso <br />STAGE: https://api.hel.fi/sso-test'),
      '#default_value' => $this->configuration['environment_url'],
    ];

    $roleOptions = [];
    foreach (Role::loadMultiple() ?? [] as $role) {
      // Skip anonymous role, but leave authenticated user role, so we can
      // use it to remove all other roles in case someone wants to use this
      // feature to remove manually given roles on login.
      if (in_array($role->id(), [
        AccountInterface::ANONYMOUS_ROLE,
      ])) {
        continue;
      }
      $roleOptions[$role->id()] = $role->label();
    }

    $form['client_roles'] = [
      '#type' => 'checkboxes',
      '#multiple' => TRUE,
      '#options' => $roleOptions,
      '#title' => $this->t('Client roles.'),
      '#description' => $this->t('Select roles to be assigned users logging in with this client.'),
      '#default_value' => $this->getClientRoles(),
    ];

    return $form;
  }

  /**
   * Remove existing and map new roles based on plugin configuration.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account to map roles to.
   */
  public function mapRoles(UserInterface $account) : void {
    // Skip role mapping if no roles are set, so we don't remove
    // any manually set roles when this feature is not enabled.
    if (!$roles = $this->getClientRoles()) {
      return;
    }

    // Remove all existing roles.
    array_map(
      fn (string $rid) => $account->removeRole($rid),
      $account->getRoles(FALSE)
    );

    // Add new roles from plugin config.
    array_map(function (string $rid) use ($account) {
      // Trying to add authenticated or anonymous role will throw an
      // exception.
      if (in_array($rid, [
        AccountInterface::AUTHENTICATED_ROLE,
        AccountInterface::ANONYMOUS_ROLE,
      ])) {
        return;
      }
      $account->addRole($rid);
    }, $roles);
    $account->save();
  }

  /**
   * Gets the configured client roles.
   *
   * @return array
   *   An array of enabled client roles.
   */
  public function getClientRoles() : ? array {
    if (is_string($this->configuration['client_roles'])) {
      return explode(',',$this->configuration['client_roles']);
    }
    return $this->configuration['client_roles'];
  }

  /**
   * {@inheritdoc}
   */
  public function getClientScopes(): array {
    $scopes = $this->configuration['client_scopes'];

    if (!$scopes) {
      return ['openid', 'email', 'ad_groups'];
    }
    return explode(',', $this->configuration['client_scopes']);
  }

}
