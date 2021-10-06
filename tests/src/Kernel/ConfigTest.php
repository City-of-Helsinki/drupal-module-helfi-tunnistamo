<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_tunnistamo;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests Tunnistamo configuration.
 *
 * @group helfi_tunnistamo
 */
class ConfigTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_tunnistamo',
    'externalauth',
    'openid_connect',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->installConfig('helfi_tunnistamo');
  }

  /**
   * Make sure tunnistamo is enabled by default.
   */
  public function testEnable() : void {
    $config = $this->config('openid_connect.client.tunnistamo')->get('settings');

    $this->assertEquals('placeholder', $config['client_id']);
    $this->assertEquals('placeholder', $config['client_secret']);
    $this->assertEquals(0, $config['is_production']);
  }

}
