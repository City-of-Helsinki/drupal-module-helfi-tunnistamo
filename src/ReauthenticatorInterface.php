<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo;

use Symfony\Component\HttpFoundation\Response;

/**
 * Logs the current user in again to get new tokens.
 *
 * Use this when the access token has expired and can't be renewed. Unlike
 * logging out and back in, this keeps the Drupal session. If the user still
 * has a session with the identity provider, the login completes without user
 * interaction.
 */
interface ReauthenticatorInterface {

  /**
   * Logs the user in again, then redirects them back to the current page.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Redirect to the identity provider.
   *
   * @throws \LogicException
   *   If the current user can't be logged in again.
   */
  public function reauthenticate() : Response;

}
