<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests default tunnistamo configuration.
 *
 * @group helfi_tunnistamo
 */
class LoginFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'openid_connect',
    'helfi_tunnistamo',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Make sure tunnistamo login button is visible by default.
   */
  public function testLoginForm() : void {
    $this->drupalGet('/user/login');
    $this->assertSession()->buttonExists('Log in with Tunnistamo');
  }

}
