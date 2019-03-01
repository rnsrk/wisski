<?php

namespace Drupal\wisski_core\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 *
 */
class wisski_coreController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function content() {
    $build = [
      '#type' => 'markup',
      '#markup' => t('Hello World!!!'),
    ];
    return $build;
  }

}
