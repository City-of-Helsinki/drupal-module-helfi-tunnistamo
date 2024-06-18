<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\helfi_api_base\Event\PostDeployEvent;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;

/**
 * Tests disable local users subscriber.
 *
 * @group helfi_tunnistamo
 */
class DisableExternalUsersPasswordSubscriberTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * Tests that deploy prevents external users from logging in with password.
   */
  public function testSubscriber() : void {
    /** @var \Drupal\externalauth\Authmap $authmap */
    $authmap = $this->container->get('externalauth.authmap');
    $external = $this->createUser(values: ['pass' => '123']);
    $local = $this->createUser(values: ['pass' => '123']);

    // Add external auth to user.
    $authmap->save($external, 'test_provider', 'test_authname');

    // User login is enabled.
    $this->assertNotEmpty($external->getPassword());
    $this->assertNotEmpty($local->getPassword());

    $this->triggerEvent();

    // User password is disabled.
    $this->assertEmpty(User::load($external->id())->getPassword());
    $this->assertNotEmpty(User::load($local->id())->getPassword());
  }

  /**
   * Triggers the post deploy event.
   */
  private function triggerEvent() : void {
    /** @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $service */
    $service = $this->container->get('event_dispatcher');
    $service->dispatch(new PostDeployEvent());
  }

}
