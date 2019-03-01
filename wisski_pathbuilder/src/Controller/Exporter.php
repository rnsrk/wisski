<?php

namespace Drupal\wisski_pathbuilder\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 *
 */
class Exporter extends ControllerBase {

  /**
   *
   */
  public function exportPb($pb) {

    // Get the pb if we only have the id
    // this seems to be discontinued?
    /*
    if (!is_object($pb)) {
    $pb = WisskiPathbuilderEntity::load($pb);
    }

    $dependencies = [];
    foreach ($pb->getPbArray() $pid => $path_info) {
    $dependencies[] = $
    }
     */

  }

}
