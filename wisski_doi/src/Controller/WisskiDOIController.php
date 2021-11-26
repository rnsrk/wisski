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
   * Tab to choose bundles for DOI assignment and schema configuration.
   * @return string[]
   */
  public function doiBundleAdministration() {
    return [
      '#markup' => 'Bundle administration',
    ];
  }

  /**
   * Tab to request DOIs for selected bundles.
   * @return string[]
   */
  public function doiRequest() {
    return [
      '#markup' => 'Request dois',
    ];
  }
}
