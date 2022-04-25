<?php

declare(strict_types = 1);

namespace Drupal\helfi_tunnistamo\Plugin\DebugDataItem;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\helfi_api_base\Annotation\DebugDataItem;
use Drupal\helfi_api_base\DebugDataItemPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the debug_data_item.
 *
 * @DebugDataItem(
 *   id = "tunnistamo",
 *   label = @Translation("Tunnistamo"),
 *   description = @Translation("Tunnistamo")
 * )
 */
class Tunnistamo extends DebugDataItemPluginBase implements ContainerFactoryPluginInterface {
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $data = [
      'TUNNISTAMO_CLIENT_ID' => FALSE,
      'TUNNISTAMO_CLIENT_SECRET' => FALSE,
    ];

    foreach ($data as $key => $value) {
      $data[$key] = (bool)getenv($key);
    }

    return $data;
  }
}
