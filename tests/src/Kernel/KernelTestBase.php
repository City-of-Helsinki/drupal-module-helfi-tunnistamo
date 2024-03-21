<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\Core\Config\Config;
use Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo;
use Drupal\KernelTests\KernelTestBase as CoreKernelTestBase;
use Drupal\openid_connect\Entity\OpenIDConnectClientEntity;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kernel test base for tunnistamo.
 */
abstract class KernelTestBase extends CoreKernelTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'helfi_api_base',
    'helfi_tunnistamo',
    'externalauth',
    'file',
    'openid_connect',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('helfi_tunnistamo');
    $this->installEntitySchema('action');
    $this->installEntitySchema('user');

  }

  /**
   * Mocks the endpoint response.
   *
   * @param string|null $environmentUrl
   *   The environment url.
   * @param string|null $authorization
   *   The authorization endpoint.
   * @param string|null $token
   *   The token endpoint.
   * @param string|null $userinfo
   *   The userinfo endpoint.
   * @param string|null $endSession
   *   The end session endpoint.
   *
   * @throws \Exception
   */
  protected function setupEndpoints(
    ?string $environmentUrl = 'https://localhost',
    ?string $authorization = 'https://localhost/authorization',
    ?string $token = 'https://localhost/token',
    ?string $userinfo = 'https://localhost/userinfo',
    ?string $endSession = 'https://localhost/endsession'
  ) : void {
    $this->container->get('kernel')->rebuildContainer();
    $this->setPluginConfiguration('environment_url', $environmentUrl);
    $this->setupMockHttpClient([
      new GuzzleResponse(body: json_encode([
        'authorization_endpoint' => $authorization,
        'token_endpoint' => $token,
        'userinfo_endpoint' => $userinfo,
        'end_session_endpoint' => $endSession,
      ])),
    ]);
  }

  /**
   * Gets the tunnistamo plugin.
   *
   * @return \Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo
   *   The tunnistamo client.
   */
  protected function getPlugin() : Tunnistamo {
    $plugin = OpenIDConnectClientEntity::load('tunnistamo')->getPlugin();
    assert($plugin instanceof Tunnistamo);

    return $plugin;
  }

  /**
   * Gets the client configuration.
   *
   * @return \Drupal\Core\Config\Config
   *   The configuration.
   */
  protected function getPluginConfiguration() : Config {
    return $this->config('openid_connect.client.tunnistamo');
  }

  /**
   * Sets a value for given configuration.
   *
   * @param string $key
   *   The key to set.
   * @param mixed $value
   *   The value to set.
   */
  protected function setPluginConfiguration(string $key, mixed $value) : void {
    $settings = $this->getPluginConfiguration()->get('settings');
    $settings[$key] = $value;
    $this->getPluginConfiguration()->set('settings', $settings)->save();
  }

  /**
   * Run given response through the http kernel.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The handled response.
   */
  protected function getHttpKernelResponse(Request $request) : Response {
    $http_kernel = $this->container->get('http_kernel');
    return $http_kernel->handle($request);
  }

}
