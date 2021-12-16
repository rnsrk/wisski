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
    $records = (new WisskiDoiDbController)->readDoiRecords($eid);
    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Revision'),
        $this->t('Creation Date'),
        $this->t('DOI'),
        $this->t('Type'),
      ],
      '#rows' => [],
      '#description' => $this->t('DOI information'),
      '#weight' => 1,
    ];
    return $build;
  }

}
