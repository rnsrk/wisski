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
   *   WisskiConfirmFormDoiRequestForStaticRevision.
   *   The revision ID as vid.
   *   The entity ID as eid.
   *   The type of the DOI (draft, registered, findable).
   *
   * @return Null
   *   Query execution return nothing.
   *
   * @throws \Exception
   */
  public function writeToDb(array $dbData) {
    return $this->connection->insert('wisski_doi')
      ->fields([
        'eid' => $dbData['eid'],
        'doi' => $dbData['doi'],
        'vid' => $dbData['vid'] ?? NULL,
        'type' => $dbData['type'],
        'revisionUrl' => $dbData['revisionUrl'],
        'isCurrent' => empty($dbData['vid']) ? 1 : 0,
      ])
      ->execute();
  }

  /**
   * Select the records corresponding to an entity.
   *
   * We parse the strClass $records to an array with the
   * json_decode/json_encode() functions. More transitions in
   * WisskiDoiAdministration::rowBuilder().
   *
   * @param int $eid
   *   The entity id.
   *
   * @return array
   *   Dataset of corresponding DOIs to an entity.
   */
  public function readDoiRecords(int $eid, int $did = NULL) {
    $query = $this->connection
      ->select('wisski_doi')
      ->fields('wisski_doi', [
        'did',
        'eid',
        'doi',
        'vid',
        'type',
        'revisionUrl',
        'isCurrent',
      ])
      ->condition('eid', $eid, '=');

    if ($did) {
      $query = $query->condition('did', $did, '=')->execute();
    }
    else {
      $query = $query->execute();
    }
    $result = $query->fetchAll();
    return array_map(function ($record) {
      return json_decode(json_encode($record), TRUE);
    }, $result);
  }

}
