<?php

namespace Drupal\wisski_doi\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller to render DOI Administration
 */

class WisskiDoiAdministration extends ControllerBase {

  /**
   * Returns a render-able array for a test page.
   */
  public function overview() {
    $build = [
      '#markup' => $this->t('Hello World!'),
    ];
    return $build;
  }

}
