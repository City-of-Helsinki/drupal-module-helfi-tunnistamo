<?php

declare(strict_types = 1);

namespace Drupal\helfi_tunnistamo\Plugin\DebugDataItem;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\helfi_api_base\DebugDataItemPluginBase;
use Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo as TunnistamoClient;
use Drupal\openid_connect\Entity\OpenIDConnectClientEntity;
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
final class Tunnistamo extends DebugDataItemPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $data = [];

    $storage = $this->entityTypeManager->getStorage('openid_connect_client');

    foreach ($storage->loadMultiple() as $client) {
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
