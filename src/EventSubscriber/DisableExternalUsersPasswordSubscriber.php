<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_api_base\EventSubscriber\DeployHookEventSubscriberBase;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Sets tunnistamo users' password to NULL.
 *
 * This should prevent given users from logging in using password.
 */
final class DisableExternalUsersPasswordSubscriber extends DeployHookEventSubscriberBase {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function onPostDeploy(Event $event) : void {
    // Query tunnistamo users that have their passwords set.
    $query = $this->database->select('authmap', 'am');
    $query->leftJoin('users_field_data', 'ufd', 'ufd.uid = am.uid');
    $query
      ->fields('am', ['uid'])
      ->condition('ufd.pass', NULL, 'IS NOT NULL')
      // Make sure we have an upper bound.
      ->range(0, 50);

    $storage = $this->entityTypeManager->getStorage('user');
    foreach ($query->execute()->fetchCol() as $id) {
      /** @var \Drupal\user\UserInterface $account */
      $account = $storage->load($id);

      // Set user password to null. This prevents the user
      // from logging in with the local user in the future.
      $account
        ->setPassword(NULL)
        ->save();
    }
  }

}
