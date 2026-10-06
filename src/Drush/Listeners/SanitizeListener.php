<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo\Drush\Listeners;

use Drupal\Core\Database\Connection;
use Drush\Commands\AutowireTrait;
use Drush\Event\SanitizeConfirmsEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Sanitizes the names of OpenID provider users on drush sql:sanitize.
 */
#[AsEventListener(method: 'onSanitizeConfirm')]
#[AsEventListener(method: 'onConsoleTerminate')]
class SanitizeListener {

  use AutowireTrait;

  /**
   * Constructs a new instance.
   */
  public function __construct(
    private readonly Connection $database,
  ) {
  }

  /**
   * Adds the operation to the list shown before confirmation.
   *
   * @param \Drush\Event\SanitizeConfirmsEvent $event
   *   The event.
   */
  public function onSanitizeConfirm(SanitizeConfirmsEvent $event): void {
    $event->addMessage('Sanitize OpenID provider users.');
  }

  /**
   * Sanitizes the database once sql:sanitize has been confirmed.
   *
   * @param \Symfony\Component\Console\Event\ConsoleTerminateEvent $event
   *   The event.
   */
  public function onConsoleTerminate(ConsoleTerminateEvent $event): void {
    if ($event->getCommand()?->getName() !== 'sql:sanitize' || $event->getExitCode()) {
      return;
    }

    // Usernames of OpenID provider users are generated from their first and
    // last name. Users whose authmap row has been removed still have the name
    // saved in user data.
    $this->database->query(
      "UPDATE {users_field_data} SET name = CONCAT('user', uid) WHERE uid IN (SELECT am.uid FROM {authmap} am) OR uid IN (SELECT ud.uid FROM {users_data} ud WHERE ud.module = 'openid_connect' AND ud.name = 'oidc_name')"
    );
    // The openid_connect module saves the first and last name on every login.
    $this->database->query(
      "UPDATE {users_data} SET value = CONCAT('user', uid), serialized = 0 WHERE module = 'openid_connect' AND name = 'oidc_name'"
    );
  }

}
