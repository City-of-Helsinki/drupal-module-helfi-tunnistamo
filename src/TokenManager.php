<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\openid_connect\OpenIDConnectAutoDiscover;
use Drupal\openid_connect\OpenIDConnectSessionInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides the OpenID Connect tokens of the current session.
 *
 * The openid_connect module saves the tokens it receives into the session
 * and never touches them again. By the time anything wants to use the access
 * token, it is most likely expired. This service handles renewal when a token
 * is needed.
 */
final class TokenManager implements TokenManagerInterface {

  /**
   * Configuration of the OpenID Connect client to renew tokens with.
   */
  private const string CLIENT_CONFIG = 'openid_connect.client.tunnistamo';

  /**
   * Where the result of a renewal is kept for the requests that lost the race.
   */
  private const string RESULT_STORE = 'helfi_tunnistamo.renewed_tokens';

  /**
   * Seconds before expiration at which a token is already considered expired.
   */
  private const int EXPIRY_MARGIN = 60;

  /**
   * How long a renewal result is kept, in seconds.
   */
  private const int RESULT_TTL = 120;

  /**
   * Seconds to hold and to wait for the renewal lock.
   */
  private const int LOCK_TIMEOUT = 15;

  public function __construct(
    private readonly OpenIDConnectSessionInterface $oidcSession,
    private readonly OpenIDConnectAutoDiscover $autoDiscover,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ClientInterface $httpClient,
    private readonly TimeInterface $time,
    private readonly KeyValueExpirableFactoryInterface $keyValueExpirableFactory,
    #[Autowire(service: 'lock')]
    private readonly LockBackendInterface $lock,
    #[Autowire(service: 'logger.channel.helfi_tunnistamo')]
    private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function hasSession() : bool {
    return (bool) $this->oidcSession->retrieveAccessToken();
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessToken() : ?string {
    if ($expire = $this->oidcSession->retrieveExpireToken()) {
      if ($expire - $this->time->getCurrentTime() >= self::EXPIRY_MARGIN) {
        return $this->oidcSession->retrieveAccessToken();
      }
    }

    return $this->renew()?->accessToken;
  }

  /**
   * Runs the refresh token grant and saves the new tokens to the session.
   *
   * @return \Drupal\helfi_tunnistamo\Token|null
   *   The new tokens, or NULL if they could not be renewed.
   */
  private function renew() : ?Token {
    $refreshToken = $this->oidcSession->retrieveRefreshToken();

    if (!$refreshToken) {
      $this->logger->warning('The access token has expired and the session has no refresh token to renew it with. The user has to log in again.');
      return NULL;
    }

    $token = $this->renewOnce($refreshToken);

    if ($token) {
      $this->persist($token);
    }

    return $token;
  }

  /**
   * Spends the refresh token, or takes the result of the request that did.
   *
   * A refresh token can only be spent once, so the lock makes one request
   * spend it and the result is kept under the token that was spent.
   *
   * @param string $refreshToken
   *   The refresh token to spend.
   *
   * @return \Drupal\helfi_tunnistamo\Token|null
   *   The new tokens, or NULL if nobody managed to renew them.
   */
  private function renewOnce(#[\SensitiveParameter] string $refreshToken) : ?Token {
    $store = $this->keyValueExpirableFactory->get(self::RESULT_STORE);
    $resultKey = hash('sha256', $refreshToken);
    $lockName = self::RESULT_STORE . ':' . $resultKey;

    if (!$this->lock->acquire($lockName, self::LOCK_TIMEOUT)) {
      $this->lock->wait($lockName, self::LOCK_TIMEOUT);

      if (!$token = Token::createFromArray($store->get($resultKey))) {
        $this->logger->warning('Another request is renewing the access token and did not leave a usable result.');
      }

      return $token;
    }

    try {
      // The winner may have finished between the expiry check and the lock.
      if ($token = Token::createFromArray($store->get($resultKey))) {
        return $token;
      }

      if ($token = $this->requestTokens($refreshToken)) {
        // Store in keyvalue under the old refresh token,
        // so concurrent requests can get the result.
        $store->setWithExpire($resultKey, $token->toArray(), self::RESULT_TTL);
      }

      return $token;
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /**
   * Saves a set of tokens to the session.
   */
  private function persist(Token $token) : void {
    $this->oidcSession->saveAccessToken($token->accessToken);
    $this->oidcSession->saveIdToken($token->idToken);
    $this->oidcSession->saveRefreshToken($token->refreshToken);
    $this->oidcSession->saveExpireToken($token->expire);
  }

  /**
   * Exchanges a refresh token for a new set of tokens.
   *
   * @param string $refreshToken
   *   The refresh token to spend.
   *
   * @return \Drupal\helfi_tunnistamo\Token|null
   *   The new tokens, or NULL if the grant did not go through.
   */
  private function requestTokens(#[\SensitiveParameter] string $refreshToken) : ?Token {
    $settings = $this->configFactory->get(self::CLIENT_CONFIG)
      ->get('settings');

    if (!is_array($settings) || empty($settings['environment_url'])) {
      $this->logger->error('The Tunnistamo client is not configured');
      return NULL;
    }

    $discovered = $this->autoDiscover->fetch(rtrim((string) $settings['environment_url'], '/') . '/');

    if (empty($discovered['token_endpoint']) || !is_string($discovered['token_endpoint'])) {
      return NULL;
    }

    try {
      $response = $this->httpClient->request('POST', $discovered['token_endpoint'], [
        'form_params' => [
          'grant_type' => 'refresh_token',
          'refresh_token' => $refreshToken,
          'client_id' => $settings['client_id'] ?? '',
          'client_secret' => $settings['client_secret'] ?? '',
        ],
        'headers' => [
          'Accept' => 'application/json',
        ],
      ]);

      $data = json_decode((string) $response->getBody(), flags: JSON_THROW_ON_ERROR);

      if (!$data instanceof \stdClass) {
        throw new \UnexpectedValueException('The token endpoint returned an unexpected response');
      }

      return Token::createFromResponse($data, $this->time->getCurrentTime());
    }
    catch (\Exception $e) {
      $message = $e->getMessage();

      if ($e instanceof RequestException && $e->hasResponse()) {
        $message .= ' Response: ' . $e->getResponse()->getBody()->getContents();
      }

      $this->logger->error('Could not renew tokens. @message', [
        '@message' => $message,
      ]);
    }

    return NULL;
  }

}
