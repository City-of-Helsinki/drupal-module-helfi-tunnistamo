<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

/**
 * Tests Tunnistamo configuration.
 *
 * @coversDefaultClass \Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo
 * @group helfi_tunnistamo
 */
class TunnistamoClientTest extends KernelTestBase {

  /**
   * Make sure the correct scopes are returned.
   *
   * @covers ::getClientScopes
   */
  public function testGetClientScopes() : void {
    $plugin = $this->getPlugin();
    $config = $plugin->getConfiguration();
    $this->assertSame($config['client_scopes'], $plugin->defaultConfiguration()['client_scopes']);
    $this->setPluginConfiguration('client_scopes', '');
    $this->assertSame([], $this->getPlugin()->getClientScopes());
  }

  /**
   * Make sure Tunnistamo is enabled by default.
   *
   * @covers ::getConfiguration
   * @covers ::defaultConfiguration
   * @covers ::create
   * @covers ::setConfiguration
   */
  public function testEnable() : void {
    $config = $this->getPlugin()
      ->getConfiguration();
    $this->assertEquals('placeholder', $config['client_id']);
    $this->assertEquals('placeholder', $config['client_secret']);
    $this->assertEquals(0, $config['auto_login']);
    $this->assertEquals([], $config['client_roles']);
    $this->assertEquals('', $config['environment_url']);
  }

  /**
   * Make sure empty environment url throws an exception.
   */
  public function testEmptyEndpointException() : void {
    $this->setPluginConfiguration('environment_url', '');
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Missing required "environment_url" configuration.');
    $this->getPlugin()->getEndpoints();
  }

  /**
   * Tests missing auto-discovered endpoint.
   */
  public function testEndpointConfigurationException() : void {
    $this->setupEndpoints(authorization: NULL);
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Missing required "authorization" endpoint configuration.');
    $this->getPlugin()->getEndpoints();
  }

  /**
   * Tests environment url configuration.
   */
  public function testEndpoints() : void {
    $this->setupEndpoints();
    $this->assertEquals('https://localhost', $this->getPluginConfiguration()->get('settings')['environment_url']);
    $endpoints = $this->getPlugin()->getEndpoints();

    foreach (['authorization', 'token', 'userinfo', 'end_session'] as $endpoint) {
      $this->assertNotEmpty($endpoints[$endpoint]);
    }
  }

}
