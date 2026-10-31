<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\Config;
use Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo;
use Drupal\KernelTests\KernelTestBase as CoreKernelTestBase;
use Drupal\openid_connect\Entity\OpenIDConnectClientEntity;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_tunnistamo\Time;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Kernel test base for tunnistamo.
 */
abstract class KernelTestBase extends CoreKernelTestBase {

  use ApiTestTrait;

  /**
   * The session the tokens live in.
   */
  protected Session $session;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'diff',
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
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('user');
    $this->installSchema('externalauth', ['authmap']);

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
    ?string $endSession = 'https://localhost/endsession',
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
   * Sets up the session and the identity provider.
   *
   * @param array<string, mixed> $tokens
   *   The tokens the session starts with.
   * @param array<mixed> $responses
   *   Responses to the token endpoint requests.
   * @param int $now
   *   Freeze time to this timestamp. Defaults to current time.
   */
  protected function setupSession(array $tokens = [], array $responses = [], ?int $now = NULL): void {
    // Set up instantiates the session, and openid_connect.session holds on to
    // the one it was built with. Rebuilding drops both, so the session set
    // below is the one the token manager ends up reading.
    $this->container->get('kernel')->rebuildContainer();

    $now = $now ?? $this->container->get(TimeInterface::class)->getRequestTime();
    $this->container->set('datetime.time', new Time($now));

    $client = $this->setupMockHttpClient(array_merge([
      new GuzzleResponse(body: (string) json_encode([
        'authorization_endpoint' => 'https://localhost/authorization',
        'token_endpoint' => 'https://localhost/token',
        'userinfo_endpoint' => 'https://localhost/userinfo',
        'end_session_endpoint' => 'https://localhost/endsession',
      ])),
    ], $responses));

    $this->container->set('http_client', $client);

    $this->session = new Session(new MockArraySessionStorage());
    $this->container->set('session', $this->session);
    $this->container->get('request_stack')->getCurrentRequest()->setSession($this->session);

    foreach ($tokens as $name => $value) {
      $this->session->set('openid_connect_' . $name, $value);
    }
  }

  /**
   * Gets the time the clock is frozen at.
   */
  protected function now(): int {
    return $this->container->get(TimeInterface::class)->getCurrentTime();
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
