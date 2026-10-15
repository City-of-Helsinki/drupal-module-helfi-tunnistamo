<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo;
use Drupal\openid_connect\OpenIDConnectClientEntityInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * OpenID Connect hooks.
 */
final readonly class OpenIdConnectHooks {

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'logger.channel.helfi_tunnistamo')]
    private LoggerInterface $logger,
  ) {
  }

  /**
   * Implements hook_openid_connect_pre_authorize().
   *
   * The OpenID provider user is connected to an existing account by email
   * address, so only allow it when the provider has verified the address.
   *
   * @param \Drupal\user\UserInterface|false $account
   *   The account connected to the OpenID provider user, if any.
   * @param array<string, mixed> $context
   *   The context.
   */
  #[Hook('openid_connect_pre_authorize')]
  public function preAuthorize(UserInterface|false $account, array $context): bool {
    if ($account) {
      return TRUE;
    }
    $client = $this->entityTypeManager
      ->getStorage('openid_connect_client')
      ->load($context['plugin_id']);

    if (!$client instanceof OpenIDConnectClientEntityInterface || !$client->getPlugin() instanceof Tunnistamo) {
      return TRUE;
    }
    $existing = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(['mail' => $context['userinfo']['email']]);

    if (!$existing || ($context['userinfo']['email_verified'] ?? FALSE) === TRUE) {
      return TRUE;
    }
    $this->logger->warning('Denied connecting an unverified email address to an existing account: %name.', [
      '%name' => reset($existing)->getAccountName(),
    ]);
    return FALSE;
  }

}
