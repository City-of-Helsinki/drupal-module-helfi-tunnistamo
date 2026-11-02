<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\externalauth\AuthmapInterface;
use Drupal\openid_connect\OpenIDConnectClaims;
use Drupal\openid_connect\OpenIDConnectClientEntityInterface;
use Drupal\openid_connect\OpenIDConnectSessionInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs the current user in again to get new tokens.
 */
final readonly class Reauthenticator implements ReauthenticatorInterface {

  public function __construct(
    private TokenManagerInterface $tokenManager,
    private OpenIDConnectSessionInterface $oidcSession,
    private OpenIDConnectClaims $claims,
    private EntityTypeManagerInterface $entityTypeManager,
    private AccountProxyInterface $currentUser,
    private AuthmapInterface $authmap,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function reauthenticate() : Response {
    if (!$this->currentUser->isAuthenticated()) {
      throw new \LogicException('Only logged in users can reauthenticate.');
    }

    // Logging in again would link the identity provider account to a user
    // who logged in with a password.
    if (!$this->tokenManager->hasSession()) {
      throw new \LogicException('The current user did not log in with Tunnistamo.');
    }

    // The new login is accepted only for the linked identity.
    // @see \Drupal\helfi_tunnistamo\Hook\OpenIdConnectHooks::preAuthorize()
    if (!$this->authmap->get((int) $this->currentUser->id(), 'openid_connect.' . TokenManager::CLIENT_ID)) {
      throw new \LogicException('The current user is not linked to Tunnistamo.');
    }

    $client = $this->entityTypeManager
      ->getStorage('openid_connect_client')
      ->load(TokenManager::CLIENT_ID);

    if (!$client instanceof OpenIDConnectClientEntityInterface || !$client->status()) {
      throw new \LogicException('The Tunnistamo client is not enabled.');
    }

    // The connect operation authenticates the current user without logging
    // them out. The new tokens are saved by the post authorize hook. The user
    // is redirected back to the current page.
    // @see \Drupal\helfi_tunnistamo\Hook\OpenIdConnectHooks::postAuthorize()
    $this->oidcSession->saveDestination();
    $this->oidcSession->saveOp('connect', (int) $this->currentUser->id());

    $plugin = $client->getPlugin();

    return $plugin->authorize($this->claims->getScopes($plugin));
  }

}
