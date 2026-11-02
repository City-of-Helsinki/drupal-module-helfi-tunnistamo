<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo\Hook;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\externalauth\AuthmapInterface;
use Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo;
use Drupal\helfi_tunnistamo\TokenManager;
use Drupal\openid_connect\OpenIDConnectClientEntityInterface;
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
    private EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'logger.channel.helfi_tunnistamo')]
    private LoggerInterface $logger,
  ) {
  }

  /**
   * Implements hook_openid_connect_pre_authorize().
   *
   * @param \Drupal\user\UserInterface|false $account
   *   The account linked to the identity, if any.
   * @param array<string, mixed> $context
   *   The authorization context.
   *
   * @return bool|null
   *   FALSE to deny the authorization, NULL to not affect it.
   *
   * @see \Drupal\helfi_tunnistamo\ReauthenticatorInterface
   */
  #[Hook('openid_connect_pre_authorize')]
  public function preAuthorize(UserInterface|false $account, array $context): ?bool {
    // Anonymous users are logging in, not related to reauthentication.
    if (!$this->getPlugin($context) || !$this->currentUser->isAuthenticated()) {
      return NULL;
    }

    $uid = (int) $this->currentUser->id();
    $linked = $this->authmap->get($uid, 'openid_connect.' . $context['plugin_id']);

    // During reauthentication, logged-in user must log-in
    // as the identity they are linked to.
    if (!$linked || $linked === (string) $context['sub']) {
      return NULL;
    }

    $this->logger->warning('User @uid logged in to Tunnistamo as a different identity than the one linked to the account. The connection was denied.', [
      '@uid' => $uid,
    ]);

    return FALSE;
  }

  /**
   * Implements hook_openid_connect_userinfo_alter().
   *
   * @param array<string, mixed> $userinfo
   *   The user info.
   * @param array<string, mixed> $context
   *   The context.
   */
  #[Hook('openid_connect_userinfo_alter')]
  public function userinfoAlter(array &$userinfo, array $context): void {
    if (!$this->getPlugin($context)) {
      return;
    }

    // If the user email is missing, replace it with a made-up address (issue
    // with @edu.hel.fi users).
    if (empty($userinfo['email'])) {
      $userinfo['email'] = helfi_tunnistamo_create_email($userinfo);
    }

    // Unset preferred_username if it is a UUID so that new users get their
    // name from `name` field instead.
    if (!empty($userinfo['preferred_username']) && Uuid::isValid($userinfo['preferred_username'])) {
      unset($userinfo['preferred_username']);
    }
  }

  /**
   * Implements hook_openid_connect_post_authorize().
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
    if (!$plugin = $this->getPlugin($context)) {
      return;
    }

    $plugin->mapRoles($account, $context);
    $plugin->setUserPreferredAdminLanguage($account);

    // Once user logs in with openid connect, set user password to null.
    // This prevents the user from logging in with the local user in the
    // future.
    if ($account->getPassword()) {
      $account
        ->setPassword(NULL)
        ->save();
    }

    // The openid_connect module saves the tokens to the session only on
    // login. Save them on the connect operation as well.
    if (is_array($context['tokens'] ?? NULL)) {
      $this->tokenManager->saveTokens($context['tokens']);
    }
  }

  /**
   * Gets the Tunnistamo plugin of the client in the context.
   *
   * @param array<string, mixed> $context
   *   The hook context.
   */
  private function getPlugin(array $context): ?Tunnistamo {
    if (!is_string($context['plugin_id'] ?? NULL)) {
      return NULL;
    }

    $client = $this->entityTypeManager
      ->getStorage('openid_connect_client')
      ->load($context['plugin_id']);

    if (!$client instanceof OpenIDConnectClientEntityInterface) {
      return NULL;
    }

    $plugin = $client->getPlugin();
    return $plugin instanceof Tunnistamo ? $plugin : NULL;
  }

}
