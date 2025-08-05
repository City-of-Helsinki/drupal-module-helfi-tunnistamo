<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_tunnistamo\Drush\Commands\SanitizeCommand;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Kernel tests for sanitization command.
 */
class SanitizationCommandTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * Tests sanitization hooks.
   */
  public function testSanitizationHooks(): void {
    /** @var \Drupal\user\UserInterface[] $users */
    $users = [
      '1' => $this->createUser(name: 'Test user 1'),
      '2' => $this->createUser(name: 'Test user 2'),
    ];

    array_map(static fn (UserInterface $user) => $user->save(), $users);

    /** @var \Drupal\externalauth\ExternalAuthInterface $externalAuth */
    $externalAuth = $this->container->get('externalauth.externalauth');
    $externalAuth->linkExistingAccount('123', 'openid_connect.tunnistamo', $users['2']);

    $sut = $this->getSut();

    $messages = [];
    $input = $this->prophesize(InputInterface::class);
    $sut->messages($messages, $input->reveal());
    $this->assertNotEmpty($messages);

    $commandData = $this->prophesize(CommandData::class);
    $sut->sanitize(0, $commandData->reveal());

    $storage = $this->container->get(EntityTypeManagerInterface::class)->getStorage('user');
    $this->assertEquals($storage->load($users['1']->id())->label(), 'Test user 1');
    $this->assertNotEquals($storage->load($users['2']->id())->label(), 'Test user 2');
  }

  /**
   * Gets the SUT.
   *
   * @return \Drupal\helfi_tunnistamo\Drush\Commands\SanitizeCommand
   *   The SUT.
   */
  private function getSut() : SanitizeCommand {
    return new SanitizeCommand($this->container->get(Connection::class));
  }

}
