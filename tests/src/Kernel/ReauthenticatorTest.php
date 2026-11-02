<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\helfi_tunnistamo\ReauthenticatorInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests logging the user in again.
 */
#[Group('helfi_tunnistamo')]
#[RunTestsInSeparateProcesses]
class ReauthenticatorTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('openid_connect');
    $this->setPluginConfiguration('environment_url', 'https://localhost');
  }

  /**
   * Gets the reauthenticator.
   */
  private function sut(): ReauthenticatorInterface {
    return $this->container->get(ReauthenticatorInterface::class);
  }

  /**
   * Tests users who can't log in again.
   */
  public function testRequirements(): void {
    // Anonymous user.
    $this->setupSession(['access' => 'access-token']);
    $this->assertLogicException(fn () => $this->sut()->reauthenticate(), 'Only logged in users');

    // User did not log in with Tunnistamo.
    $this->setupSession();
    $this->setUpCurrentUser();
    $this->assertLogicException(fn () => $this->sut()->reauthenticate(), 'did not log in with Tunnistamo');

    // User is not linked to Tunnistamo.
    $this->setupSession(['access' => 'access-token']);
    $this->setUpCurrentUser();
    $this->assertLogicException(fn () => $this->sut()->reauthenticate(), 'not linked to Tunnistamo');

    // Client is disabled.
    $this->setupSession(['access' => 'access-token']);
    $this->setUpCurrentUser();
    $this->linkCurrentUser('123');
    $this->getPluginConfiguration()->set('status', FALSE)->save();
    $this->assertLogicException(fn () => $this->sut()->reauthenticate(), 'not enabled');
  }

  /**
   * Tests that the user is sent to log in with the connect operation.
   */
  public function testReauthenticate(): void {
    $this->setupSession(['access' => 'access-token']);
    $uid = (int) $this->setUpCurrentUser()->id();
    $this->linkCurrentUser('123');

    $response = $this->sut()->reauthenticate();
    $this->assertStringStartsWith('https://localhost/authorization?', (string) $response->headers->get('Location'));

    $oidcSession = $this->container->get('openid_connect.session');
    $this->assertSame(['op' => 'connect', 'uid' => $uid], $oidcSession->retrieveOp(FALSE));
    // The user is sent back to the current page.
    $this->assertSame(
      ltrim($this->container->get('redirect.destination')->get(), '/'),
      $oidcSession->retrieveDestination(FALSE)['destination'],
    );
  }

  /**
   * Asserts that the callback throws \LogicException.
   */
  private function assertLogicException(callable $callback, string $message): void {
    try {
      $callback();
      $this->fail('Expected exception was not thrown.');
    }
    catch (\LogicException $e) {
      $this->assertStringContainsString($message, $e->getMessage());
    }
  }

}
