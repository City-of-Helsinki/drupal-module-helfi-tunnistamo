<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo;

use Symfony\Component\HttpFoundation\Response;

/**
 * Provides the OpenID Connect tokens of the current session.
 */
interface TokenManagerInterface {

  /**
   * Checks whether the current session was authenticated with Tunnistamo.
   *
   * This only looks at the session and never talks to the identity provider.
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

  /**
   * Starts the OpenID flow to get new tokens for the current user.
   *
   * Use this when the access token has expired and can't be renewed. Unlike
   * logging out and back in, this keeps the Drupal session. If the user still
   * has a session with the identity provider, the flow completes without
   * user interaction. The user is redirected back to the current page, or to
   * the destination query parameter.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Redirect to the identity provider.
   *
   * @throws \LogicException
   *   If the user is not logged in or the Tunnistamo client is not enabled.
   */
  public function reauthenticate() : Response;

}
