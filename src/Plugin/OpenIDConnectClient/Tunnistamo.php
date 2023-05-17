<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_tunnistamo\Event\RedirectUrlEvent;
use Drupal\openid_connect\Plugin\OpenIDConnectClientBase;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Implements OpenID Connect Client plugin for Tunnistamo.
 *
 * @OpenIDConnectClient(
 *   id = "tunnistamo",
 *   label = @Translation("Tunnistamo")
 * )
 */
final class Tunnistamo extends OpenIDConnectClientBase {

  public const TESTING_ENVIRONMENT = 'https://tunnistamo.test.hel.ninja';
  public const STAGING_ENVIRONMENT = 'https://api.hel.fi/sso-test';
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
   * The environment resolver.
   *
   * @var \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface
   */
  private EnvironmentResolverInterface $environmentResolver;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) : self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->environmentResolver = $container->get('helfi_api_base.environment_resolver');
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
  protected function getRedirectUrl(
    array $route_parameters = [],
    array $options = []
  ): Url {
    $url = parent::getRedirectUrl($route_parameters, $options);
    /** @var \Drupal\helfi_tunnistamo\Event\RedirectUrlEvent $urlEvent */
    $urlEvent = $this->eventDispatcher->dispatch(new RedirectUrlEvent(
      $url,
      $this->requestStack->getCurrentRequest(),
      $this
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
  public function authorize(string $scope = 'openid email', array $additional_params = []): Response {
    // @todo Remove this override once https://www.drupal.org/project/openid_connect/issues/3317308
    // is merged.
    $redirect_uri = $this->getRedirectUrl()->toString(TRUE);
    $url_options = $this->getUrlOptions($scope, $redirect_uri);

    if (!empty($additional_params)) {
      $url_options['query'] = array_merge($url_options['query'], $additional_params);
    }

    $endpoints = $this->getEndpoints();
    // Clear _GET['destination'] because we need to override it.
    $this->requestStack->getCurrentRequest()->query->remove('destination');
    $authorization_endpoint = Url::fromUri($endpoints['authorization'], $url_options)->toString(TRUE);

    $this->loggerFactory->get('openid_connect_' . $this->pluginId)->debug('Send authorize request to @url', ['@url' => $authorization_endpoint->getGeneratedUrl()]);
    $response = new TrustedRedirectResponse($authorization_endpoint->getGeneratedUrl());
    // We can't cache the response, since this will prevent the state to be
    // added to the session. The kill switch will prevent the page getting
    // cached for anonymous users when page cache is active.
    $this->pageCacheKillSwitch->trigger();

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoints(): array {
    $endpointMap = [
      'dev' => self::TESTING_ENVIRONMENT,
      'test' => self::TESTING_ENVIRONMENT,
      'stage' => self::STAGING_ENVIRONMENT,
      'prod' => self::PRODUCTION_ENVIRONMENT,
    ];
    $base = self::STAGING_ENVIRONMENT;

    try {
      // Attempt to automatically detect endpoint.
      $env = $this->environmentResolver->getActiveEnvironmentName();

      if (isset($endpointMap[$env])) {
        $base = $endpointMap[$env];
      }
    }
    catch (\InvalidArgumentException) {
    }
    // Allow environment_url config to always override automatically detected
    // endpoint.
    if (!empty($this->configuration['environment_url'])) {
      $base = $this->configuration['environment_url'];
    }

    return [
      'authorization' => sprintf('%s/openid/authorize/', $base),
      'token' => sprintf('%s/openid/token/', $base),
      'userinfo' => sprintf('%s/openid/userinfo/', $base),
      'end_session' => sprintf('%s/openid/end-session', $base),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state
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
          '#markup' => $this->t('Url to auth server. Leave this empty to detect environment automatically. See README.md for more information.'),
        ],
        [
          '#theme' => 'item_list',
          '#items' => [
            sprintf('DEV: %s', self::TESTING_ENVIRONMENT),
            sprintf('TEST: %s', self::TESTING_ENVIRONMENT),
            sprintf('STAGE: %s', self::STAGING_ENVIRONMENT),
            sprintf('PROD: %s', self::PRODUCTION_ENVIRONMENT),
          ],
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
   * Remove existing and map new roles based on plugin configuration.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account to map roles to.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
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
      return ['openid', 'email', 'ad_groups'];
    }
    return explode(',', $this->configuration['client_scopes']);
  }

  /**
   * Set user preferred admin langcode if not set.
   *
   * @param UserInterface $account
   *    Account.
   *
   * @return void
   */
  public function setUserPreferredAdminLanguage(UserInterface $account) : void {
    try {
      if ($this->environmentResolver->getActiveProject() &&
        !$account->getPreferredAdminLangcode(FALSE)
      ) {
        $account->set('preferred_admin_langcode', 'fi');
        $account->save();
      }
    }
    catch(\Exception $e){
      return;
    }
  }

}
