<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo;

/**
 * A set of OpenID Connect tokens.
 */
final readonly class Token {

  public function __construct(
    #[\SensitiveParameter] public string $accessToken,
    #[\SensitiveParameter] public string $idToken,
    #[\SensitiveParameter] public string $refreshToken,
    public int $expire,
  ) {
  }

  /**
   * Creates a token set from a token endpoint response.
   *
   * @param \stdClass $data
   *   The decoded response body.
   * @param int $requestTime
   *   Request time, to turn the relative expiration into a
   *   timestamp.
   *
   * @throws \UnexpectedValueException
   */
  public static function createFromResponse(\stdClass $data, int $requestTime) : self {
    if (!isset($data->expires_in) || !is_numeric($data->expires_in)) {
      throw new \UnexpectedValueException('The token endpoint response did not contain expires_in');
    }

    return new self(
      self::requireString($data, 'access_token'),
      self::requireString($data, 'id_token'),
      self::requireString($data, 'refresh_token'),
      $requestTime + (int) $data->expires_in,
    );
  }

  /**
   * Creates a token set from its stored representation.
   *
   * @param mixed $data
   *   The value that was stored, if any.
   *
   * @return self|null
   *   The tokens, or NULL if the value does not hold a usable set.
   */
  public static function createFromArray(mixed $data) : ?self {
    if (
      !is_array($data) ||
      !is_string($data['access_token'] ?? NULL) ||
      !is_string($data['id_token'] ?? NULL) ||
      !is_string($data['refresh_token'] ?? NULL) ||
      !is_int($data['expire'] ?? NULL)
    ) {
      return NULL;
    }

    return new self(
      $data['access_token'],
      $data['id_token'],
      $data['refresh_token'],
      $data['expire'],
    );
  }

  /**
   * Gets the storable representation of the tokens.
   *
   * @phpstan-return array{
   *   access_token: string,
   *   id_token: string,
   *   refresh_token: string,
   *   expire: int,
   * }
   */
  public function toArray() : array {
    return [
      'access_token' => $this->accessToken,
      'id_token' => $this->idToken,
      'refresh_token' => $this->refreshToken,
      'expire' => $this->expire,
    ];
  }

  /**
   * Reads a required string from a token endpoint response.
   *
   * @throws \UnexpectedValueException
   */
  private static function requireString(\stdClass $data, string $key) : string {
    if (!isset($data->{$key}) || !is_string($data->{$key})) {
      throw new \UnexpectedValueException("The token endpoint response did not contain $key");
    }

    return $data->{$key};
  }

}
