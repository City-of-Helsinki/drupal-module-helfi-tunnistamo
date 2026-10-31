<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs the current user in again to get new tokens.
 *
 * Use this when the access token has expired and can't be renewed. Unlike
 * logging out and back in, this keeps the Drupal session. If the user still
 * has a session with the identity provider, the login completes without user
 * interaction.
 *
 * After the login, the user is sent through a resume route that redirects
 * them back, or re-runs the action that needed the token. If the action
 * still can't get a token ReauthenticationFailedException is thrown instead
 * of redirecting the user in a loop.
 *
 * @see \Drupal\helfi_tunnistamo\Controller\ResumeController
 */
interface ReauthenticatorInterface {

  /**
   * Logs the user in again, then redirects them to the given URL.
   *
   * @param \Drupal\Core\Url|null $destination
   *   Where the user is redirected after the login. Defaults to the current
   *   page.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Redirect to the identity provider.
   *
   * @throws \LogicException
   */
  public function reauthenticate(?Url $destination = NULL) : Response;

  /**
   * Logs the user in again, then submits the form again.
   *
   * Call this from a submit handler. The form is submitted programmatically
   * with the same values, so the form must be buildable from its class name
   * and its values and build arguments must be serializable. Entity forms and
   * file uploads are not supported.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   State of the form being submitted.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Redirect to the identity provider.
   *
   * @throws \LogicException
   */
  public function reauthenticateAndResubmit(FormStateInterface $form_state) : Response;

}
