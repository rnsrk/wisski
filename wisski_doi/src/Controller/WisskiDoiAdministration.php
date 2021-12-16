<?php

namespace Drupal\wisski_doi\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller to render DOI Administration.
 */
class WisskiDoiAdministration extends ControllerBase {

  /**
   * Returns a render-able array for the DOI administration page.
   */
  public function overview() {
    $eid = 6;
    $rows = (new WisskiDoiDbController)->readDoiRecords($eid);
    dpm($rows);
    $build['table'] = [
      '#type' => 'table',
      '#header' => array_keys($rows[0]),
      '#rows' => $rows,
      '#description' => $this->t('DOI information'),
      '#weight' => 1,
    ];
    return $build;
  }

}
