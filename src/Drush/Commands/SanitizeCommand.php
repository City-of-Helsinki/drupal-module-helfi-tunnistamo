<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Database\Connection;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Commands\sql\sanitize\SanitizeCommands;
use Drush\Commands\sql\sanitize\SanitizePluginInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Hooks into Drush sanitization commands.
 */
class SanitizeCommand extends DrushCommands implements SanitizePluginInterface {

  use AutowireTrait;

  /**
   * Constructs a new instance.
   */
  public function __construct(
    private readonly Connection $database,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritDoc}
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: SanitizeCommands::SANITIZE)]
  public function sanitize($result, CommandData $commandData): void {
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

  /**
   * {@inheritDoc}
   */
  #[CLI\Hook(type: HookManager::ON_EVENT, target: SanitizeCommands::CONFIRMS)]
  public function messages(array &$messages, InputInterface $input): void {
    $messages[] = 'Sanitize OpenID provider users.';
  }

}
