<?php

declare(strict_types = 1);

namespace Drupal\helfi_tunnistamo\Plugin\DebugDataItem;

use Drupal\helfi_api_base\DebugDataItemPluginBase;
use Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo as TunnistamoClient;
use Drupal\openid_connect\Entity\OpenIDConnectClientEntity;

/**
 * Plugin implementation of the debug_data_item.
 *
 * @DebugDataItem(
 *   id = "tunnistamo",
 *   label = @Translation("Tunnistamo"),
 *   description = @Translation("Tunnistamo")
 * )
 */
final class Tunnistamo extends DebugDataItemPluginBase {

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $data = [];

    foreach (OpenIDConnectClientEntity::loadMultiple() as $client) {
      if (!$client->getPlugin() instanceof TunnistamoClient) {
        continue;
      }
      $configuration = $client->getPlugin()->getConfiguration();
      $status = 'Not configured';

      if (isset($configuration['client_id'], $configuration['client_secret'])) {
        $status = $configuration['client_id'];
      }

      $data[$client->getPluginId()] = $status;
    }

    return $data;
  }

}
