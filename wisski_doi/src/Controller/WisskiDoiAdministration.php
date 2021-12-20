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
  public function overview($wisski_individual) {
    $wisski_individual = intval($wisski_individual);

    $rows = (new WisskiDoiDbController)->readDoiRecords($wisski_individual) ?? NULL;
    if ($rows) {
      $build['table'] = [
        '#type' => 'table',
        '#header' => array_keys($rows[0]),
        '#rows' => $rows,
        '#description' => $this->t('DOI information'),
        '#weight' => 1,
      ];
    }
    else {
      $build = [
        '#markup' => '<p>' . $this->t('No DOIs associated with the entity.') . '</p>',
      ];
    }
    return $build;
  }

}
