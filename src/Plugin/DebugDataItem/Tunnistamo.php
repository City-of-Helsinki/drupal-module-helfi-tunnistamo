<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo\Plugin\DebugDataItem;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Attribute\DebugDataItem;
use Drupal\helfi_api_base\DebugDataItemPluginBase;
use Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient\Tunnistamo as TunnistamoClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the debug_data_item.
 */
#[DebugDataItem(
  id: 'tunnistamo',
  title: new TranslatableMarkup('Tunnistamo'),
)]
final class Tunnistamo extends DebugDataItemPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : self {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get(EntityTypeManagerInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $data = [];

    $storage = $this->entityTypeManager->getStorage('openid_connect_client');

    /** @var \Drupal\openid_connect\OpenIDConnectClientEntityInterface $client */
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
