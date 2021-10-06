<?php

declare(strict_types = 1);

namespace Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\helfi_tunnistamo\Event\RedirectUrlEvent;
use Drupal\openid_connect\Plugin\OpenIDConnectClientBase;
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
  public function defaultConfiguration() : array {
    return [
      'is_production' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getRedirectUrl(
    array $route_parameters = [],
    array $options = []
  ): Url {
    $url = parent::getRedirectUrl($route_parameters, $options);
    /** @var \Drupal\helfi_tunnistamo\Event\RedirectUrlEvent $dispachedUrl */
    $dispachedUrl = $this->eventDispatcher->dispatch(new RedirectUrlEvent(
      $url,
      $this->requestStack->getCurrentRequest()
    ));
    return $dispachedUrl->getRedirectUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoints() : array {
    $base = $this->isProduction() ?
      self::PRODUCTION_ENVIRONMENT :
      self::TESTING_ENVIRONMENT;

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
  public function isProduction() : bool {
    return (bool) $this->configuration['is_production'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state
  )  : array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['is_production'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use production environment'),
      '#default_value' => $this->isProduction(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getClientScopes() : array {
    return [
      'openid',
      'email',
      'ad_groups',
    ];
  }

}
