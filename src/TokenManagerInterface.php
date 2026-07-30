<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo;

/**
 * Provides the OpenID Connect tokens of the current session.
 */
interface TokenManagerInterface {

  /**
   * Checks whether the current session was authenticated with Tunnistamo.
   *
   * This only looks at the session and never talks to the identity provider.
   * This newer makes network requests.
   *
   * @return bool
   *   TRUE if the session holds an access token.
   */
  public function hasSession() : bool;

  /**
   * Gets an access token that can be presented to an API.
   *
   * @return string|null
   *   The access token, or NULL if the session has none and no new token could
   *   be fetched.
   */
  public function getAccessToken() : ?string;

}
