<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\Annotation\WisskiSalzAdapterPlugin.
 */

namespace Drupal\wisski_salz\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an external entity storage client annotation object
 *
 * @see \Drupal\wisski_salz\WisskiSalzAdapterPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class WisskiSalzAdapterPlugin extends Plugin {
  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The name of the storage client.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $name;

}
