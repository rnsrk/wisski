<?php

namespace Drupal\wisski_fire_brigade\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mysql\Driver\Database\mysql\Connection;
use Drupal\Core\Messenger\Messenger;

use Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB;

class UriMigrationForm extends FormBase {

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



  /*
   * {@inheritdoc}
   */
  public function getFormId(){
    return self::class;
  }

  /*
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state){
    $this->messenger->deleteAll();
    $this->messenger->addWarning(t('Warning: Please do not proceed without backups of the triplestore and SQL database! '));

    $storage = $this->entityTypeManager->getStorage('wisski_salz_adapter');

    $form['table'] = array(
        '#type' => 'table',
        '#header' => [
        'checkbox' => t('Migrate'),
        'adapter' => t('Adapter'),
        'old_uris' => t('Old URIs (separate multiple by ",")'),
        'new_uri' => t('New URI'),
        ],
        );

    foreach($storage->loadMultiple() as $machineName => $adapter){
      $engine = $adapter->getEngine();
      if(!$engine instanceof Sparql11EngineWithPB){
        continue;
      }


      $row['checkbox'] =  array(
          '#type' => 'checkbox',
          '#default_value' => 0,
          );
      $row['adapter'] = array(
          '#type' => 'item',
          '#markup' => $adapter->toLink()->toString(),
          '#value' => $machineName
          );

      $row['old_uris'] = array(
          '#type' => 'textfield',
          );
      // add old default graph URI if set
      if(array_key_exists('default_graph', $engine->getConfiguration())){
        $row['old_uris']['#default_value'] = $adapter->getEngine()->getConfiguration()['default_graph'];
      }

      $row['new_uri'] = array(
          '#type' => 'textfield',
          );

      $form['table'][] = $row;

    }

    $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Migrate'),
        );

    return $form;

  }

  /*
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state){

    $config = [];

    foreach($form['table'] as $key => $row){
      if(!is_numeric($key))
        continue;

      $checked = $row['checkbox']['#checked'];
      if(!$checked)
        continue;

      $machineName = $row['adapter']['#value'];
      $oldUris = array_map("trim", explode(',',$row['old_uris']['#value']));
      $newUri = trim($row['new_uri']['#value']);
      if(!$newUri || empty($oldUris))
        continue;

      $config[$machineName] = [
        'old_uris' => $oldUris,
        'new_uri' => $newUri
      ];
    }

    $this->migrate($config);

    $form_state->setRebuild(true);
    // reset user input
    $input = $form_state->getUserInput();
    $input['table'] = array();
    $form_state->setUserInput($input);

    return $form_state;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state){
    $values = $form_state->getValues();
    $noSelection = true;
    foreach($values['table'] as $i => $row){
      if(!$row['checkbox']){
        continue;
      }

      $noSelection = false;
      $adapter = $row['adapter'];

      if(!$row['old_uris']){
       $form_state->setErrorByName($form['table'][$i]['old_uris'], t("No old URI provided for adapter $adapter! Please enter a valid old base URI."));
      }
      if(!$row['new_uri']) {
        $form_state->setError($form['table'][$i]['new_uri'], t("No new URI provided for adapter $adapter! Please enter a valid new base URI."));
      }
    }

    if($noSelection){
      $form_state->setErrorByName("table", t("No selection made! Make sure to check the adapters you want to migrate."));
    }


  }

  /*
   * Migrates a WissKI to a new URI.
   * This includes the triplestore as well as the SQL database.
   * MAKE SURE TO HAVE A TRIPLESTORE AND SQL DATABASE BACKUP BEFORE USING THIS.
   *
   * @param array $config
   *  A set of arrays specifying the adapters and how they should be migrated:
   *
   *  $adapter_machine_name => [
   *    'old_uris' => $oldUris,
   *    'new_uri => $newUri
   *  ]
   *  
   *  $adapter_machine_name string
   *    The machine name of the adapter.
   *  $oldUris string[]
   *    Array of old URI strings that should be migrated.
   *  $newUri
   *    The URI that should be migrated to.
   */
  public function migrate($config){
    $adapterIds = array_keys($config);
    $adapters = [];

    $storage = $this->entityTypeManager->getStorage('wisski_salz_adapter');

    foreach($adapterIds as $id){
      $adapter = $storage->load($id);
      if(!$adapter){
        $this->messenger->addWarning("Warning: Adapter $id not found!");
        continue;
      }

      $engine = $adapter->getEngine();
      if (!($engine instanceof Sparql11EngineWithPB)){
        $this->messenger->addWarning(t("Warning: Adapter $id does not feature a \Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB!"));
        continue;
      }

      // do the migrating
      $newUri = $config[$id]['new_uri'];
      $oldUris = $config[$id]['old_uris'];
      $this->migrateTriplestore($engine, $oldUris, $newUri);
      $this->migrateSQLDatabase($oldUris, $newUri);


      // update config in adapter
      $engineConfig = $engine->getConfiguration();
      $engineConfig['default_graph'] = $newUri;
      $adapter->setEngineConfig($engineConfig);
      $adapter->save();
    }
    drupal_flush_all_caches();
  }

