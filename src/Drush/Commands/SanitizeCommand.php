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
    $this->database->query(
      "UPDATE {users_field_data} ufd INNER JOIN {authmap} am ON am.uid = ufd.uid SET ufd.name = CONCAT('user', ufd.uid)"
    );
  }

  /**
   * {@inheritDoc}
   */
  #[CLI\Hook(type: HookManager::ON_EVENT, target: SanitizeCommands::CONFIRMS)]
  public function messages(array &$messages, InputInterface $input): void {
    $messages[] = 'Sanitize tunnistamo usernames.';
  }

}
