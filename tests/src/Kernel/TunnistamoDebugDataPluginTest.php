<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\openid_connect\Entity\OpenIDConnectClientEntity;

/**
 * Tests Tunnistamo debug data plugin.
 *
 * @coversDefaultClass \Drupal\helfi_tunnistamo\Plugin\DebugDataItem\Tunnistamo
 * @group helfi_tunnistamo
 */
class TunnistamoDebugDataPluginTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->installConfig(['openid_connect']);
    // Create a second client to confirm that only Tunnistamo clients
    // are processed.
    OpenIDConnectClientEntity::create([
      'id' => 'generic',
      'plugin' => 'generic',
    ])->save();
  }

  /**
   * @covers ::collect
   */
  public function testCollect() : void {
    /** @var \Drupal\helfi_api_base\DebugDataItemPluginManager $manager */
    $manager = $this->container->get('plugin.manager.debug_data_item');
    /** @var \Drupal\helfi_tunnistamo\Plugin\DebugDataItem\Tunnistamo $plugin */
    $plugin = $manager->createInstance('tunnistamo');
    $this->assertEquals(['tunnistamo' => 'placeholder'], $plugin->collect());

    // Make sure client is reported as not configured when required
    // configuration is not set.
    $this->setPluginConfiguration('client_id', NULL);
    $this->assertEquals(['tunnistamo' => 'Not configured'], $plugin->collect());
  }

}
