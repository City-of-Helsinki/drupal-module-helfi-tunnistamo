<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\openid_connect\OpenIDConnectClientEntityInterface;
use Drupal\openid_connect\Plugin\OpenIDConnectClientInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('openid_connect.settings')
      ->set('connect_existing_users', TRUE)
      ->save();
  }

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
   * Make sure only the verified email addresses are connected.
   */
  #[DataProvider('connectData')]
  public function testConnectExistingUser(mixed $emailVerified, bool $expected) : void {
    $account = $this->createUser(values: ['mail' => 'existing@example.com']);
    $userInfo = [
      'sub' => '123',
      'email' => 'existing@example.com',
      'email_verified' => $emailVerified,
    ];

    $status = $this->container->get('openid_connect.openid_connect')
      ->completeAuthorization($this->getClientMock(array_filter($userInfo, fn ($value) => $value !== NULL)), [
        'access_token' => '123',
      ]);
    $this->assertEquals($expected, $status);

    $uid = $this->container->get('externalauth.authmap')
      ->getUid('123', 'openid_connect.tunnistamo');
    $this->assertEquals($expected ? $account->id() : FALSE, $uid);
  }

  /**
   * Data provider for testConnectExistingUser().
   *
   * @return array<array{mixed, bool}>
   *   The data.
   */
  public static function connectData() : array {
    return [
      [TRUE, TRUE],
      [FALSE, FALSE],
      ['true', FALSE],
      [NULL, FALSE],
    ];
  }

  /**
   * Make sure new and already connected users don't need a verified email.
   */
  public function testUnverifiedEmail() : void {
    $this->config('user.settings')
      ->set('register', 'visitors')
      ->save();
    $userInfo = [
      'sub' => '123',
      'email' => 'new@example.com',
      'email_verified' => FALSE,
    ];
    $openIdConnect = $this->container->get('openid_connect.openid_connect');

    // A new account is created.
    $this->assertTrue($openIdConnect->completeAuthorization($this->getClientMock($userInfo), ['access_token' => '123']));

    // The user can log in again with the connected account.
    $this->container->get('current_user')->setAccount(new AnonymousUserSession());
    $this->assertTrue($openIdConnect->completeAuthorization($this->getClientMock($userInfo), ['access_token' => '123']));
  }

}
