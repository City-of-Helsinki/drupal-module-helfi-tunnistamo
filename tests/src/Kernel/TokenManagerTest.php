<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\helfi_tunnistamo\TokenManagerInterface;
use Drupal\openid_connect\OpenIDConnectClientEntityInterface;
use Drupal\openid_connect\Plugin\OpenIDConnectClientInterface;
use Drupal\Tests\helfi_tunnistamo\Time;
use Drupal\Tests\user\Traits\UserCreationTrait;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the token manager.
 */
#[Group('helfi_tunnistamo')]
#[RunTestsInSeparateProcesses]
class TokenManagerTest extends KernelTestBase {

  use ProphecyTrait;
  use UserCreationTrait;

  /**
   * The session the tokens live in.
   */
  private Session $session;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('openid_connect');
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

  /**
   * Tests that anonymous users can't reauthenticate.
   */
  public function testReauthenticateRequiresLogin(): void {
    $this->setupSession();

    $this->expectException(\LogicException::class);
    $this->sut()->reauthenticate();
  }

  /**
   * Tests that reauthenticating starts the connect operation.
   */
  public function testReauthenticate(): void {
    $this->setupSession();
    $account = $this->setUpCurrentUser();

    $response = $this->sut()->reauthenticate();

    $this->assertTrue($response->isRedirect());
    $this->assertStringStartsWith('https://localhost/authorization?', (string) $response->headers->get('Location'));
    $this->assertSame([
      'op' => 'connect',
      'uid' => (int) $account->id(),
    ], $this->container->get('openid_connect.session')->retrieveOp(FALSE));
  }

  /**
   * Tests that the connect operation saves the new tokens to the session.
   */
  public function testConnectSavesTokens(): void {
    $this->setupSession([
      'access' => 'expired-access-token',
      'refresh' => 'spent-refresh-token',
    ]);
    $this->setUpCurrentUser();

    $plugin = $this->prophesize(OpenIDConnectClientInterface::class);
    $plugin->usesUserInfo()->willReturn(TRUE);
    $plugin->retrieveUserInfo(Argument::cetera())->willReturn([
      'sub' => '123',
      'email' => 'user@example.com',
    ]);
    $client = $this->prophesize(OpenIDConnectClientEntityInterface::class);
    $client->id()->willReturn('tunnistamo');
    $client->getPlugin()->willReturn($plugin->reveal());

    $success = $this->container->get('openid_connect.openid_connect')
      ->connectCurrentUser($client->reveal(), [
        'access_token' => 'new-access-token',
        'id_token' => 'new-id-token',
        'refresh_token' => 'new-refresh-token',
        'expire' => $this->now() + 300,
      ]);

    $this->assertTrue($success);
    $this->assertTokens([
      'access' => 'new-access-token',
      'id' => 'new-id-token',
      'refresh' => 'new-refresh-token',
      'expire' => $this->now() + 300,
    ]);
  }

}
