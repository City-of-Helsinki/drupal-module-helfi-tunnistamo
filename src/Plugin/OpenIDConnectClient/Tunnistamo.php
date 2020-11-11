<?php

declare(strict_types = 1);

namespace Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient;

use Drupal\openid_connect\Plugin\OpenIDConnectClientBase;

/**
 * Tunnistamo OpenID Connect client.
 *
 * Implements OpenID Connect Client plugin for Tunnistamo.
 *
 * @OpenIDConnectClient(
 *   id = "tunnistamo",
 *   label = @Translation("Tunnistamo")
 * )
 */
final class Tunnistamo extends OpenIDConnectClientBase {

  /**
   * {@inheritdoc}
   */
  public function getEndpoints() {
    return [
      'authorization' => 'https://api.hel.fi/sso/openid/authorize/',
      'token' => 'https://api.hel.fi/sso/openid/token/',
      'userinfo' => 'https://api.hel.fi/sso/openid/userinfo/',
    ];
  }

}