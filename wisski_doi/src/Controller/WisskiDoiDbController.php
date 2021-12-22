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
   * json_decode/json_encode() functions and drop the auto indent
   * primary key (did).
   *
   * @param int $eid
   *   The entity id.
   *
   * @return array
   *   Dataset of corresponding DOIs to an entity.
   */
  public function readDoiRecords(int $eid) {
    $query = $this->connection->query("SELECT * FROM wisski_doi WHERE eid = {$eid}");
    $result = $query->fetchAll();
    $rows = [];
    foreach ($result as $record) {
      $row = json_decode(json_encode($record), TRUE);
      $doiLink = 'https://doi.org/' . $row['doi'];
      $row['doi'] = ['data' => $this->t('<a href=":doiLink" class="wisski-doi-link">:doiLink</a>', [':doiLink' => $doiLink])];
      $row['revisionUrl'] = ['data' => $this->t('<a href=":revisionLink">:revisionLink</a>', [':revisionLink' => $row['revisionUrl']])];
      $row = array_splice($row, 2, 4);
      $rows[] = $row;
    }
    return $rows;
  }

}
