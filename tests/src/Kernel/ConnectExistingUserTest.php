<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\openid_connect\OpenIDConnectClientEntityInterface;
use Drupal\openid_connect\Plugin\OpenIDConnectClientInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests connecting the OpenID provider users to existing accounts.
 */
#[Group('helfi_tunnistamo')]
#[RunTestsInSeparateProcesses]
class ConnectExistingUserTest extends KernelTestBase {

  use ProphecyTrait;
  use UserCreationTrait;

  /**
   * Gets the client mock.
   *
   * @param array<string, mixed> $userInfo
   *   The userinfo.
   *
   * @return \Drupal\openid_connect\OpenIDConnectClientEntityInterface
   *   The client mock.
   */
  private function getClientMock(array $userInfo) : OpenIDConnectClientEntityInterface {
    $plugin = $this->prophesize(OpenIDConnectClientInterface::class);
    $plugin->usesUserInfo()->willReturn(TRUE);
    $plugin->retrieveUserInfo(Argument::cetera())->willReturn($userInfo);
    $client = $this->prophesize(OpenIDConnectClientEntityInterface::class);
    $client->id()->willReturn('tunnistamo');
    $client->getPlugin()->willReturn($plugin->reveal());

    return $client->reveal();
  }

  /**
   * Make sure the update hook disables connecting to existing accounts.
   */
  public function testConnectExistingUser() : void {
    $this->config('openid_connect.settings')
      ->set('connect_existing_users', TRUE)
      ->save();

    $this->container->get('module_handler')->loadInclude('helfi_tunnistamo', 'install');
    helfi_tunnistamo_update_9006();
    $this->assertFalse($this->config('openid_connect.settings')->get('connect_existing_users'));

    $this->createUser(values: ['mail' => 'existing@example.com']);
    $userInfo = [
      'sub' => '123',
      'email' => 'existing@example.com',
      'email_verified' => TRUE,
    ];

    $status = $this->container->get('openid_connect.openid_connect')
      ->completeAuthorization($this->getClientMock($userInfo), [
        'access_token' => '123',
      ]);
    $this->assertFalse($status);

    $uid = $this->container->get('externalauth.authmap')
      ->getUid('123', 'openid_connect.tunnistamo');
    $this->assertFalse($uid);
  }

}
