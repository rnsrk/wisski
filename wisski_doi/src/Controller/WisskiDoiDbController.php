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
   * @param string $doi
   *   The DOI from the response of the DOI provider. Implemented at
   *   WisskiDoiRestController::getDraftDoi, invoked from
   *   WisskiRequestDoiConfirmForm.
   * @param int $revisionId
   *   The revision ID, in Drupal called "vid".
   */
  public function writeToDb(string $doi, int $revisionId) {
    dpm($doi);
    dpm($revisionId);

    $result = $this->connection->insert('wisski_doi')
      ->fields([
        'doi' => $doi,
        'vid' => $revisionId,
      ])
      ->execute();
  }

}
