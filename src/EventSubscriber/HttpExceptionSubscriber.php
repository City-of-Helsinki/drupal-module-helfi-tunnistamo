<?php

declare(strict_types = 1);

namespace Drupal\helfi_tunnistamo\EventSubscriber;

use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo;
use Drupal\openid_connect\Entity\OpenIDConnectClientEntity;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Exception subscriber for handling HTML error pages.
 */
final class HttpExceptionSubscriber extends HttpExceptionSubscriberBase {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $accountProxy
   *   The account proxy.
   */
  public function __construct(private AccountProxyInterface $accountProxy) {
  }

  /**
   * {@inheritdoc}
   */
  protected static function getPriority() {
    return 200;
  }

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats() {
    return ['html'];
  }

  /**
   * Handles a 403 error for html.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to respond to.
   */
  public function on403(ExceptionEvent $event) : void {
    // Nothing to do if user is authenticated already.
    if ($this->accountProxy->isAuthenticated()) {
      return;
    }
    /** @var \Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo $client */
    $client = OpenIDConnectClientEntity::load('tunnistamo')?->getPlugin();

    if (!$client instanceof Tunnistamo) {
      return;
    }
    $client->setSilentAuthentication();
    $response = $client->authorize(implode(' ', $client->getClientScopes()));

    $event->setResponse($response);
  }

}
