<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\openid_connect\OpenIDConnect;
use Drupal\openid_connect\OpenIDConnectClientEntityInterface;
use Drupal\openid_connect\Plugin\OpenIDConnectClientInterface;
use Drupal\user\Entity\User;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests user_info_alter hooks.
 *
 * @group helfi_tunnistamo
 */
class UserInfoAlterTest extends KernelTestBase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->installSchema('externalauth', ['authmap']);
    $this->installConfig('user');
  }

  /**
   * Gets the client mock.
   *
   * @param array $userInfo
   *   The userinfo.
   * @param string $scopes
   *   The scopes.
   *
   * @return \Drupal\openid_connect\OpenIDConnectClientEntityInterface
   *   The client mock.
   */
  private function getClientMock(array $userInfo, string $scopes = '') : OpenIDConnectClientEntityInterface {
    $plugin = $this->prophesize(OpenIDConnectClientInterface::class);
    $plugin->usesUserInfo()->willReturn(TRUE);
    $plugin->retrieveUserInfo(Argument::cetera())->willReturn($userInfo);
    $client = $this->prophesize(OpenIDConnectClientEntityInterface::class);
    $client->id()->willReturn('tunnistamo');
    $client->getPlugin()->willReturn($plugin->reveal());
    // Our hook_openid_connect_userinfo_alter() loads the actual Tunnistamo
    // client, and we have to override the client scopes from actual client
    // entity.
    $this->setPluginConfiguration('client_scopes', $scopes);

    return $client->reveal();
  }

  /**
   * Gets the open id connect service.
   *
   * @return \Drupal\openid_connect\OpenIDConnect
   *   The openid connect.
   */
  private function openIdConnect() : OpenIDConnect {
    return $this->container->get('openid_connect.openid_connect');
  }

  /**
   * Tests authorization email fallback.
   *
   * @dataProvider authorizationData
   */
  public function testAuthorization(array $userInfo, string $scopes, bool $expectedStatus) : void {
    $status = $this->openIdConnect()
      ->completeAuthorization($this->getClientMock($userInfo, $scopes), [
        'access_token' => '123',
      ]);
    $this->assertSame($expectedStatus, $status);

    // Make sure account is actually created.
    if ($status) {
      /** @var \Drupal\externalauth\Authmap $authmap */
      $authmap = $this->container->get('externalauth.authmap');
      $uid = $authmap->getUid($userInfo['sub'], 'openid_connect.tunnistamo');
      $this->assertEquals(helfi_tunnistamo_create_email($userInfo), User::load($uid)->getEmail());
    }
  }

  /**
   * Data provider for testAuthorization().
   *
   * @return array[]
   *   The data.
   */
  public function authorizationData() : array {
    return [
      [
        [
          'email' => '',
          'sub' => '123',
        ],
        'email',
        FALSE,
      ],
      [
        [
          'email' => '',
          'sub' => '123',
        ],
        'ad_groups',
        TRUE,
      ],
    ];
  }

}
