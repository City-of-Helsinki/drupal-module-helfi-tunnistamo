<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\helfi_tunnistamo\Session\Configuration;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests session configuration.
 *
 * @group helfi_tunnistamo
 */
class SessionConfigurationTest extends KernelTestBase {

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|null
   */
  protected ?RequestStack $requestStack;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_tunnistamo',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() : void {
    parent::setUp();

    $this->requestStack = $this->container->get('request_stack');
  }

  /**
   * Gets the SUT.
   *
   * @return \Drupal\Core\Session\SessionConfigurationInterface
   *   The SUT.
   */
  private function getSut() : SessionConfigurationInterface {
    return $this->container->get('session_configuration');
  }

  /**
   * Tests session suffix from configuration.
   */
  public function testSessionNameConfig() : void {
    $options = $this->getSut()->getOptions($this->requestStack->getCurrentRequest());
    $this->assertEquals(Configuration::COOKIE_LIFETIME, $options['cookie_lifetime']);
  }

}
