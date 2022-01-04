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
   *   WisskiDoiConfirmFormRequestDoiForStaticRevision.
   *   The revision ID as vid.
   *   The entity ID as eid.
   *   The state of the DOI (draft, registered, findable).
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
        'state' => $dbData['state'],
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
        'state',
        'revisionUrl',
        'isCurrent',
      ])
      ->condition('eid', $eid, '=');

    if ($did) {
      $query = $query->condition('did', $did, '=');
    }
    $result = $query->orderBy('did', 'DESC')->execute()->fetchAll();
    return array_map(function ($record) {
      return json_decode(json_encode($record), TRUE);
    }, $result);
  }

  /**
   * Delete the DOI record.
   *
   * We parse the strClass $records to an array with the
   * json_decode/json_encode() functions. More transitions in
   * WisskiDoiAdministration::rowBuilder().
   *
   * @param int|null $did
   *   The internal DOI id.
   *
   * @return array
   *   Dataset of corresponding DOIs to an entity.
   */
  public function deleteDoiRecord(int $did = NULL) {
    $result = $this->connection->delete('wisski_doi')->condition('did', $did)->execute();
    $this->messenger()
      ->addStatus($this->t('Deleted DOI record from DB.'));
    return $result;
  }

  /**
   * Update the DOI record.
   *
   * @param string $state
   *   The internal DOI id.
   * @param int|null $did
   *   The internal DOI id.
   *
   * @return array
   *   Dataset of corresponding DOIs to an entity.
   */
  public function updateDbRecord(string $state, int $did = NULL) {
    if (!$did) {
      $this->messenger()
        ->addError($this->t('There is no did.'));
      return NULL;
    }
    $result = $this->connection->update('wisski_doi')
      ->fields([
        'state' => $state,
      ])->condition('did', $did)->execute();
    $this->messenger()
      ->addStatus($this->t('Updated DOI record from DB.'));
    return $result;
  }

}
