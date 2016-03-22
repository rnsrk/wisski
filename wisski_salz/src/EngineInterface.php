<?php

/**
 * @file
 * Contains Drupal\wisski_salz\EngineInterface.
 */

namespace Drupal\wisski_salz;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\wisski_salz\ExternalEntityInterface;
use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines an interface for external entity storage client plugins.
 */
interface EngineInterface extends PluginInspectionInterface, ConfigurablePluginInterface, PluginFormInterface  {
  
  public function load($uri);
  
  public function loadMultiple($uris = NULL);
}
