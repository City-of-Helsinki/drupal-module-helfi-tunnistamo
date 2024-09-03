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

    $this->getPlugin()->mapRoles($account, ['userinfo' => ['ad_groups' => [], 'amr' => ['something'], 'loa' => 'weak']]);
    // Our account should not have the newly added role now, amr is disabled.
    $this->assertEquals([
      AccountInterface::AUTHENTICATED_ROLE,
    ], $account->getRoles());

    $this->getPlugin()->mapRoles($account, ['userinfo' => ['ad_groups' => [], 'loa' => 'weak']]);
    // Our account should have the newly added role now.
    $this->assertEquals([
      AccountInterface::AUTHENTICATED_ROLE,
      $role,
    ], $account->getRoles());

    $this->setPluginConfiguration('client_roles', [
      AccountInterface::AUTHENTICATED_ROLE => AccountInterface::AUTHENTICATED_ROLE,
    ]);

    $this->getPlugin()->mapRoles($account, ['userinfo' => ['ad_groups' => []]]);

    // Make sure our custom role is removed.
    $this->assertEquals([
      AccountInterface::AUTHENTICATED_ROLE,
    ], $account->getRoles());

    $this->getPlugin()->mapRoles($account, []);

    // Custom roles are removed since ad_groups was not set.
    $this->assertEquals([
      AccountInterface::AUTHENTICATED_ROLE,
    ], $account->getRoles());

    // Tests Level of Assurance mapping.
    $this->setPluginConfiguration('loa_roles', [
      [
        'loa' => 'substantial',
        'roles' => [$role],
      ],
    ]);
    $this->getPlugin()->mapRoles($account, ['userinfo' => ['loa' => 'substantial']]);
    $this->assertEquals([
      AccountInterface::AUTHENTICATED_ROLE,
      $role,
    ], $account->getRoles());

    $role2 = $this->createRole([], 'test2');
    $role3 = $this->createRole([], 'test3');
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
    $this->setPluginConfiguration('loa_roles', [
      [
        'loa' => 'substantial',
        'roles' => [$role3],
      ],
    ]);
    $this->getPlugin()->mapRoles($account, ['userinfo' => ['ad_groups' => ['ad_role'], 'loa' => 'substantial']]);
    $this->assertEquals([
      AccountInterface::AUTHENTICATED_ROLE,
      $role,
      $role2,
      $role3,
    ], $account->getRoles());

  }

}
