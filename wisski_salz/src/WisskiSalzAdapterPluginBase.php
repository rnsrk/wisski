<?php

/**
 * @file
 * Contains Drupal\wisski_salz\WisskiSalzAdapterPluginBase.
 */

namespace Drupal\wisski_salz;

use Drupal\Component\Plugin\PluginBase;

/**
 * Base class for external entity storage clients.
 */
abstract class WisskiSalzAdapterPluginBase extends PluginBase implements WisskiSalzAdapterPluginInterface {

  /**
   * The HTTP client to fetch the data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
#  protected $httpClient;

  /**
   * The decoder to decode the data.
   *
   * @var \Drupal\wisski_salz\ResponseDecoderFactoryInterface
   */
#  protected $decoder;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
#    $this->httpClient = $this->configuration['http_client'];
#    $this->decoder = $this->configuration['decoder'];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->pluginDefinition['name'];
  }

  /**
   * {@inheritdoc}
   */
#  public function getHttpHeaders() {
#    return isset($this->configuration['http_headers']) ? $this->configuration['http_headers'] : [];
#  }
}
