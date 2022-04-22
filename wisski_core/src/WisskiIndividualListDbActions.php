<?php

namespace Drupal\wisski_core;

use Drupal\Core\Database\Connection;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\wisski_core\WisskiIndividualListDbActionsInterface;
use Drupal\wisski_core\Form\WisskiIndListForm;

/**
 * Service for database CRUD actions.
 */
class WisskiIndividualListDbActions implements WisskiIndividualListDbActionsInterface {
  use StringTranslationTrait;

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $connection;

  /**
   * The Drupal messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  private MessengerInterface $messenger;

  /**
   * {@inheritDoc}
   */
  public function __construct(Connection $connection, MessengerInterface $messenger, TranslationInterface $stringTranslation) {
    $this->connection = $connection;
    $this->messenger = $messenger;
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * {@inheritDoc}
   */
  public function readBundleRecords(string $bundle_id) {
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $individualsPerBundle = [];
    // Query all individuals.
    $wisskiIndividualQuery = \Drupal::entityQuery('wisski_individual')
      ->condition('bundle', [$bundle_id]);
    $wisskiIndividualResults = $wisskiIndividualQuery->execute();
    foreach ($wisskiIndividualResults as $result => $eid) {
      $title = wisski_core_generate_title($eid);
      $entityLink = \Drupal::request()->getSchemeAndHttpHost() . '/wisski/navigate/' . $eid;
      $individualsPerBundle[$eid] = [
        'eid' => $eid,
        'label' => $title[$language][0]['value'],
        'link' => ['data' => $this->t('<a href=":entityLink" class="wisski-entity-link">:entityLink</a>', [':entityLink' => $entityLink])],
      ];
    }
    return $individualsPerBundle;
  }

  public function deleteBundleRecords() {
    $individualList = \Drupal::configFactory()
      ->getEditable(WisskiIndListForm::SELECTED_INDIVIDUALS)->get('wisskiIndividuals');
      // dpm($individualList, 'deletelist');

      // create a map that contains only the selected items, i.e., the eids that 
      // should be deleted and all not selected eids are filtered
      $individualListAsMap = array_map(function($a){
           return $a;
        },array_filter($individualList));
     
      $entity_storage = \Drupal::entityTypeManager()->getStorage('wisski_individual');
      $entities = $entity_storage->loadMultiple($individualListAsMap);
      $entity_storage->delete($entities);
    }
  
}
