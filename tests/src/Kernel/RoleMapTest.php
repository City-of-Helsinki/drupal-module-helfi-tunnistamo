<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests Tunnistamo role map functionality.
 *
 * @group helfi_tunnistamo
 */
class RoleMapTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * Tests that roles are mapped accordingly.
   */
  public function testRoleMap() : void {
    $account = $this->createUser();
    // Create a new role and tell our plugin to map the role.
    $role = $this->createRole([], 'test');
    $this->setPluginConfiguration('client_roles', [$role => $role]);
    $this->setPluginConfiguration('ad_roles_disabled_amr', ['something']);

    $this->getPlugin()->mapRoles($account, ['userinfo' => ['ad_groups' => [], 'amr' => ['something']]]);
    // Our account should not have the newly added role now, amr is disabled.
    $this->assertEquals([
      AccountInterface::AUTHENTICATED_ROLE,
    ], $account->getRoles());

    $this->getPlugin()->mapRoles($account, ['userinfo' => ['ad_groups' => []]]);
    // Our account should have the newly added role now.
    $this->assertEquals([
      AccountInterface::AUTHENTICATED_ROLE,
      $role,
    ], $account->getRoles());

    $this->setPluginConfiguration('client_roles', [
      AccountInterface::AUTHENTICATED_ROLE => AccountInterface::AUTHENTICATED_ROLE,
    ]);

    $this->getPlugin()->mapRoles($account, []);

    // Make sure our custom role is not removed since ad_groups was not set.
    $this->assertEquals([
      AccountInterface::AUTHENTICATED_ROLE,
      $role,
    ], $account->getRoles());

    $this->getPlugin()->mapRoles($account, ['userinfo' => ['ad_groups' => []]]);

    // Make sure our custom role is removed.
    $this->assertEquals([
      AccountInterface::AUTHENTICATED_ROLE,
    ], $account->getRoles());

    $role2 = $this->createRole([], 'test2');
    $this->setPluginConfiguration('client_roles', [$role => $role]);
    $this->setPluginConfiguration('ad_roles', [
      [
        'ad_role' => 'ad_role',
        'roles' => [$role2],
      ],
      // Test non-existent ad role.
      [
        'ad_role' => 'non_existent',
        'roles' => [$role2],
      ],
    ]);
    $this->getPlugin()->mapRoles($account, ['userinfo' => ['ad_groups' => ['ad_role']]]);
    $this->assertEquals([
      AccountInterface::AUTHENTICATED_ROLE,
      $role,
      $role2,
    ], $account->getRoles());
  }

}
