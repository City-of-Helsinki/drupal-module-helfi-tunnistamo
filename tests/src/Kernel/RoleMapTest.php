<?php

declare(strict_types = 1);

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

    $this->getPlugin()->mapClientRoles($account);
    // Our account should have the newly added role now.
    $this->assertEquals([
      AccountInterface::AUTHENTICATED_ROLE,
      $role,
    ], $account->getRoles());

    $this->setPluginConfiguration('client_roles', [
      AccountInterface::AUTHENTICATED_ROLE => AccountInterface::AUTHENTICATED_ROLE,
    ]);

    $this->getPlugin()->mapClientRoles($account);
    // Make sure our custom role is removed.
    $this->assertEquals([
      AccountInterface::AUTHENTICATED_ROLE,
    ], $account->getRoles());
  }

}
