<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\helfi_tunnistamo\TokenManagerInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the token manager.
 */
#[Group('helfi_tunnistamo')]
#[RunTestsInSeparateProcesses]
class TokenManagerTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setPluginConfiguration('environment_url', 'https://localhost');
  }

  /**
   * Gets the token manager.
   */
  private function sut(): TokenManagerInterface {
    return $this->container->get(TokenManagerInterface::class);
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
   * Tests that the connect operation saves the new tokens to the session.
   */
  public function testConnectSavesTokens(): void {
    $this->setupSession([
      'access' => 'expired-access-token',
      'refresh' => 'spent-refresh-token',
    ]);
    $this->setUpCurrentUser();
    $this->linkCurrentUser('123');

    $this->assertTrue($this->connectAs('123', [
      'access_token' => 'new-access-token',
      'id_token' => 'new-id-token',
      'refresh_token' => 'new-refresh-token',
      'expire' => $this->now() + 300,
    ]));
    $this->assertTokens([
      'access' => 'new-access-token',
      'id' => 'new-id-token',
      'refresh' => 'new-refresh-token',
      'expire' => $this->now() + 300,
    ]);
    $this->assertSame('123', $this->getLinkedSub());
  }

  /**
   * Tests that connecting as a different identity is denied.
   */
  public function testConnectAsDifferentIdentityIsDenied(): void {
    $this->setupSession([
      'access' => 'expired-access-token',
      'refresh' => 'spent-refresh-token',
    ]);
    $this->setUpCurrentUser();
    $this->linkCurrentUser('123');

    $this->assertFalse($this->connectAs('456', [
      'access_token' => 'other-access-token',
      'id_token' => 'other-id-token',
      'refresh_token' => 'other-refresh-token',
      'expire' => $this->now() + 300,
    ]));

    // The account link and the session were left as they were.
    $this->assertSame('123', $this->getLinkedSub());
    $this->assertTokens([
      'access' => 'expired-access-token',
      'id' => NULL,
      'refresh' => 'spent-refresh-token',
      'expire' => NULL,
    ]);
  }

}
