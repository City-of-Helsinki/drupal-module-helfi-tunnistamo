<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\externalauth\AuthmapInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * User hooks.
 */
final readonly class UserHooks {

  public function __construct(
    private AuthmapInterface $authmap,
    #[Autowire(service: 'logger.channel.helfi_tunnistamo')]
    private LoggerInterface $logger,
  ) {
  }

  /**
   * Implements hook_mail_alter().
   *
   * External users must log in through the OpenID provider, so they can't
   * use the one-time login link to bypass it.
   *
   * @param array<string, mixed> $message
   *   The message.
   */
  #[Hook('mail_alter')]
  public function mailAlter(array &$message): void {
    if ($message['id'] !== 'user_password_reset') {
      return;
    }
    $account = $message['params']['account'] ?? NULL;

    if (!$account instanceof UserInterface || !$this->authmap->getAll((int) $account->id())) {
      return;
    }
    $message['send'] = FALSE;

    $this->logger->notice('Password reset email was not sent to an external user: %name.', [
      '%name' => $account->getAccountName(),
    ]);
  }

}
