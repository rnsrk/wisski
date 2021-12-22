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

  /**
   * Dynamically declares route.
   */
  public function routes() {
    $routes = [];
    // Declares a single route under the name 'example.content'.
    // Returns an array of Route objects.
    $routes['wisski_individual.revision_get_doi_for_current'] = new Route(
    // Path to attach this route to:
      '/wisski/navigate/{wisski_individual}/revision/get-doi-for-current',
      // Route defaults:
      [
        '_form' => '\Drupal\wisski_doi\Form\WisskiConfirmFormDoiRequestForCurrentRevision',
        '_title' => 'Do you want to request a new DOI for the current revision?',
      ],
      // Route requirements:
      [
        '_permission' => 'administer wisski',
        'wisski_individual' => '\d+',
      ]
    );
    return $routes;
  }

}
