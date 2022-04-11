<?php

namespace Drupal\wisski_core;

use Drupal\Core\Database\Connection;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Service for database CRUD actions.
 */
interface WisskiIndividualListDbActionsInterface {

  /**
   * {@inheritDoc}
   */
  public function __construct(Connection $connection, MessengerInterface $messenger, TranslationInterface $stringTranslation);
  
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
  public function readBundleRecords(string $bundle_id);

  public function deleteBundleRecords();

}
