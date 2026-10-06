<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_tunnistamo\Drush\Listeners\SanitizeListener;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Kernel tests for the sanitize listener.
 */
#[Group('helfi_tunnistamo')]
#[RunTestsInSeparateProcesses]
#[CoversClass(SanitizeListener::class)]
class SanitizeListenerTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * The test user IDs.
   *
   * @var array<int, int>
   */
  private array $uids;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Drupal\user\UserInterface[] $users */
    $users = [
      1 => $this->createUser(name: 'Test user 1'),
      2 => $this->createUser(name: 'Test user 2'),
      3 => $this->createUser(name: 'Test user 3'),
    ];

    array_map(static fn (UserInterface $user) => $user->save(), $users);

    /** @var \Drupal\externalauth\ExternalAuthInterface $externalAuth */
    $externalAuth = $this->container->get('externalauth.externalauth');
    $externalAuth->linkExistingAccount('123', 'openid_connect.tunnistamo', $users[2]);

    $this->uids = array_map(static fn (UserInterface $user) => (int) $user->id(), $users);

    $userData = $this->container->get(UserDataInterface::class);
    // User 1 has unrelated user data only.
    $userData->set('openid_connect', $this->uids[1], 'other', 'Test user 1');
    $userData->set('helfi_tunnistamo', $this->uids[1], 'oidc_name', 'Test user 1');
    // User 3 has logged in with OpenID Connect, but the authmap row has been
    // removed since.
    $userData->set('openid_connect', $this->uids[3], 'oidc_name', 'Test user 3');
  }

  /**
   * Tests sanitization.
   */
  public function testSanitize(): void {
    $this->getSut()->onConsoleTerminate($this->createTerminateEvent('sql:sanitize', 0));

    $storage = $this->container->get(EntityTypeManagerInterface::class)->getStorage('user');
    $storage->resetCache();
    $userData = $this->container->get(UserDataInterface::class);

    $this->assertEquals('Test user 1', $storage->load($this->uids[1])->getAccountName());
    $this->assertEquals('Test user 1', $userData->get('openid_connect', $this->uids[1], 'other'));
    $this->assertEquals('Test user 1', $userData->get('helfi_tunnistamo', $this->uids[1], 'oidc_name'));

    // User 2 is renamed because of the authmap row.
    $this->assertEquals('user' . $this->uids[2], $storage->load($this->uids[2])->getAccountName());
    $this->assertNull($userData->get('openid_connect', $this->uids[2], 'oidc_name'));

    // User 3 is renamed because of the saved name.
    $this->assertEquals('user' . $this->uids[3], $storage->load($this->uids[3])->getAccountName());
    $this->assertEquals('user' . $this->uids[3], $userData->get('openid_connect', $this->uids[3], 'oidc_name'));
  }

  /**
   * Tests that other commands and failed or aborted runs sanitize nothing.
   */
  #[TestWith(['sql:sanitize', 1])]
  #[TestWith(['sql:dump', 0])]
  public function testSkip(string $commandName, int $exitCode): void {
    $this->getSut()->onConsoleTerminate($this->createTerminateEvent($commandName, $exitCode));

    $storage = $this->container->get(EntityTypeManagerInterface::class)->getStorage('user');
    $storage->resetCache();

    foreach ($this->uids as $key => $uid) {
      $this->assertEquals('Test user ' . $key, $storage->load($uid)->getAccountName());
    }
    $this->assertEquals('Test user 3', $this->container->get(UserDataInterface::class)->get('openid_connect', $this->uids[3], 'oidc_name'));
  }

  /**
   * Creates the event Drush dispatches when a command finishes.
   *
   * @param string $commandName
   *   The command name.
   * @param int $exitCode
   *   The exit code.
   *
   * @return \Symfony\Component\Console\Event\ConsoleTerminateEvent
   *   The event.
   */
  private function createTerminateEvent(string $commandName, int $exitCode) : ConsoleTerminateEvent {
    return new ConsoleTerminateEvent(new Command($commandName), new ArrayInput([]), new NullOutput(), $exitCode);
  }

  /**
   * Gets the SUT.
   *
   * @return \Drupal\helfi_tunnistamo\Drush\Listeners\SanitizeListener
   *   The SUT.
   */
  private function getSut() : SanitizeListener {
    return new SanitizeListener($this->container->get(Connection::class));
  }

}
