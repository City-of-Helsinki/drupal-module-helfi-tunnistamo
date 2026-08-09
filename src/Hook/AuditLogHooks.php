<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_api_base\AuditLog\AuditLogServiceInterface;
use Drupal\helfi_api_base\AuditLog\Event\AuditLogEvent;
use Drupal\user\UserInterface;

/**
 * Audit log hooks.
 */
final readonly class AuditLogHooks {

  public function __construct(
    private AuditLogServiceInterface $auditLogService,
  ) {
  }

  /**
   * Implements hook_openid_connect_post_authorize().
   */
  #[Hook('openid_connect_post_authorize')]
  public function onPostAuthorize(UserInterface $account, array $context): void {
    $this->auditLogService->logOperation(new AuditLogEvent(
      operation: 'TUNNISTAMO_LOGIN',
      message: sprintf('User "%s" successfully authenticated via SSO.', $context['userinfo']['name']),
      target: [
        'id' => $context['userinfo']['sub'],
        'type' => 'USER',
        'name' => $context['userinfo']['name'],
      ],
    ));
  }

}
