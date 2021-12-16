<?php

namespace Drupal\wisski_doi\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for DB CRUD actions.
 */
class WisskiDoiDbController extends ControllerBase {

  /**
   * The query builder object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $connection;

  /**
   * Establish database connection with query builder.
   */
  public function __construct() {
    $this->connection = \Drupal::service('database');
  }

  /**
   * Write DOI data to DB.
   *
   * @param array $dbData
   *   Contains:
   *   The DOI from the response of the DOI provider. Implemented at
   *   WisskiDoiRestController::getDraftDoi, invoked from
   *   WisskiRequestDoiConfirmForm.
   *   The revision ID as vid.
   *   The entity ID as eid.
   *   The type of the DOI (draft, registered, findable).
   *
   * @throws \Exception
   */
  public function writeToDb(array $dbData) {
    $result = $this->connection->insert('wisski_doi')
      ->fields([
        'eid' => $dbData['eid'],
        'doi' => $dbData['doi'],
        'vid' => $dbData['vid'],
        'type' => $dbData['type'],
      ])
      ->execute();
  }

  /**
   * Select the records corresponding to a entity.
   *
   * @param int $eid
   *   The entity id.
   *
   * @return array
   *   Dataset of corresponding DOIs to an entity.
   */
  public function readDoiRecords(int $eid) {
    $query = $this->connection->query("SELECT * FROM wisski_doi WHERE eid = {eid}");
    $result = $query->fetchAll();
    dpm($result);
    return $result;
  }

}
