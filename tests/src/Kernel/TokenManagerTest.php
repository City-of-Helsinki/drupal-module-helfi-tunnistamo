<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\helfi_tunnistamo\TokenManagerInterface;
use Drupal\Tests\helfi_tunnistamo\Time;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the token manager.
 */
#[Group('helfi_tunnistamo')]
#[RunTestsInSeparateProcesses]
class TokenManagerTest extends KernelTestBase {

  /**
   * The session the tokens live in.
   */
  private Session $session;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setPluginConfiguration('environment_url', 'https://localhost');
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
  private function setupSession(array $tokens = [], array $responses = [], ?int $now = NULL): void {
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

    foreach ($tokens as $name => $value) {
      $this->session->set('openid_connect_' . $name, $value);
    }
  }

  /**
   * Gets the token manager.
   */
  private function sut(): TokenManagerInterface {
    return $this->container->get(TokenManagerInterface::class);
  }

  /**
   * Gets the time the clock is frozen at.
   */
  private function now(): int {
    return $this->container->get(TimeInterface::class)->getCurrentTime();
  }

  /**
   * Asserts the tokens the session holds.
   *
   * @param array<string, mixed> $tokens
   *   The expected tokens, keyed by name. A token expected to be NULL is one
   *   the session must not have.
   */
  private function assertTokens(array $tokens): void {
    foreach ($tokens as $name => $expected) {
      $this->assertSame(
        $expected,
        $this->session->get('openid_connect_' . $name),
        sprintf('The session holds an unexpected %s token.', $name),
      );
    }
  }

  /**
   * Tests that a token that is still valid is returned as is.
   */
  public function testValidTokenIsNotRenewed(): void {
    $this->setupSession([
      'access' => 'access-token',
      'expire' => $this->now() + 3600,
    ]);

    $this->assertSame('access-token', $this->sut()->getAccessToken());

    // The session was left as it was.
    $this->assertTokens(['access' => 'access-token']);
  }

  /**
   * Tests that an expired token is renewed and the new tokens are saved.
   */
  public function testExpiredTokenIsRenewed(): void {
    $this->setupSession(
      tokens: [
        'access' => 'expired-access-token',
        'refresh' => 'refresh-token',
        'expire' => $this->now() - 10,
      ],
      responses: [
        new GuzzleResponse(body: (string) json_encode([
          'access_token' => 'new-access-token',
          'id_token' => 'new-id-token',
          'refresh_token' => 'new-refresh-token',
          'expires_in' => 300,
        ])),
      ],
    );

    $this->assertSame('new-access-token', $this->sut()->getAccessToken());

    // Everything the identity provider returned must be saved: the refresh
    // token is rotated on use.
    $this->assertTokens([
      'access' => 'new-access-token',
      'id' => 'new-id-token',
      'refresh' => 'new-refresh-token',
      'expire' => $this->now() + 300,
    ]);
  }

}
