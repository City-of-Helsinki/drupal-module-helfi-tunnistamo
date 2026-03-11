<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo\Event;

use Drupal\Core\Url;
use Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Allow redirect url to be altered.
 */
final class RedirectUrlEvent extends Event {

  /**
   * Gets the redirect url.
   *
   * @param \Drupal\Core\Url $redirectUrl
   *   The redirect url.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo $client
   *   The Tunnistamo client.
   */
  public function __construct(
    private Url $redirectUrl,
    private Request $request,
    private Tunnistamo $client,
  ) {
  }

  /**
   * Gets the Tunnistamo client.
   *
   * @return \Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo
   *   The Tunnistamo client.
   */
  public function getClient() : Tunnistamo {
    return $this->client;
  }

  /**
   * Gets the current request.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  public function getRequest() : Request {
    return $this->request;
  }

  /**
   * Gets the redirect url.
   *
   * @return \Drupal\Core\Url
   *   The redirect url.
   */
  public function getRedirectUrl(): Url {
    return $this->redirectUrl;
  }

  /**
   * Sets the redirect url.
   *
   * @param \Drupal\Core\Url $redirectUrl
   *   The redirect url.
   *
   * @return $this
   *   The self.
   */
  public function setRedirectUrl(Url $redirectUrl) : self {
    $this->redirectUrl = $redirectUrl;
    return $this;
  }

}
