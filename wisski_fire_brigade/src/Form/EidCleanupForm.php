<?php

namespace Drupal\wisski_fire_brigade\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mysql\Driver\Database\mysql\Connection;
use Drupal\Core\Messenger\Messenger;

class EidCleanupForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection
   *
   * @var Drupal\mysql\Driver\Database\mysql\Connection
   */
  protected $database;


  /**
   * The Drupal messenger
   *
   * @var Drupal\Core\Messenger\Messenger
   */
  protected $messenger;



  /**
   * The constructor.
   */
  public function __construct(
      EntityTypeManagerInterface $entity_type_manager,
      Connection $database,
      Messenger $messenger,
      ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('entity_type.manager'),
        $container->get('database'),
        $container->get('messenger'),
        );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(){
    return self::class;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state){
    $multEids = $this->getMultipleEids();

    if(empty($multEids)){
      $this->messenger->addMessage(t("No URIs with multiple Entity IDs found!"));
      return $form;
    }

    // build the options array for formatted EIDs
    $options = [];
    foreach($multEids as $uri => $eids){
      $options[] = [
        'uri' => $uri,
        'eids' => implode(', ', $eids),
      ];
    }
    $form['selection'] = array(
        '#type' => 'radios',
        '#default_value' => 'lowest',
        '#options' => array(
          'lowest' => t("Keep lowest EID"),
          'highest' => t("Keep highest EID"),
          ),
        '#title' => t("Decide which EID to keep"),
        );
    $form['table'] = array(
        '#type' => 'tableselect',
        '#header' => array(
          'uri' => t('URI'),
          'eids' => t('EIDs'),
          ),
        '#options' => $options
        );
    $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Rename'),
        );

    $this->messenger->addWarning(t("Warning: Please do not proceed without backups of the triplestore and SQL database!"));
    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state){
    $storage = $this->entityTypeManager->getStorage('wisski_salz_adapter');
    $selection = $form_state->getValues()['selection'];

    foreach($storage->loadMultiple() as $machineName => $adapter){
      $engine = $adapter->getEngine();
      foreach($form['table']['#value'] as $i){
        $row = $form['table']['#options'][$i];
        $eids = explode(', ', $row['eids']);
        // remove the EID that should be kept
        if($selection == 'lowest'){
          unset($eids[array_search(min($eids), $eids)]);
        } else {
          unset($eids[array_search(max($eids), $eids)]);
        }
        // delete from triplestore
        $query = $this->deleteEidsQuery($row['uri'],$engine->getDefaultDataGraphUri(), $eids);
        $engine->directUpdate($query);
        // delete from database
        $this->deleteFromDatabase($row['uri'], $eids);
      }
    }
    return $form_state;
  }



  /**
   * Buils a query for deleting EIDs for a URI
   *
   * @param string $uri
   *  The URI
   * @param string $baseUri
   *  The base URI of the Graph
   * @param array $eids
   *  The EIDs that should be removed for the URI
   */
  function deleteEidsQuery(string $uri, string $baseUri, array $eids) : string {
    $buildDeleteStatement = function ($eid) use ($uri, $baseUri){
      return "<$uri> <{$baseUri}eid> $eid";
    };

    $query = "DELETE DATA {";
    $query .= implode(' . ', array_map($buildDeleteStatement, $eids));
    return $query . " }";
  }

  /**
   * Query to find URIs with multiple EIDs
   *
   * @return string 
   *  The Query
   */
  function multipleEidsQuery(string $baseUri) : string {
    return "SELECT ?s (GROUP_CONCAT( ?o; separator=',') AS ?eids) WHERE {
      ?s <{$baseUri}eid> ?o .
    } GROUP BY ?s
    having(COUNT(?s) > 1)";
  }

  /**
   * Deletes eids for a uri from the database
   *
   * @param string $uri
   *  The URI where eids should be deleted
   * @param array $eids
   *  The EIDs to be deleted
   */
  function deleteFromDatabase($uri, $eids){
    $queries = [];
    foreach($eids as $eid){
      $this->database->delete('wisski_salz_id2uri')
        ->condition('uri', $uri)
        ->condition('eid', $eid)
        ->execute();
    }
  }

  /**
   * Gets URIs that have multiple EIDs assigned.
   *
   * @return array
   *  Array of arrays containing URI and EIDs
   */
  function getMultipleEids() : array {

    // find multiple EIDs from triplestore
    $adapters = $this->entityTypeManager->getStorage('wisski_salz_adapter')->loadMultiple();
    foreach($adapters as $adapter){
      $engine = $adapter->getEngine();
      $baseUri = $engine->getDefaultDataGraphUri();

      $query = $this->multipleEidsQuery($baseUri);
      $results = $engine->directQuery($query);

      $multEids = [];
      foreach($results as $result){
        $uri = $result->s->getUri();
        $eids = explode(',', $result->eids->getValue());
        $multEids[$uri] = array_combine($eids, $eids);
      }
    }

    // find multiple EIDs in the wisski_salz_id2uri table
    $dbResults = $this->database->query('SELECT `uri`, GROUP_CONCAT(`eid`) AS `eids` FROM `wisski_salz_id2uri` GROUP BY `uri` HAVING COUNT(eid) > 1');
    foreach($dbResults as $row){
      $uri = $row->uri;
      $eids = explode(',', $row->eids);
      if(array_key_exists($uri, $multEids)){
        $multEids[$uri] += array_combine($eids, $eids);
      }
      else {
        $multEids[$uri] = array_combine($eids, $eids);
      }
    }
    return $multEids;

  }
}