  /*
   * Migrates a WissKI to a new URI.
   * Reads the migration details from a file.
   *
   * @see MigrationUtils::migrate($config)
   */
  public function migrateFromFile($file){
    $json = file_get_contents($file);
    $config = json_decode($json, true);
    $this->migrate($config);
  }

  /*
   * Migrates the triplestore from to a new URI.
   *
   * @param \Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB $engine
   *  The engine running on the triplestore
   * @param string $oldUri
   *  The URI that should be replaced.
   * @param string $newUri
   *  The new URI that replaces $oldUri.
   */
  function migrateTriplestore($engine, $oldUris, $newUri){
    $engine->directUpdate(self::migrationQuery($oldUris,$newUri));
  }

  /*
   * Migrates the 'wisski_salz_id2uri' table to a new URI.
   *
   * @param Drupal\mysql\Driver\Database\mysql\Connection $database
   *  The connection to the database
   * @param string $oldUri
   *  The URI that should be replaced.
   * @param string $newUri
   *  The new URI that replaces $oldUri.
   */
  function migrateSQLDatabase($oldUris, $newUri){
    $update = $this->database->update('wisski_salz_id2uri');
    foreach($oldUris as $oldUri){
      $update->condition('uri', $this->database->escapeLike($oldUri) . "%", $operator = 'LIKE');
    }
    $update->expression('uri', "REPLACE(uri, :old_value, :new_value)", [
        ':old_value' => $oldUri,
        ':new_value' => $newUri,
    ])->execute();
  }

  /*
   * Returns a SPARQL query that updates the triplestore.
   * Replaces the URIs of all graphs starting with any of
   * the URIs provided in $oldUris
   *
   * @param string $oldUri
   *  The old graph URI
   * @param string $newUri
   *  The new graph URI
   *
   * @return string
   *  The query
   */
  static function migrationQuery($oldUris, $newUri){
    foreach($oldUris as $oldUri){
      $where[] = "{  GRAPH ?oldGraph { ?oldSubj ?oldPred ?oldObj } .
        BIND(IF(strStarts(str(?oldGraph), \"$oldUri\"), IRI(REPLACE(str(?oldGraph),\"$oldUri\",\"$newUri\")), ?oldGraph) AS ?newGraph)
        BIND(IRI(REPLACE(str(?oldSubj),\"$oldUri\", \"$newUri\")) AS ?newSubj)
        BIND(IF(ISIRI(?oldObj), IRI(REPLACE(str(?oldObj),\"$oldUri\",\"$newUri\")),REPLACE(?oldObj,\"$oldUri\",\"$newUri\")) AS ?newObj)
        BIND(IF(strStarts(str(?oldPred), \"$oldUri\"), IRI(REPLACE(str(?oldPred),\"$oldUri\",\"$newUri\")), ?oldPred) AS ?newPred)
    }";
    // TODO: figure out if we want to filter by graph:
    // FILTER(strStarts(str(?oldGraph), \"$oldUri\"))

    $whereString = implode("UNION", $where);
    }
    return "DELETE { GRAPH ?oldGraph { ?oldSubj ?oldPred ?oldObj } }
  INSERT { GRAPH ?newGraph { ?newSubj ?newPred ?newObj } }
  WHERE{ $whereString }";
  }


}
