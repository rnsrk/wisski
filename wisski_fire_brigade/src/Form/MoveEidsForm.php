<?php

namespace Drupal\wisski_fire_brigade\Form;
// Core
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

// Dependency Injection
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mysql\Driver\Database\mysql\Connection;
use Drupal\Core\State\State;
use Drupal\Core\Messenger\Messenger;

// WissKI
use Drupal\wisski_salz\Entity\Adapter;
use Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB;

class MoveEidsForm extends FormBase {

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
   * The Drupal state
   *
   * @var Drupal\Core\State\State
   */
  protected $state;

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
      State $state,
      Messenger $messenger
      ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->state = $state;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('entity_type.manager'),
        $container->get('database'),
        $container->get('state'),
        $container->get('messenger')
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
    $form['insert'] = array(
        '#type' => 'submit',
        '#value' => t('Move')
        );
    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state){
    $info = $this->insertEids();
    $this->messenger->addMessage(t("Moved {$info['count']} entries to the Triplestore in {$info['duration']}s"));
    return $form_state;
  }


  /*
   * Reads the 'wisski_salz_id2uri' table and inserts its data into the baseFields graph in the triplestore.
   * Returns how many triples were inserted and how long the insertion took.
   *
   * @return array
   *  [
   *    'count' => int,
   *    'duration' => float
   *  ]
   */
  function insertEids() : array {
    if(empty($val))
      $val = 0;

    $result = $this->database
      ->select("wisski_salz_id2uri", 't')
      ->fields('t')
      ->condition('eid', $val, '>')
      ->orderBy('eid', 'ASC')
      ->execute();

    $adapters = array();
    $pairs = array();

    $count = 0;
    while($thing = $result->fetchObject()) {
      $adapter_id = $thing->adapter_id;
      $adapter = NULL;
      $eid = $thing->eid;
      $uri = $thing->uri;

      // if there is nothing to do, skip it.
      if(empty($eid) || empty($uri))
        continue;

      if(!isset($adapters[$adapter_id])) {
        $adapter = $this->entityTypeManager->getStorage('wisski_salz_adapter')->load($adapter_id);
        $adapters[$adapter_id] = $adapter;
      } else {
        $adapter = $adapters[$adapter_id];
      }

      if($adapter){
        $pairs[$adapter_id][] = [
          'uri' => $uri,
          'eid' => $eid
        ];
        $count++;
      }
    }

    $start = microtime(true);
    foreach($adapters as $adapter_id => $adapter){
      $this->update($adapter, $pairs[$adapter_id]);
    }
    $end = microtime(true);
    return [
      'count' => $count,
      'duration' => $end - $start,
    ];
  }


  /*
   * Performs an update query using the engine of the passed adapter
   *
   * @param Drupal\wisski_salz\Entity\Adapter $adapter 
   *
   * @param array $pairs
   *  Array of arrays representing a URI-EID pair:
   *  [
   *    [
   *      'uri' => $uri,
   *      'eid' => $eid,
   *    ],
   *  ]
   */
  function update(Adapter $adapter, array $pairs){
      $engine = $adapter->getEngine();
      $query = $this->buildQuery($engine, $pairs);
      $engine->directUpdate($query);
  }


  /*
   * Builds the insertion for the triplestore.
   *
   * @param Sparql11EngineWithPB $engine
   *
   * @param array $pairs 
   *  Array of arrays representing a URI-EID pair:
   *  [
   *    [
   *      'uri' => $uri,
   *      'eid' => $eid,
   *    ],
   *  ]
   * @return string
   *  The query
   */
  function buildQuery(Sparql11EngineWithPb $engine, array $pairs) : string {
    $baseFieldGraph = $engine->getBaseFieldGraph();
    $baseFieldUrl = $engine->getDefaultDataGraphUri() . "eid";

    $buildStatement = function ($pairs) use (&$baseFieldUrl){
      return $this->buildStatement($baseFieldUrl, $pairs);
    };

    $query = "INSERT DATA { ";
    $statements = array_map($buildStatement, $pairs);
    $statements[] = $this->buildPredicateStatement($baseFieldUrl);
    $query .= $this->graphWrapper($baseFieldGraph, implode(' . ', $statements));
    $query .= "}";
    return $query;
  }


  /*
   * Wraps a statement with a Graph.
   *
   * @param string $graphUri
   *  The URI of the graph.
   *
   * @param string $statement
   *  The statement to be wrapped.
   *
   * @return string
   *  The wrapped statement. 
   *  
   */
  function graphWrapper(string $graphUri, string $statement) : string {
    return "GRAPH  <$graphUri> { $statement }";
  }


  /*
   * Builds a statement from a URI-EID pair
   * and the eid predicate
   *
   * @param string $baseFieldUrl
   *  The URI of the eid predicate
   * @param array $pair
   *  [
   *    'uri' => $uri,
   *    'eid' => $eid,
   *  ]
   *
   * @return string 
   *  The statement. 
   */
  function buildStatement(string $baseFieldUrl, array $pair) : string{
    $uri = $pair['uri'];
    $eid = $pair['eid'];
    return "<$uri> <$baseFieldUrl> $eid";
  }


  /*
   * Builds the statement defining the eid predicate.
   *
   * @param string $baseFieldUri
   *  The URI of the eid predicate
   *
   * @return string.
   *  The statement.
   */
  function buildPredicateStatement(string $baseFieldUrl) : string {   
    return "<$baseFieldUrl> a owl:AnnotationProperty";
  }


  /**
   * Inserts random crap into the default adaper
   * For testing performance difference between bundled and single queries
   *
   * @param int $amount
   *  The amount of crap to be inserted
   *
   * @param boolean $single
   *  If queries should be sent one by one
   */ 
  private function insertCrap($amount ,$single){
    $adapter = $this->entityTypeManager->getStorage('wisski_salz_adapter')->load('default');

    $pairs = [];
    $start = microtime(true);
    for($i=0; $i<$amount; $i++){
      $pair = [
        'uri' => "http://this.is.crap.com/$i",
        'eid' => $i,
      ];
      $pairs[] = $pair;
      if($single){
        $this->update($adapter,[$pair]);
      }
    }
    if(!$single){
      $this->update($adapter, $pairs);
    }
    $end = microtime(true);

    return [
      'count' => $amount,
      'duration' => $end - $start,
    ];
  }
}
