<?php

declare(strict_types = 1);

namespace Drupal\helfi_tunnistamo\Event;

use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Allow redirect url to be altered.
 */
final class RedirectUrlEvent extends Event {

  /**
   * The redirect url.
   *
   * @var \Drupal\Core\Url
   */
  private Url $redirectUrl;

  /**
   * The request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  private Request $request;

  /**
   * Gets the redirect url.
   *
   * @param \Drupal\Core\Url $redirectUrl
   *   The redirect url.
   */
  public function __construct(Url $redirectUrl, Request $request) {
    $this->redirectUrl = $redirectUrl;
    $this->request = $request;
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
