<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\AdapterInterface.
 */

namespace Drupal\wisski_salz;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

/**
 * Provides an interface for defining WissKI Salz Adapter entities.
 */
interface AdapterInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface{
  // Add get/set methods for your configuration properties here.

}
