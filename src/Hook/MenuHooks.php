<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo\Hook;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Menu hooks.
 */
final readonly class MenuHooks {

  public function __construct(
    private RouteMatchInterface $routeMatch,
    private Connection $database,
  ) {
  }

  /**
   * Implements hook_menu_local_tasks_alter().
   */
  #[Hook('menu_local_tasks_alter')]
  public function alterMenu(&$data, $route_name, RefinableCacheableDependencyInterface &$cacheability): void {
    if (!in_array($route_name, ['entity.user.canonical', 'entity.user.edit_form'])) {
      return;
    }

    $cacheability->addCacheTags([
      'user.permissions',
      'url',
    ]);

    // Route user might not be the current user.
    // We don't need to care about permissions here because we are only
    // removing a link to the user's TFA settings. The appearance of the
    // link is controlled by 'administer tfa for other users' permission.
    $routeUser = $this->routeMatch->getParameter('user');

    // The user has used an SSO login if they have authmap entry.
    $ssoUser = (bool) $this->database->select('authmap', 'am')
      ->condition('uid', $routeUser->id())
      ->countQuery()
      ->execute()
      ->fetchField();

    // Remove TFA link if set.
    if ($ssoUser && isset($data['tabs'])) {
      foreach ($data['tabs'] as &$tab) {
        unset($tab['tfa.overview']);
      }
    }
  }

}
