<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\helfi_api_base\Features\FeatureManager;
use Drupal\helfi_api_base\UserExpire\UserExpireManager;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;

/**
 * Tests API Base's user expiration feature with Tunnistamo.
 *
 * @group helfi_tunnistamo
 */
class UserExpireTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    /** @var \Drupal\helfi_api_base\Features\FeatureManager $featureManager */
    $featureManager = $this->container->get(FeatureManager::class);
    $featureManager->enableFeature(FeatureManager::USER_EXPIRE);
  }

  /**
   * Gets the SUT.
   *
   * @return \Drupal\helfi_api_base\UserExpire\UserExpireManager
   *   The SUT.
   */
  public function getSut() : UserExpireManager {
    return $this->container->get(UserExpireManager::class);
  }

  /**
   * Tests the expired users.
   */
  public function testTunnistamoUsers() : void {
    /** @var \Drupal\user\UserInterface[] $users */
    $users = [
      '1' => $this->createUser(),
      '2' => $this->createUser(),
    ];

    foreach ($users as $user) {
      // Make sure users have never logged in.
      $this->assertEquals(0, $user->getLastAccessedTime());
      $this->assertTrue($user->getCreatedTime() > 0);
      // Set access time over the threshold.
      $user->setLastAccessTime(strtotime('-7 months'))
        ->setChangedTime(strtotime('-2 days'))
        ->save();
    }
    /** @var \Drupal\externalauth\ExternalAuthInterface $externalAuth */
    $externalAuth = $this->container->get('externalauth.externalauth');
    $externalAuth->linkExistingAccount('123', 'openid_connect.tunnistamo', $users['2']);

    // Make sure user 2 is not marked as expired after logging in using
    // Tunnistamo.
    $this->getSut()->cancelExpiredUsers();

    $this->assertTrue(User::load(1)->isBlocked());
    $this->assertFalse(User::load(2)->isBlocked());

    foreach ([1, 2] as $uid) {
      User::load($uid)->setLastAccessTime(strtotime('-5 years 1 day'))
        ->setChangedTime(strtotime('-2 days'))
        ->save();
    }
    $this->getSut()->deleteExpiredUsers();
    $this->assertNull(User::load(1));
    // Make sure Tunnistamo users are deleted as well.
    $this->assertNotNull(User::load(2));
  }

}
