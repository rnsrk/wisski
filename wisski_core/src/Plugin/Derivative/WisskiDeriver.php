<?php

namespace Drupal\wisski_core\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Defines dynamic local tasks.
 */
class WisskiDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Implement dynamic logic to provide values for the same keys as in example.links.task.yml.
    $w = 0;
    foreach (
      array(
      'entity.wisski_individual.canonical' => 'View',
      'entity.wisski_individual.edit_form' => 'Edit',
      'entity.wisski_individual.delete_form' => 'Delete',
      )
    as $route => $title) {
      $this->derivatives[$route] = $base_plugin_definition;
      $der = &$this->derivatives[$route];
      $der['base_route'] = 'entity.wisski_individual.canonical';
      $der['route_name'] = $route;
      $der['weight'] = ++$w;
      $der['title'] = $title;
      $der['class'] = '\Drupal\wisski_core\Plugin\Menu\LocalTask\WisskiLocalTask';
    }
    return $this->derivatives;
  }

}