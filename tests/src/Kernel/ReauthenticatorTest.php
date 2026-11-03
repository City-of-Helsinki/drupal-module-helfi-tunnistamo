<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\Core\Url;
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
   * Tests users who can't log in again.
   */
  public function testRequirements(): void {
    // Anonymous user.
    $this->setupSession(['access' => 'access-token']);
    $this->assertReauthenticateFails('Only logged in users');

    // User did not log in with Tunnistamo.
    $this->setupSession();
    $this->setUpCurrentUser();
    $this->assertReauthenticateFails('did not log in with Tunnistamo');

    // User is not linked to Tunnistamo.
    $this->setupSession(['access' => 'access-token']);
    $this->assertReauthenticateFails('not linked to Tunnistamo');
  }

  /**
   * Tests that the user is sent to log in with the connect operation.
   */
  public function testReauthenticate(): void {
    $this->setupSession(['access' => 'access-token']);
    $uid = (int) $this->setUpCurrentUser()->id();
    $this->linkCurrentUser('123');

    $response = $this->sut()->reauthenticate(Url::fromUri('internal:/some/path'));
    $this->assertStringStartsWith('https://localhost/authorization?', (string) $response->headers->get('Location'));

    $oidcSession = $this->container->get('openid_connect.session');
    $this->assertSame(['op' => 'connect', 'uid' => $uid], $oidcSession->retrieveOp(FALSE));
    $this->assertSame('some/path', $oidcSession->retrieveDestination(FALSE)['destination']);
  }

  /**
   * Gets the reauthenticator.
   *
   * Fetched after setupSession(), which rebuilds the container: an instance
   * from before the rebuild holds on to the old session and current user.
   */
  private function sut(): ReauthenticatorInterface {
    return $this->container->get(ReauthenticatorInterface::class);
  }

  /**
   * Asserts that reauthentication throws \LogicException.
   */
  private function assertReauthenticateFails(string $message): void {
    try {
      $this->sut()->reauthenticate(Url::fromUri('internal:/some/path'));
      $this->fail('Expected exception was not thrown.');
    }
    catch (\LogicException $e) {
      $this->assertStringContainsString($message, $e->getMessage());
    }
  }

}
