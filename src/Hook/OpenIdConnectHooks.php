<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\externalauth\AuthmapInterface;
use Drupal\helfi_tunnistamo\TokenManager;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * OpenID Connect hooks.
 */
final readonly class OpenIdConnectHooks {

  public function __construct(
    private TokenManager $tokenManager,
    private AuthmapInterface $authmap,
    private AccountProxyInterface $currentUser,
    #[Autowire(service: 'logger.channel.helfi_tunnistamo')]
    private LoggerInterface $logger,
  ) {
  }

  /**
   * Implements hook_openid_connect_pre_authorize().
   *
   * The connect operation links the current user to whichever identity the
   * user logs in as, replacing the existing link. A logged-in user must log
   * in again as the identity they are linked to, otherwise someone else's
   * identity would take over the account.
   *
   * This runs before openid_connect links the account, so denying here
   * leaves the link and the session tokens untouched.
   *
   * @param \Drupal\user\UserInterface|false $account
   *   The account linked to the identity, if any.
   * @param array<string, mixed> $context
   *   The authorization context.
   *
   * @return bool|null
   *   FALSE to deny the authorization, NULL to not affect it.
   *
   * @see \Drupal\openid_connect\OpenIDConnect::connectCurrentUser()
   * @see \Drupal\helfi_tunnistamo\ReauthenticatorInterface
   */
  #[Hook('openid_connect_pre_authorize')]
  public function preAuthorize(UserInterface|false $account, array $context): ?bool {
    // Anonymous users are logging in, not connecting.
    if (($context['plugin_id'] ?? NULL) !== TokenManager::CLIENT_ID || !$this->currentUser->isAuthenticated()) {
      return NULL;
    }

    $uid = (int) $this->currentUser->id();
    $linked = $this->authmap->get($uid, 'openid_connect.' . TokenManager::CLIENT_ID);

    if (!$linked || $linked === (string) $context['sub']) {
      return NULL;
    }

    $this->logger->warning('User @uid logged in to Tunnistamo as a different identity than the one linked to the account. The connection was denied.', [
      '@uid' => $uid,
    ]);

    return FALSE;
  }

  /**
   * Implements hook_openid_connect_post_authorize().
   *
   * The openid_connect module saves the tokens to the session only on login.
   * Save them on the connect operation as well, so users can get new tokens
   * without logging out.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   * @param array<string, mixed> $context
   *   The authorization context.
   *
   * @see \Drupal\helfi_tunnistamo\ReauthenticatorInterface
   */
  #[Hook('openid_connect_post_authorize')]
  public function postAuthorize(UserInterface $account, array $context): void {
    if (($context['plugin_id'] ?? NULL) !== TokenManager::CLIENT_ID || !is_array($context['tokens'] ?? NULL)) {
      return;
    }

    $this->tokenManager->saveTokens($context['tokens']);
  }

}
