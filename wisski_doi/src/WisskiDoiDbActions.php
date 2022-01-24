<?php

namespace Drupal\wisski_doi;

use Drupal\Core\Database\Connection;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for DB CRUD actions.
 */
class WisskiDoiDbActions {

  use StringTranslationTrait;

  /**
   * The query builder object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $connection;

  /**
   * The Drupal messenger service.
   *
   * @var mixed
   */
  private mixed $messenger;

  /**
   * Get services through dependency injection.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Establish database connection with query builder.
   */
  public function __construct(Connection $connection, TranslationInterface $stringTranslation) {
    $this->connection = $connection;
    $this->messenger = \Drupal::service('messenger');
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Write DOI data to DB.
   *
   * @param array $dbData
   *   Contains:
   *   eid: The entity ID as eid.
   *   doi: DOI string with prefix and suffix.
   *   vid: The revision ID as vid.
   *   state: The state of the DOI (draft, registered, findable).
   *   revisionUrl: Full external URL of the revision.
   *   isCurrent: 0|1.
   *
   * @return Null
   *   Query execution returns nothing.
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
        'created' => $dbData['created'],
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
   * @param int|null $did
   *   The internal DOI identifier from the wisski_doi table.
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
        'created',
      ])
      ->condition('eid', $eid, '=');

    if ($did) {
      $query = $query->condition('did', $did, '=');
    }
    $result = $query->orderBy('did', 'DESC')->execute()->fetchAll();

    // $result is stdClass Object, this returns an array of the results.
    return array_map(function ($record) {
      return json_decode(json_encode($record), TRUE);
    }, $result);
  }

  /**
   * Select the latest DOI corresponding to an entity.
   *
   * We parse the strClass $records to an array with the
   * json_decode/json_encode() functions.
   *
   * @param int $eid
   *   The entity id.
   * @param int $isCurrent
   *   If the DOI is for current revision.
   *
   * @return array
   *   Dataset of corresponding DOIs to an entity.
   */
  public function readLatestDoiRecords(int $eid, int $isCurrent) {
    $query = $this->connection
      ->select('wisski_doi')
      ->fields('wisski_doi', [
        'eid',
        'doi',
        'state',
        'isCurrent',
        'created',
      ])
      ->condition('eid', $eid, '=')
      ->condition('isCurrent', $isCurrent, '=');
    $result = $query->orderBy('created', 'DESC')->execute()->fetch();

    // $result is stdClass Object, this returns an array of the results.
    return json_decode(json_encode($result), TRUE);
  }

  /**
   * Delete the DOI record.
   *
   * @param int|null $did
   *   The internal DOI id.
   *
   * @return int
   *   Dataset of corresponding DOIs to an entity.
   */
  public function deleteDoiRecord(int $did = NULL) {
    $result = $this->connection->delete('wisski_doi')
      ->condition('did', $did)
      ->execute();
    $this->messenger->addStatus($this->t('Deleted DOI record from DB.'));
    return $result;
  }

  /**
   * Update the DOI record.
   *
   * @param string $state
   *   The internal DOI id.
   * @param int|null $did
   *   The internal DOI id.
   */
  public function updateDbRecord(string $state, int $did = NULL) {
    if (!$did) {
      $this->messenger->addError($this->t('There is no did.'));
      return NULL;
    }
    $this->messenger->addStatus($this->t('Updated DOI record from DB.'));
    return $this->connection->update('wisski_doi')
      ->fields([
        'state' => $state,
      ])->condition('did', $did)->execute();
  }

  /**
   * Select the individuals corresponding to a bundle.
   *
   * We parse the strClass $records to an array with the
   * json_decode/json_encode() functions.
   *
   * @param string $bundle_id
   *   The bundle id.
   *
   * @return array
   *   Dataset of corresponding DOIs to an entity.
   */
  public function readBundleRecords(string $bundle_id) {
    $individualsPerBundle = [];
    // Query all individuals.
    $wisskiIndividualQuery = \Drupal::entityQuery('wisski_individual')
      ->condition('bundle', [$bundle_id]);
    $wisskiIndividualResults = $wisskiIndividualQuery->execute();
    foreach ($wisskiIndividualResults as $result => $eid) {
      $wisskiIndividualDataQuery = $this->connection
        ->select('wisski_title_n_grams', 'wt')
        ->fields('wt', [
          'ngram',
        ])
        ->condition('ent_num', $eid, '=');
      $wisskiIndividualDataResult = $wisskiIndividualDataQuery->execute()
        ->fetch();
      $wisskiIndividualDataResult = json_decode(json_encode($wisskiIndividualDataResult), TRUE);
      $entityLink = \Drupal::request()->getSchemeAndHttpHost() . '/wisski/navigate/' . $eid . '/doi';
      $individualsPerBundle[$eid] = [
        'eid' => $eid,
        'label' => $wisskiIndividualDataResult['ngram'],
        'link' => ['data' => $this->t('<a href=":entityLink" class="wisski-entity-link">:entityLink</a>', [':entityLink' => $entityLink])],
      ];
    }
    return $individualsPerBundle;
  }

}
