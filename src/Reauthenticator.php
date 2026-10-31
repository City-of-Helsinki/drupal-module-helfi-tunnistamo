<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\openid_connect\OpenIDConnectClaims;
use Drupal\openid_connect\OpenIDConnectClientEntityInterface;
use Drupal\openid_connect\OpenIDConnectSessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs the current user in again to get new tokens.
 */
final readonly class Reauthenticator implements ReauthenticatorInterface {

  /**
   * Session key of the action to resume after the login.
   */
  public const string RESUME_SESSION_KEY = 'helfi_tunnistamo.resume';

  /**
   * Session key of the time the user was last logged in again.
   */
  public const string REAUTHENTICATED_SESSION_KEY = 'helfi_tunnistamo.reauthenticated';

  /**
   * Seconds after a login during which the user is not logged in again.
   */
  public const int LOOP_GUARD_WINDOW = 60;

  public function __construct(
    private TokenManagerInterface $tokenManager,
    private OpenIDConnectSessionInterface $oidcSession,
    private OpenIDConnectClaims $claims,
    private EntityTypeManagerInterface $entityTypeManager,
    private AccountProxyInterface $currentUser,
    private RedirectDestinationInterface $redirectDestination,
    private RequestStack $requestStack,
    private TimeInterface $time,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function reauthenticate(?Url $destination = NULL) : Response {
    return $this->start(NULL, [], $destination);
  }

  /**
   * {@inheritdoc}
   */
  public function reauthenticateAndResubmit(FormStateInterface $form_state) : Response {
    $values = $form_state->getValues();
    // The resubmitted form gets new ones.
    unset($values['form_build_id'], $values['form_token'], $values['form_id']);

    return $this->start(FormResubmitter::class . '::resubmit', [
      $form_state->getFormObject()::class,
      $values,
      $form_state->getBuildInfo()['args'] ?? [],
    ], NULL);
  }

  /**
   * Starts the connect operation of openid_connect.
   *
   * @param string|null $callback
   *   The callback to resume, in a format supported by
   *   \Drupal\Core\Utility\CallableResolver. It may return a response for
   *   the user.
   * @param array<mixed> $arguments
   *   Arguments for the callback. Stored in the session, so they must be
   *   serializable.
   * @param \Drupal\Core\Url|null $fallback
   *   Where the user is redirected if the callback returns nothing.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Redirect to the identity provider.
   */
  private function start(?string $callback, array $arguments, ?Url $fallback) : Response {
    if (!$this->currentUser->isAuthenticated()) {
      throw new \LogicException('Only logged in users can reauthenticate.');
    }

    // Logging in again would link the identity provider account to a user
    // who logged in with a password.
    if (!$this->tokenManager->hasSession()) {
      throw new \LogicException('The current user did not log in with Tunnistamo.');
    }

    $session = $this->requestStack->getSession();
    $reauthenticated = $session->get(self::REAUTHENTICATED_SESSION_KEY);
    if (is_int($reauthenticated) && $this->time->getCurrentTime() - $reauthenticated < self::LOOP_GUARD_WINDOW) {
      throw new ReauthenticationFailedException('The user was just logged in again, and the access token is still not usable.');
    }

    $client = $this->entityTypeManager
      ->getStorage('openid_connect_client')
      ->load(TokenManager::CLIENT_ID);

    if (!$client instanceof OpenIDConnectClientEntityInterface || !$client->status()) {
      throw new \LogicException('The Tunnistamo client is not enabled.');
    }

    $session->set(self::RESUME_SESSION_KEY, [
      'callback' => $callback,
      'arguments' => $arguments,
      'fallback' => $fallback?->toString() ?? $this->redirectDestination->get(),
    ]);

    // The connect operation authenticates the current user without logging
    // them out. The new tokens are saved by the post authorize hook.
    // saveDestination() reads the destination from the redirect destination
    // service.
    // @see \Drupal\helfi_tunnistamo\Hook\OpenIdConnectHooks::postAuthorize()
    $this->redirectDestination->set(Url::fromRoute('helfi_tunnistamo.resume')->toString());
    $this->oidcSession->saveDestination();
    $this->oidcSession->saveOp('connect', (int) $this->currentUser->id());

    $plugin = $client->getPlugin();

    return $plugin->authorize($this->claims->getScopes($plugin));
  }

}
