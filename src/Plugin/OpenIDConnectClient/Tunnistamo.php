<?php

declare(strict_types = 1);

namespace Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient;

use Drupal\Core\Form\FormStateInterface;
use Drupal\openid_connect\Plugin\OpenIDConnectClientBase;

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
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'is_production' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoints() {
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
  ) {
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
