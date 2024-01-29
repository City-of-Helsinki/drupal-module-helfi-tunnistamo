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
    $this->installSchema('user', ['users_data']);
    $this->installConfig('user');
  }

  /**
   * Gets the client mock.
   *
   * @param array $userInfo
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
   * Gets the open id connect service.
   *
   * @return \Drupal\openid_connect\OpenIDConnect
   *   The openid connect.
   */
  private function openIdConnect() : OpenIDConnect {
    return $this->container->get('openid_connect.openid_connect');
  }

  /**
   * Tests normal user trying to use edu client.
   */
  public function testEduClientWithEmail() : void {
    $userInfo = [
      'email' => 'test@example.com',
      'sub' => '123',
    ];

    $this->setPluginConfiguration('edu_client', TRUE);
    $status = $this->openIdConnect()
      ->completeAuthorization($this->getClientMock($userInfo), [
        'access_token' => '123',
      ]);

    // Users with email cannot log in using edu client.
    $this->assertFalse($status);
  }

  /**
   * Tests authorization email and username fallback.
   *
   * @dataProvider authorizationData
   */
  public function testAuthorization(array $userInfo, string $expectedEmail, string $expectedUsername) : void {
    // Allow empty usernames:
    $this->setPluginConfiguration('edu_client', empty($userInfo['email']));

    $status = $this->openIdConnect()
      ->completeAuthorization($this->getClientMock($userInfo), [
        'access_token' => '123',
      ]);
    $this->assertTrue($status);

    /** @var \Drupal\externalauth\Authmap $authmap */
    $authmap = $this->container->get('externalauth.authmap');
    $uid = $authmap->getUid($userInfo['sub'], 'openid_connect.tunnistamo');
    $user = User::load($uid);
    $this->assertEquals($expectedEmail, $user->getEmail());
    $this->assertEquals($expectedUsername, $user->getAccountName());
  }

  /**
   * Data provider for testAuthorization().
   *
   * @return array[]
   *   The data.
   */
  public function authorizationData() : array {
    return [
      // Make sure authorization succeeds, and a random email address is
      // generated when a user has no email.
      [
        [
          'email' => '',
          'sub' => '123',
          'name' => 'Cats',
          'preferred_username' => '9cf5e439-529b-4d6c-b9f3-4738fb90c55f',
        ],
        '123+placeholder@hel.fi',
        'Cats',
      ],
      // Make sure the original email is used when set.
      [
        [
          'email' => 'test@example.com',
          'sub' => '123',
          'name' => 'Cats',
          'preferred_username' => 'Dogs',
        ],
        'test@example.com',
        'Dogs',
      ],
    ];
  }

}
