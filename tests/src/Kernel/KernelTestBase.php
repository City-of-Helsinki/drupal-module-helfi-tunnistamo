<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\Core\Config\Config;
use Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo;
use Drupal\KernelTests\KernelTestBase as CoreKernelTestBase;
use Drupal\openid_connect\Entity\OpenIDConnectClientEntity;

/**
 * Kernel test base for tunnistamo.
 */
abstract class KernelTestBase extends CoreKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'helfi_tunnistamo',
    'externalauth',
    'file',
    'openid_connect',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('helfi_tunnistamo');
    $this->installEntitySchema('action');
    $this->installEntitySchema('user');
  }

  /**
   * Gets the tunnistamo plugin.
   *
   * @return \Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo
   *   The tunnistamo client.
   */
  protected function getPlugin() : Tunnistamo {
    return OpenIDConnectClientEntity::load('tunnistamo')->getPlugin();
  }

  /**
   * Gets the client configuration.
   *
   * @return \Drupal\Core\Config\Config
   *   The configuration.
   */
  protected function getPluginConfiguration() : Config {
    return $this->config('openid_connect.client.tunnistamo');
  }

  /**
   * Sets a value for given configuration.
   *
   * @param string $key
   *   The key to set.
   * @param mixed $value
   *   The value to set.
   */
  protected function setPluginConfiguration(string $key, mixed $value) : void {
    $settings = $this->getPluginConfiguration()->get('settings');
    $settings[$key] = $value;
    $this->getPluginConfiguration()->set('settings', $settings)->save();
  }

}
