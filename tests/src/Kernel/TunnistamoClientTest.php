<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\user\Entity\Role;

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
    $this->assertSame(['openid', 'email'], $this->getPlugin()->getClientScopes());
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

  /**
   * Tests configuration form default values.
   */
  public function testConfigurationForm() : void {
    Role::create(['id' => 'test', 'label' => 'test'])->save();
    $this->setupEndpoints();
    $plugin = $this->getPlugin();
    $configuration = $plugin->getConfiguration();
    $form = $plugin->buildConfigurationForm([], new FormState());
    $this->assertEquals($configuration['auto_login'], $form['auto_login']['#default_value']);
    $this->assertEquals($configuration['client_scopes'], $form['client_scopes']['#default_value']);
    $this->assertEquals('https://localhost', $form['environment_url']['#default_value']);
    $this->assertEquals(['test' => 'test'], $form['client_roles']['#options']);
    $this->assertEquals($configuration['client_roles'], $form['client_roles']['#default_value']);
  }

}
