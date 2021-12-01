<?php

namespace Drupal\wisski_doi\Controller;

/**
 * Controller to render main admin page
 */

class WisskiDOIController {

  /**
   * Main tab for provider credentials.
   * @return string[]
   */
  public function doiRepositorySettings() {
    return [
      '#markup' => 'Repository settings',
    ];
  }

  /**
   * Tab to request DOIs for selected individual.
   * @return string[]
   */
  public function doiIndidualRequest() {
    dpm('doiIndividualRequest');
  }
}
