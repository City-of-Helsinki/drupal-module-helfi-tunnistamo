<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\helfi_tunnistamo\Hook\MenuHooks;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests MenuHooks.
 */
#[Group('helfi_tunnistamo')]
#[RunTestsInSeparateProcesses]
class MenuHooksTest extends KernelTestBase {

  use ProphecyTrait;

  /**
   * Tests that TFA tab is removed for SSO users.
   */
  public function testTfaTabRemovedForSsoUser(): void {
    $user = $this->createUser();
    $this->createAuthmapEntry($user);

    $data = [
      'tabs' => [
        0 => [
          'tfa.overview' => ['#markup' => 'TFA'],
          'entity.user.canonical' => ['#markup' => 'View'],
        ],
      ],
    ];
    $cacheability = new CacheableMetadata();

    $this->getSut($user)->alterMenu($data, 'entity.user.canonical', $cacheability);

    $this->assertArrayNotHasKey('tfa.overview', $data['tabs'][0]);
    $this->assertArrayHasKey('entity.user.canonical', $data['tabs'][0]);
  }

  /**
   * Tests that TFA tab is preserved for non-SSO users.
   */
  public function testTfaTabPreservedForNonSsoUser(): void {
    $user = $this->createUser();

    $data = [
      'tabs' => [
        0 => [
          'tfa.overview' => ['#markup' => 'TFA'],
        ],
      ],
    ];
    $cacheability = new CacheableMetadata();

    $this->getSut($user)->alterMenu($data, 'entity.user.canonical', $cacheability);

    $this->assertArrayHasKey('tfa.overview', $data['tabs'][0]);
  }

  /**
   * Creates a user entity.
   */
  private function createUser(): User {
    $user = User::create([
      'name' => 'testuser',
      'mail' => 'test@example.com',
    ]);
    $user->save();
    return $user;
  }

  /**
   * Creates an authmap entry for the given user.
   */
  private function createAuthmapEntry(User $user): void {
    $this->container->get('database')
      ->insert('authmap')
      ->fields([
        'uid' => $user->id(),
        'provider' => 'openid_connect.tunnistamo',
        'authname' => 'test-authname',
        'data' => serialize([]),
      ])
      ->execute();
  }

  /**
   * Builds the service under test with a mocked route match.
   */
  private function getSut(User $routeUser): MenuHooks {
    $routeMatch = $this->prophesize(RouteMatchInterface::class);
    $routeMatch->getParameter('user')->willReturn($routeUser);

    return new MenuHooks(
      $routeMatch->reveal(),
      $this->container->get('database'),
    );
  }

}
