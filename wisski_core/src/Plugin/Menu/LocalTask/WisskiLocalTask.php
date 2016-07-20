<?php

namespace Drupal\wisski_core\Plugin\Menu\LocalTask;

use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\wisski_core\WisskiEntityInterface;

/**
 * Defines a local task plugin with a dynamic title.
 */
class WisskiLocalTask extends LocalTaskDefault {

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    
    $title = $this->pluginDefinition['title'];
    $request = \Drupal::request();
    if ($entity = $request->attributes->get('wisski_individual')) {
      if (is_object($entity) && method_exists($entity,'label'))
        $title .= ' '.$entity->label();
      elseif (is_string($entity)) $title .= ' '.$entity;
    }
    //dpm($this);
    return $title;
  }

}