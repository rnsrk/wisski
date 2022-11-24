<?php

namespace Drupal\wisski_statistics\Statistics;

use Drupal\Core\Utility\TableSort;
use Drupal\Core\Form\FormStateInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// depencency injection
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\mysql\Driver\Database\mysql\Connection;
use Drupal\Component\Datetime\Time;
use Drupal\Core\State\State;

use Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB;

abstract class StatisticType {
  // TODO: probably add a ROUTE type for wisski routes
  public const TABLE = "TABLE";
  public const DATE = "DATE";
  public const SCALAR = "SCALAR";
  public const BOOLEAN = "BOOLEAN";
  public const LINK = "LINK";
  public const WISSKI_BUNDLE_ID = "WISSKI_BUNDLE_ID";
};



/**
 * Controller routines for statistics.
 * Adaption from https://git.drupalcode.org/project/entity_count/-/blob/8.x-1.x/src/Controller/EntityCountController.php
 */
class WisskiStatistics {
  const WISSKI_INDIVIDUAL = "wisski_individual";
  const WISSKI_SALZ_ADAPTER = "wisski_salz_adapter";

  const ASC = "asc";
  const DESC = "desc";

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The database connection
   *
   * @var Drupal\mysql\Driver\Database\mysql\Connection
   */
  protected $database;


  /**
   * The Drupal Time
   *
   * @var Drupal\Core\Datetime\Time
   */
  protected $time;

  /**
   * The Drupal state
   *
   * @var Drupal\Core\State\State
   */
  protected $state;


  static protected $statistics = array(
      'users'=> array(
        'totalUsers' => array('type' =>  StatisticType::SCALAR),
        'lastLogin' => array('type' => StatisticType::DATE),
        ),
      'bundles' => array(
        'totalBundles' => array('type' => StatisticType::SCALAR),
        'totalMainBundles' => array('type' => StatisticType::SCALAR),
        'bundleStatistics' => array(
          'type' => StatisticType::TABLE,
          'sort' => ['mainBundle', self::ASC],
          'columns' => array(
            'label' => StatisticType::SCALAR,
            'machineName'  => StatisticType::WISSKI_BUNDLE_ID,
            'entities' => StatisticType::SCALAR,
            'lastEdit' => StatisticType::DATE,
            'mainBundle' => StatisticType::BOOLEAN,
            ),
          ),
        ),
      'triplestore' => array(
        'totalTriples' => array('type' => StatisticType::SCALAR),
        'graphStatistics'=> array(
          'type' => StatisticType::TABLE,
          'sort' => ['triples', self::DESC],
          'columns' => array(
            'uri' => StatisticType::SCALAR,
            'triples' => StatisticType::SCALAR,
            ),
          ),
        ),
      'activity' => array(
          'totalEditsLastWeek' => array('type' => StatisticType::SCALAR),
          'mostVisited' => array('type' => StatisticType::LINK),
          //'pageVisits' => array(
          //  'type' => StatisticType::TABLE,
          //  'sort' => ['visits', self::DESC],
          //  'columns' => array(
          //    'url' => StatisticType::SCALAR,
          //    'visits' => StatisticType::SCALAR,
          //    ),
          //  ),
          ),
      );


