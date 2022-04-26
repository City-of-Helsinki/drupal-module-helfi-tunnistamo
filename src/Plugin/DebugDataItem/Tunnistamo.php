<?php

declare(strict_types = 1);

namespace Drupal\helfi_tunnistamo\Plugin\DebugDataItem;

use Drupal\helfi_api_base\DebugDataItemPluginBase;

/**
 * Plugin implementation of the debug_data_item.
 *
 * @DebugDataItem(
 *   id = "tunnistamo",
 *   label = @Translation("Tunnistamo"),
 *   description = @Translation("Tunnistamo")
 * )
 */
class Tunnistamo extends DebugDataItemPluginBase {

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $data['TUNNISTAMO_CLIENT_ID'] = getenv('TUNNISTAMO_CLIENT_ID');
    $data['TUNNISTAMO_CLIENT_SECRET'] = getenv('TUNNISTAMO_CLIENT_SECRET')
      ? 'TRUE' : 'FALSE';

    return $data;
  }

}
