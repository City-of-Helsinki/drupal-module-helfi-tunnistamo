<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the password reset for external users.
 */
#[Group('helfi_tunnistamo')]
#[RunTestsInSeparateProcesses]
class PasswordResetTest extends KernelTestBase {

  use AssertMailTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system', 'user']);
  }

  /**
   * Make sure the password reset email is not sent to external users.
   */
  public function testPasswordResetMail() : void {
    $external = $this->createUser();
    $local = $this->createUser();
    $this->container->get('externalauth.authmap')
      ->save($external, 'test_provider', 'test_authname');

    _user_mail_notify('password_reset', $external);
    $this->assertCount(0, $this->getMails());

    _user_mail_notify('password_reset', $local);
    $this->assertCount(1, $this->getMails());
    $this->assertMail('to', $local->getEmail());
  }

}
