<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_tunnistamo\TokenManager;
use Drupal\user\UserInterface;

/**
 * OpenID Connect hooks.
 */
final readonly class OpenIdConnectHooks {

  public function __construct(
    private TokenManager $tokenManager,
  ) {
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
