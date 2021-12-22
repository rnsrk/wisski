<?php

namespace Drupal\wisski_doi\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\wisski_core\WisskiEntityInterface;
use Drupal\wisski_core\WisskiStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Controller to render DOI Administration.
 */
class WisskiDoiAdministration extends ControllerBase {

  /**
   * Returns a render-able array for the DOI administration page.
   *
   * @return array
   *   The render array of the connected DOIs from the DB table
   *   wisski_doi as a table.
   */
  public function overview($wisski_individual) {
    $wisski_individual = intval($wisski_individual);
    $rows = (new WisskiDoiDbController)->readDoiRecords($wisski_individual) ?? NULL;
    /*
     *  Looks if the revision is the current revision.
     */
    $rows = array_map(function ($row) {
      $row['vid'] ?? $row['vid'] = 'current';
      return $row;
    }, $rows);
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