  /**
   * The constructor.
   */
  public function __construct(
      EntityTypeManagerInterface $entity_type_manager,
      EntityTypeBundleInfoInterface $entity_type_bundle_info,
      Connection $database,
      Time $time,
      State $state,
      ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->database = $database;
    $this->time = $time;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('entity_type.manager'),
        $container->get('entity_type.bundle.info'),
        $container->get('database'),
        $container->get('datetime.time'),
        $container->get('state'),
        );
  }


  //------------------------//
  //  Statistics Generation //
  //------------------------//

  /**
   * Generate the statsitics for export or saving.
   *
   * @return array
   *  The statistics
   */
  public function generateStatistics($statisticsMap = null) : array {
    // default to all statistics
    $statisticsMap = $statisticsMap ?? self::$statistics;

    $statisticsValues = [];
    foreach($statisticsMap as $groupName => $stats){
      foreach($stats as $function => $attributes){
        $statisticsValues[$groupName][$function] = [$this, $function]();
      }
    }
    return $statisticsValues;
  }


  //-------------------//
  //  Statistics Maps  //
  //-------------------//

  public static function fullStatisticsMap() : array {
    return self::$statistics;
  }

  public static function scalarStatisticsMap() : array {
    foreach(self::$statistics as $groupName => $stats){
      foreach($stats as $function => $attributes){
        if($attributes['type'] == StatisticType::TABLE){
          continue;
        }
        $scalarStatistics[$groupName][$function] = $attributes;       
      }
    }
    return $scalarStatistics;
  }

  //--------------------//
  //  State Management  //
  //--------------------//

  /**
   * Generates statistics, saves and returns them.
   */
  public function update() : array {
    // accumulate single requests from wisski_statistics_requests into wisski_statistics_page_visits
    $requests = $this->database->query("SELECT url, COUNT(*) AS count FROM wisski_statistics_requests GROUP BY url")->fetchAll();
    foreach($requests as $row){
      $visits = intval($row->count);
      $url = $row->url;
      $this->database->merge('wisski_statistics_page_visits')
        ->key(array('url' => $url))
        ->insertFields(array(
              'url' => $url,
              'visits' => $visits,
              )
            )   
        ->expression('visits', 'visits + :count', array(':count' => $visits))
        ->execute();
    }
    // clear the table storing single requests
    $this->database->truncate('wisski_statistics_requests')->execute();


    $statisticValues = $this->generateStatistics();
    $this->state->set('wisski_statistics', $statisticValues);
    return $statisticValues;
  }

  /** 
   * Read the saved statistics and generates them if they dont exist yet
   */
  public function read() : array {
    $statisticsValues = $this->state->get('wisski_statistics');
    if(!$statisticsValues || empty($statisticsValues)){
      $statisticsValues = $this->update();
    }
    return $statisticsValues;
  }

  //-----------------------//
  //  Activity Statistics  //
  //-----------------------//


  /*
   * Retuns the number of edits which occured in the last 7 days
   */
  function totalEditsLastWeek() : int {
    $now = \Drupal::time()->getCurrentTime();
    $oneWeekPrior = $now - (60*60*24*7);
    $query = $this->database->select('wisski_revision', 'r');
    $query->condition('revision_timestamp', $oneWeekPrior, '>=');
    $totalEdits = $query
      ->countQuery()
      ->execute()
      ->fetchField();
    return $totalEdits ?? 0;
  }

  /**
   * Returns the most visited site
   */
  function mostVisited(){
    $query = $this->database->select('wisski_statistics_page_visits', 't');
    $query->fields('t', ['url','visits']);
    $query->orderBy('visits', 'DESC');
    $query->range(0,1);

    return $query->execute()->fetchAll()[0]->url;
  }


  /**
   * Returns a table containing all accessed 
   * pages and their access count.
   */
  function pageVisits() {
    $query = $this->database->select('wisski_statistics_page_visits', 't');
    $query->fields('t');
    $query->orderBy('visits', 'DESC');
    $result = $query->execute()->fetchAll();

    $table = [];
    foreach($result as $row){
      $table[] = array(
          'url' => $row->url,
          'visits' => intval($row->visits),
          );
    }
    return $table;
  }


  //---------------------//
  //  Bundle Statistics  //
  //---------------------//

  /**
   * Gets number of main bundles
   */
  function totalMainBundles() : int {
    return count($this->getMainBundles());
  }


  /**
   * Gets number of bundles
   */
  function totalBundles() : int {
    return count($this->entityTypeManager->getStorage('wisski_bundle')->loadMultiple());
  }


  /**
   * Get all main bundles
   */
  protected function getMainBundles() : array {
    $mainBundles = [];
    $bundles = $this->entityTypeManager->getStorage('wisski_bundle')->loadMultiple();
    foreach($bundles as $bundleId => $bundle){
      // when bundle has no parent it's a main bundle
      if(empty($bundle->getParentBundleIds())){
        $mainBundles[$bundleId] = $bundle;
      }
    }
    return $mainBundles;
  }

  /**
   * Gets the last time that a bundle was edited.
   *
   * @param string $bundleId
   *  The bundle to be queried.
   * @return string or null if bundle was never edited
   *  The timestamp.
   */ 
  protected function lastEdit(string $bundleId) : int {
    $query = $this->database->select('wisski_entity_field_properties', 'f');
    $query->addExpression('MAX(ident)');
    $timestamp = $query
      ->condition('bid', $bundleId)
      ->condition('fid', 'changed')
      ->execute()
      ->fetchField();
    return $timestamp ?? 0;
  }


  /**
   * Queries some statsitics about WissKI bundles.
   *
   * @return array
   *  The statistics about the Bundles
   *  [
   *    'label' => string,
   *    'bundle_id' => string,
   *    'entities' => number,
   *    'last_edit' => string,
   *    'main_bundle' => boolean,
   *  ]
   */ 
  protected function bundleStatistics() : array {
    $bundleStats = [];

    $mainBundles = $this->getMainBundles();
    $mainBundleIds = array_keys($mainBundles);

    $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo(self::WISSKI_INDIVIDUAL);
    foreach ($bundleInfo as $bundleId => $bundleData) {
      $bundle = $bundleData['label'];
      $definition = $this->entityTypeManager->getDefinition(self::WISSKI_INDIVIDUAL);

      $keys = $definition->getKeys();
      $bundleKey = is_null($keys['bundle']) ? 'bundle' : $keys['bundle'];

      $query = $this->entityTypeManager->getStorage(self::WISSKI_INDIVIDUAL)->getQuery();
      $query->condition($bundleKey, [$bundleId]);
      $query->count();

      // TODO: see if this actually works in case of an error
      try{
        $count = $query->execute();
      }
      catch(\Throwable $e){
        continue;
      }

      $timestamp = $this->lastEdit($bundleId);

      // this determines the ordering in the table
      $bundleStats[] = [
        'label' => $bundle,
        'machineName' => $bundleId,
        'entities' => $count,
        'lastEdit' => $timestamp,
        'mainBundle' => in_array($bundleId, $mainBundleIds),
      ]; 
    }

    return $bundleStats;
  }


  //-------------------//
  //  User Statistics  //
  //-------------------//

  /**
   * Get total number of users.
   */
  function totalUsers() : int {
    return $this->database->select('users_data', 'u')
      ->fields('u', ['uid'])
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Get the timestamp of the most recent login
   */
  function lastLogin() : string {
    $query = $this->database->select('users_field_data', 'u');
    $query->addExpression('MAX(login)');
    $timestamp = $query->execute()->fetchField();
    return $timestamp;
  }

  //--------------------------//
  //  Triplestore Statistics  //
  //--------------------------//


  /**
   * Get total number of triples in the Triplestore.
   */
  function totalTriples() : int {
    $query = "SELECT (COUNT(?s) AS ?total ) WHERE { ?s ?p [] }";
    $adapters = $this->entityTypeManager->getStorage(self::WISSKI_SALZ_ADAPTER)->loadMultiple();
    $totalTriples = 0;
    foreach($adapters as $adapter){
      $engine = $adapter->getEngine();
      if(!$engine instanceof Sparql11EngineWithPB ){
        continue;
      } 
      $results = $engine->directQuery($query);
      foreach($results as $result){
        $totalTriples += $result->total->getValue();
      }
    }
    return $totalTriples;
  }


  /**
   * Get number of Triples per Graph
   */
  function graphStatistics() : array {
    $query = "SELECT DISTINCT ?g (COUNT(?g) as ?count) WHERE { GRAPH ?g { ?s ?p [] } } GROUP BY ?g";
    $adapters = $this->entityTypeManager->getStorage(self::WISSKI_SALZ_ADAPTER)->loadMultiple();
    $graphs = [];
    foreach($adapters as $adapter){
      $engine = $adapter->getEngine();
      if(!$engine instanceof Sparql11EngineWithPB){
        continue;
      } 

      $results = $engine->directQuery($query);
      foreach($results as $result){
        $graphs[] = [
          'uri' => $result->g->getUri(),
          'triples' => $result->count->getValue(),
        ];
      }
    }
    return $graphs;
  }

}

