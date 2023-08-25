<?php

declare(strict_types = 1);

namespace Drupal\helfi_tunnistamo;

use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * A helper trait to deal with user accounts.
 */
trait AccountTrait {

  /**
   * Removes all roles from given user.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account.
   *
   * @return static
   *   The self.
   */
  protected function removeRoles(UserInterface $account) : static {
    static $called = NULL;

    // Make sure roles are only removed once.
    if ($called === NULL) {
      array_map(
        fn (string $rid) => $account->removeRole($rid),
        $account->getRoles(FALSE)
      );
      $called = TRUE;
    }
    return $this;
  }

  /**
   * Grant given roles to user.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account.
   * @param string[] $roles
   *   The roles to map.
   *
   * @return static
   *   The self.
   */
  protected function mapRoles(UserInterface $account, array $roles) : static {
    foreach ($roles as $rid) {
      // Trying to add authenticated or anonymous role will throw an
      // exception.
      if (in_array($rid, [
        AccountInterface::AUTHENTICATED_ROLE,
        AccountInterface::ANONYMOUS_ROLE,
      ])) {
        continue;
      }
      $account->addRole($rid);
    }
    $account->save();

    return $this;
  }

}
