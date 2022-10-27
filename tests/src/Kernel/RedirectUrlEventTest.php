<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\helfi_tunnistamo\Event\RedirectUrlEvent;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Tests redirect url override.
 *
 * @group helfi_tunnistamo
 */
class RedirectUrlEventTest extends KernelTestBase implements EventSubscriberInterface {

  /**
   * Track caught events in a property for testing.
   *
   * @var array
   */
  private array $caughtEvents = [];

  /**
   * Catch events.
   *
   * @param \Drupal\helfi_tunnistamo\Event\RedirectUrlEvent $event
   *   The event.
   */
  public function alterUrl(RedirectUrlEvent $event): void {
    $event->setRedirectUrl(Url::fromUserInput('/fi/user/test')->setAbsolute());
    $this->caughtEvents[] = $event;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    return [
      RedirectUrlEvent::class => [
        ['alterUrl'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container
      ->register('testing.redirect_url_event', self::class)
      ->addTag('event_subscriber');
    $container->set('testing.redirect_url_event', $this);
  }

  /**
   * Make sure authorization redirect_uri can be altered.
   */
  public function testAlterAuthorizeUrl() : void {
    self::assertCount(0, $this->caughtEvents);
    $client = $this->getPlugin();
    $response = $client->authorize();
    $this->assertInstanceOf(TrustedRedirectResponse::class, $response);
    parse_str(parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);

    $this->assertStringEndsWith('/fi/user/test', $query['redirect_uri']);

    self::assertCount(1, $this->caughtEvents);
  }

}
