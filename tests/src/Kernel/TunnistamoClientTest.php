<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo;

/**
 * Tests Tunnistamo configuration.
 *
 * @coversDefaultClass \Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo
 * @group helfi_tunnistamo
 */
class TunnistamoClientTest extends KernelTestBase {

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
   * Sets the active environment name.
   *
   * @param string $env
   *   The env name.
   */
  private function setActiveEnvironmentName(string $env) : void {
    $config = $this->config('helfi_api_base.environment_resolver.settings');
    $config
      ->set(EnvironmentResolver::ENVIRONMENT_NAME_KEY, $env)
      ->save();
    $this->container->get('kernel')->rebuildContainer();
  }

  /**
   * Tests environment url configuration.
   */
  public function testEndpoints() : void {
    // Endpoint should default to staging environment.
    $this->assertEndpoint(Tunnistamo::STAGING_ENVIRONMENT);
    // Make sure if we remove the environment_url setting altogether the
    // default value still fallbacks to testing env.
    $config = $this->getPluginConfiguration();
    $settings = $config->get('settings');
    unset($settings['environment_url']);
    $config->set('settings', $settings)->save();

    $this->assertEndpoint(Tunnistamo::STAGING_ENVIRONMENT);

    // Endpoint should default to whatever we set as base url in
    // 'environment_url'.
    $this->setPluginConfiguration('environment_url', 'https://example.com');
    $this->assertEndpoint('https://example.com');
  }

  /**
   * Tests environment auto-detection.
   */
  public function testEndpointAutodetect() : void {
    $envs = [
      'dev' => Tunnistamo::TESTING_ENVIRONMENT,
      'test' => Tunnistamo::TESTING_ENVIRONMENT,
      'stage' => Tunnistamo::STAGING_ENVIRONMENT,
      'prod' => Tunnistamo::PRODUCTION_ENVIRONMENT,
    ];
    foreach ($envs as $env => $url) {
      $this->setActiveEnvironmentName($env);
      $this->assertEndpoint($url);
    }
  }

}
