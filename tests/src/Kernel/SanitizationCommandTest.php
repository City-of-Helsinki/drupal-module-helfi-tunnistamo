<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_tunnistamo\Drush\Commands\SanitizeCommand;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Kernel tests for sanitization command.
 */
#[Group('helfi_tunnistamo')]
#[RunTestsInSeparateProcesses]
#[CoversClass(SanitizeCommand::class)]
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
      '3' => $this->createUser(name: 'Test user 3'),
    ];

    array_map(static fn (UserInterface $user) => $user->save(), $users);

    /** @var \Drupal\externalauth\ExternalAuthInterface $externalAuth */
    $externalAuth = $this->container->get('externalauth.externalauth');
    $externalAuth->linkExistingAccount('123', 'openid_connect.tunnistamo', $users['2']);

    $uids = array_map(static fn (UserInterface $user) => (int) $user->id(), $users);

    $userData = $this->container->get(UserDataInterface::class);
    // User 1 has unrelated user data only.
    $userData->set('openid_connect', $uids['1'], 'other', 'Test user 1');
    $userData->set('helfi_tunnistamo', $uids['1'], 'oidc_name', 'Test user 1');
    // User 3 has logged in with OpenID Connect, but the authmap row has been
    // removed since.
    $userData->set('openid_connect', $uids['3'], 'oidc_name', 'Test user 3');

    $sut = $this->getSut();

    $messages = [];
    $input = $this->prophesize(InputInterface::class);
    $sut->messages($messages, $input->reveal());
    $this->assertNotEmpty($messages);

    $commandData = $this->prophesize(CommandData::class);
    $sut->sanitize(0, $commandData->reveal());

    $storage = $this->container->get(EntityTypeManagerInterface::class)->getStorage('user');
    $storage->resetCache();
    $this->assertEquals('Test user 1', $storage->load($uids['1'])->getAccountName());
    $this->assertEquals('Test user 1', $userData->get('openid_connect', $uids['1'], 'other'));
    $this->assertEquals('Test user 1', $userData->get('helfi_tunnistamo', $uids['1'], 'oidc_name'));

    // User 2 is renamed because of the authmap row.
    $this->assertEquals('user' . $uids['2'], $storage->load($uids['2'])->getAccountName());
    $this->assertNull($userData->get('openid_connect', $uids['2'], 'oidc_name'));

    // User 3 is renamed because of the saved name.
    $this->assertEquals('user' . $uids['3'], $storage->load($uids['3'])->getAccountName());
    $this->assertEquals('user' . $uids['3'], $userData->get('openid_connect', $uids['3'], 'oidc_name'));
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
