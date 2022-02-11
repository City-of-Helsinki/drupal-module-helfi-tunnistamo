<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo;

/**
 * Tests Tunnistamo configuration.
 *
 * @group helfi_tunnistamo
 */
class ConfigTest extends KernelTestBase {

  /**
   * Make sure tunnistamo is enabled by default.
   */
  public function testEnable() : void {
    $config = $this->getPluginConfiguration()
      ->get('settings');
    $this->assertEquals('placeholder', $config['client_id']);
    $this->assertEquals('placeholder', $config['client_secret']);
    $this->assertEquals(0, $config['auto_login']);
    $this->assertEquals([], $config['client_roles']);
    $this->assertEquals(0, $config['is_production']);
    $this->assertEquals('', $config['environment_url']);
  }

  /**
   * Asserts that endpoint base url matches the given url.
   *
   * @param string $expected
   *   The expected endpoint url.
   */
  private function assertEndpoint(string $expected) : void {
    array_map(function (string $url) use ($expected) {
      $this->assertTrue(str_starts_with($url, $expected));
    }, $this->getPlugin()->getEndpoints());
  }

  /**
   * Tests environment url configuration.
   */
  public function testEndpoints() : void {
    // Endpoint should default to testing environment.
    $this->assertEndpoint(Tunnistamo::TESTING_ENVIRONMENT);

    // Endpoint should default to production environment when 'is_production' is
    // set to true.
    $this->setPluginConfiguration('is_production', TRUE);
    $this->assertEndpoint(Tunnistamo::PRODUCTION_ENVIRONMENT);

    // Endpoint should default to whatever we set as base url in
    // 'environment_url'.
    $this->setPluginConfiguration('is_production', FALSE);
    $this->setPluginConfiguration('environment_url', 'https://example.com');
    $this->assertEndpoint('https://example.com');
  }

}
