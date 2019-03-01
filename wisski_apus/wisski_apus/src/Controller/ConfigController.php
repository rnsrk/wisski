<?php

namespace Drupal\wisski_apus\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 *
 */
class ConfigController extends ControllerBase {

  /**
   *
   */
  public function overview() {
    return [
      '#type' => 'markup',
      '#markup' => $this->t("Configure WissKI's Content and Annotation Processing"),
    ];
  }

  /**
   *
   */
  public function dummy() {
    return [
      '#type' => 'markup',
      '#markup' => $this->t("bla blubb"),
    ];
  }

}
