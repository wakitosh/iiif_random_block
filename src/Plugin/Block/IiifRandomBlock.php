<?php

namespace Drupal\iiif_random_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides an 'IIIF Random Image' Block.
 *
 * @Block(
 * id = "iiif_random_block",
 * admin_label = @Translation("IIIF Random Image Block"),
 * category = @Translation("Custom"),
 * )
 */
class IiifRandomBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected $database;
  protected $configFactory;

  /**
   * Constructs a new IiifRandomBlock instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $items = [];
    try {
      $query = $this->database->select('iiif_display_images', 'd')
        ->fields('d', ['image_url', 'manifest_url', 'related_url', 'label']);
      $items = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }
    catch (\Exception $e) {
      \Drupal::logger('iiif_random_block')->error('Could not fetch display images from database: @message', ['@message' => $e->getMessage()]);
    }

    $config = $this->configFactory->get('iiif_random_block.settings');

    $build['content'] = [
      '#theme' => 'iiif_random_block',
      '#items' => $items,
      '#source_link_text' => $config->get('source_link_text'),
      '#source_link' => $config->get('source_link_url'),
    ];

    // Attach the carousel library and settings.
    $duration = $config->get('carousel_duration') ?: 10;
    $build['#attached']['library'][] = 'iiif_random_block/carousel';
    $build['#attached']['drupalSettings']['iiif_random_block']['carousel']['duration'] = $duration * 1000;

    // Do not cache the block render array.
    $build['#cache']['max-age'] = 0;

    return $build;
  }

}
