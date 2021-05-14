<?php

namespace Drupal\wisski_salz\Query;

# TODO: Check if we can generalize special cases for query classes!
# perhaps we can add semantic methods for each of them

use Drupal\wisski_core\WisskiCacheHelper;
use Drupal\wisski_salz\AdapterHelper;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\wisski_pathbuilder\Entity\WisskiPathEntity;
use Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity;

use Drupal\Core\Config\Entity\Query\Condition as ConditionParent;

/**
 * WisskiQueryDelegator is used to construct Drupal Queries, then translate them to SparQL and execute them.
 * 
 * This process consists of three phases:
 * - "construct" phase: only the constructor is called with a conjuction between all conditions
 * - "build" phase: conditions and fields are added using the ->condition() and ->field() methods
 * - "execute" phase: the query is sent to the relevant adapters which translate them to SparQL and execute them. 
 */
class WisskiQueryDelegator extends WisskiQueryBase {

  //
  // =============== CONSTRUCT PHASE ===============
  //

  public function __construct(EntityTypeInterface $entity_type,$conjunction,array $namespaces) {
    parent::__construct($entity_type,$conjunction,$namespaces);

    $this->populateAdapterQueries($entity_type,$conjunction,$namespaces);
  }
    
  /**
   * we cache a list of entity IDs whose corresponding entites have an empty title in the cache table
   * those MUST be deleted from the view
   */
  protected static $empties;

  /** populates self::$empties if it's not already cached */
  private function populateEmpties() {
    if (isset(self::$empties)) {
      return;
    }

    self::$empties = array();

    $bundleIDs = $this->getWissKIBundleIDs();
    foreach ($bundleIDs as $bid => $bundleID) {
      $empties = WisskiCacheHelper::getEntitiesWithEmptyTitle($bundleID);
      self::$empties = array_merge(self::$empties, $empties);
    }
  }

  /**
   * an array of Query Objects keyed by the name of their parent adapter. 
   * This function should only be used during the *query construction* phase, and not during execution.
   * See relevant_adapter_queries.
   * 
   * This list always contains all adapter queries, even if some of them might not be used for a particular query. 
   */
  private $adapter_queries = NULL;

  /** called once to populate the adapter_queries array  */
  private function populateAdapterQueries(EntityTypeInterface $entity_type,$conjunction,array $namespaces) {
    $adapters = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->loadMultiple();

    $preferred_queries = array();
    $other_queries = array();
    
    foreach ($adapters as $adapter) {
      $query = $adapter->getQueryObject($entity_type,$conjunction,$namespaces);
      if ($adapter->getEngine()->isPreferredLocalStore()) {
        $preferred_queries[$adapter->id()] = $query;
      } else {
        $other_queries[$adapter->id()] = $query;
      }
    }
    $this->adapter_queries = array_merge($preferred_queries,$other_queries);
  }

  /**
   * Like $adapter_queries, but only for adapters relevant to this query. 
   * This should be populated before every call to query. 
   */
  private $relevant_adapter_queries = NULL;

  /**
   * should be called before a query is executed to populate the relevant adapter queries
   */
  private function populateRelevantAdapterQueries() {

    // find all the bundles involved in this query
    $bundleIDs = $this->getWissKIBundleIDs();

    // no bundle ids matching the query
    // probably something went wrong, so fall back to using all adapters!
    // no need to give an extra warning, that's already done in getWissKIBundleIDs()
    if (count($bundleIDs) == 0) {
      $this->relevant_adapter_queries = $this->adapter_queries;
      return;
    }

    $pb_man = \Drupal::service('wisski_pathbuilder.manager');

    // find the IDs of adapters known for each adapter
    $adapterIDs = array();
    foreach($bundleIDs as $bid => $bundleID) {
      $pbsForBundle = array_values($pb_man->getPbsUsingBundle($bundleID));
      $adaptersForBundle = array_map(function($pb) { return $pb['adapter_id']; }, $pbsForBundle);
      $adapterIDs = array_merge($adapterIDs, $adaptersForBundle);
    }

    // TODO: Provide some functionality of prioritizing adapters
    // probably via a ->getAdapterPriority and a stable sort here!
    // e.g. \Drupal\wisski_adapter_dms\Query\Query should be prioritized

    $this->relevant_adapter_queries = array_filter(
      $this->adapter_queries,
      function ($adapterID) use ($adapterIDs) {
        return in_array($adapterID, $adapterIDs);
      },
      ARRAY_FILTER_USE_KEY
    );
  }

  /** returns an array of bundle IDs involved in this query */
  public function getWissKIBundleIDs() {

    // make a queue of conditions to check recursively
    $conditionQueue = array($this->condition);
    $bundleIds = array();

    while(count($conditionQueue) > 0) {

      // take the first condition from the queue
      // to be safe, ignore non-condition instances
      $condition = array_shift($conditionQueue);
      if (!($condition instanceof ConditionParent)) {
        continue;
      }

      // iterate over any subconditions declared in this condition
      // - if it is a nested condition, add it to the queue
      // - if it is a 'bundle' condition, record the bundle id
      foreach ($condition->conditions() as $cond) {
        $field = $cond["field"];

        if (!is_string($field)) { 
          array_push($conditionQueue, $field);
          continue;
        }

        // requested a specific bundle
        if ($field == "bundle") {
          array_push($bundleIds, current($cond["value"]));
          continue;
        }

        // requested a specific eid
        // TODO: this handles only is equal to
        if ($field == "eid") {
          $eidBundleIds = AdapterHelper::getBundleIdsForEntityId($cond['value'], TRUE);
          $bundleIds = array_merge($bundleIds, $eidBundleIds);
          continue;
        }

      }
    }

    if (count($bundleIds) == 0) {
      \Drupal::messenger()->addWarning("No bundles are relevant for query");
    }

    return array_unique($bundleIds);
  }

  //
  // =============== EXECUTE PHASE ===============
  //

  /**
   * Execute executes this query and returns an array of results!
   *
   * Execute uses three different strategies:
   * - Case 1: 1 relevant adapter => send query to the adapter
   * - Case 2: >1 federatable adapters => make a "federated" query and send it to the dominant adapter
   * - Case 3: non-federatable adapters => send queries to each and merge in php memory (here be dragons!)
  */
  public function execute() {
    $plan = $this->makeQueryPlan();
    dpm($plan, "plan");

    // which fields should be returned?
    // in which order?
    //makeQueryForAdapterAndAst($adapter, $aast);
    //$query = makeQueryForAst($aast, $plan);

    $this->populateRelevantAdapterQueries();

    // check if we can do an easy return
    
    // $easy_ret = $this->executeEasyRet();
    // if($easy_ret != NULL) {
    //   return $easy_ret;
    // }
    
    
    $this->populateEmpties();
    
    // execute count query or actual query
    if ($this->count) {
      return $this->executeCount($plan);
    }

    return $this->executeNormal($plan);
  }

  private function makeQueryPlan() {
    $annotator = new ASTAnnotator(NULL);
    $planner = new QueryPlanner(NULL);
    
    $ast = $this->getConditionAST(TRUE);
    $aast = $annotator->annotate($ast);
    return $planner->plan($aast);
  }

  // give the actual adapter object not just the string
  private function makeQueryForAdapterAndAst($adapter, $aast) {

    $conjunction = 'AND';
    if ($aast !== NUll && $aast['type'] === ASTBuilder::TYPE_LOGICAL_AGGREGATE) {
      $conjuction = $aast['operator'];
    }

    // TODO: Write order into query
    $query = $adapter->getQueryObject($this->entityType,$conjunction,$this->namespaces);
    $this->addConditionFromAst($query, $query, $aast);
    
    return $query;
  }

  private function addConditionFromAst($query, $condition, $aast) {

    if ($aast === NULL) {
      return;
    }
    // we get to the childern here and can simply take the values from the current AST for the condition
    if ($aast['type'] === ASTBuilder::TYPE_FILTER) {
      $condition->condition($aast['field'], $aast['value'], $aast['operator'], $aast['langcode']);
      return;
    } 
    // create a new condition group and add conditions for all the children
    else if ($aast['type'] === ASTBuilder::TYPE_LOGICAL_AGGREGATE) {
      if ($aast['operator'] === "AND") {
        $group = $query->andConditionGroup();
      }
      else if ($aast['operator'] === "OR") {
        $group = $query->orConditionGroup();
      }
      foreach ($aast['children'] as $child) {
        $this->addConditionFromAst($query, $group, $child);
      }
      $condition->condition($group);
      return;
    }
    die("Implementation error!");
  }

  // add a skip pager here as parameter
  private function executeNormal($plan) {
    //call initializePager() to initialize the pager if we have one
    $pager = FALSE;
    if ($this->pager) {
      $pager = TRUE;
      $this->initializePager();
    }
    
    $result = array();

    if($plan === NULL) {
      \Drupal::messenger()->addWarning("Invalid Query provided. ");
      return NULL;
    }
    else if($plan['type'] === QueryPlanner::TYPE_EMPTY_PLAN) {
      return $this->executeNormalEmptyPlan($plan, $pager);
      // this should not happen, query doesn't have any adapters
      // maybe build sql query if possible
    }
    else if($plan['type'] === QueryPlanner::TYPE_SINGLE_ADAPTER_PLAN) {
      return $this->executeNormalSinglePlan($plan, $pager);
    }     
    else if($plan['type'] === QueryPlanner::TYPE_SINGLE_FEDERATION_PLAN) {
      return $this->executeSingleFederation($plan, $pager);
    }
    else if($plan['type'] === QueryPlanner::TYPE_SINGLE_PARTITION_PLAN) {
      return $this->executeSinglePartitionPlan($plan, $pager);
    }
    else if($plan['type'] === QueryPlanner::TYPE_MULTI_FEDERATION_PLAN) {

    }
    else if($plan['type'] === QueryPlanner::TYPE_MULTI_PARTITION_PLAN) {
      return $this->executeMultiPartitionPlan($plan, $pager);
    } 
    else {
      die("Implementation error! Unknown plan.");
    }

    \Drupal::messenger()->addWarning("Not implemented. ");
    return NULL;
  }

/** execute, but for a count query only */
  private function executeCount($plan) {
    if($plan === NULL) {
      \Drupal::messenger()->addWarning("Invalid Query provided. ");
      return 0;
    }
    else if($plan['type'] === QueryPlanner::TYPE_EMPTY_PLAN) {
      return $this->executeCountEmptyPlan($plan);
    }
    else if($plan['type'] === QueryPlanner::TYPE_SINGLE_ADAPTER_PLAN) {
      return $this->executeCountSinglePlan($plan);
    }     
    else if($plan['type'] === QueryPlanner::TYPE_SINGLE_FEDERATION_PLAN) {
      dpm("counting?");
      return $this->executeCountSingleFederation($plan);
    }
    else if($plan['type'] === QueryPlanner::TYPE_SINGLE_PARTITION_PLAN) {
      return $this->executeCountSinglePartitionPlan($plan);
    }
    else if($plan['type'] === QueryPlanner::TYPE_MULTI_FEDERATION_PLAN) {

    }
    else if($plan['type'] === QueryPlanner::TYPE_MULTI_PARTITION_PLAN) {
      return $this->executeCountMultiPartitionPlan($plan);
    } 
    else {
      die("Implementation error! Unknown plan.");
    }

    \Drupal::messenger()->addWarning("Not implemented. ");
    return 0;
  }

  private function executeSingleFederation($plan, $pager) {
    dpm("executeNormalSinglePlan");
    // TODO: check if only relevant adapters within plan (tom?)
    $adapters = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->loadMultiple($plan['adapters']);
    /*
    We want to get the sparql queries for each adapter. 
    Then we put them together to get the federated query.
    After that we send the federated query to the pivot adapter, which should return the complete result.
    */

    // get the pathbuilders for relevant bundle ids
    $pb_man = \Drupal::service('wisski_pathbuilder.manager');
    // TODO: iterate over all bundles? maybe delegate to adapter
    $bundleId = $plan['ast']['annotations']['bundles'][0];
    $pbsForBundle = array_values($pb_man->getPbsUsingBundle($bundleId));

    dpm($adapters, "adapters");
    dpm($pbsForBundle, "pbsForBundles");

    // additional foreach over all bundles?

    $sparql = "SELECT DISTINCT * WHERE { ";
    $pivotAdapter = current($adapters);
    dpm($pivotAdapter, "pivotAdapter");
    $triplesForPivotAdapter = "";
    $serviceAdapterString = "";
    foreach ($adapters as $adapter) {
      foreach ($pbsForBundle as $pbArray) {
        //$pathId from bundleid
        //get actual pb object from pbId (Array)
        $pb = WisskiPathbuilderEntity::load($pbArray['pb_id']);
        if ($adapter->id() === $pb->getAdapterId()) {
          $groups = $pb->getGroupsForBundle($bundleId);
          dpm($groups, "groups");
          foreach ($groups as $group) {
            if ($adapter->id() === $pivotAdapter->id()) {
              $triplesForPivotAdapter = $adapter->getEngine()->generateTriplesForPath($pb, $group, "", NULL, NULL, 0, 0, FALSE, '=', 'group', TRUE, array(), 0, "und");
              // without service

            } else {
              $serviceAdapterString .= " UNION { ";
              $triplesForServiceAdapters = $adapter->getEngine()->generateTriplesForPath($pb, $group, "", NULL, NULL, 0, 0, FALSE, '=', 'group', TRUE, array(), 0, "und");
              //with service
              $endpointUrl = $adapter->getEngine()->getFederationServiceUrl();
              dpm($endpointUrl, "endpointurl");
              $serviceAdapterString .= "SERVICE <" . $endpointUrl . "> { " ;
              $serviceAdapterString .= $triplesForServiceAdapters . " } } ";
            }
            // ($pb, $path, $primitiveValue = "", $subject_in = NULL, $object_in = NULL, $disambposition = 0, $startingposition = 0, $write = FALSE, $op = '=', $mode = 'field', $relative = TRUE, $variable_prefixes = array(), $numbering = 0, $language = "und")
            
            // build actual sparql query from triples
            // pivot has no service statement; but for all other pats, we need the endpoint uri here (adapter -> endpointUri) for the service
            // this is how the query we want to build should look like
            //
            //  SELECT DISTINCT * WHERE { 
            //   GRAPH ?g_x0 { ?x0 a <http://erlangen-crm.org/200717/E21_Person> } .
            //  } UNION {
            //    SERVICE <endpointUri> {
            //      GRAPH ?g_x0 { ?x0 a <http://erlangen-crm.org/200717/E21_Person> } .
            //    }
            //  }
          }
          
        }
      }
    }
    // as the first part of our sparql query we add the part without services
    $sparql .= " { " . $triplesForPivotAdapter . " } ";
    // then we add all the triples with their endpoits as service parts (?)
    $sparql .= $serviceAdapterString . " } ";

    dpm($sparql, "Spargel?");
    $pivotAdapterEngine = $pivotAdapter->getEngine();
    
    // we need to get the eids from the uris somehow 
    //$queryResultUris = $pivotAdapterEngine->directQuery($sparql);

    if ($pivotAdapterEngine->graph_rewrite) {
      $query = $pivotAdapterEngine->graphInsertionRewrite($query);
    }
    $query = $pivotAdapterEngine->rewriteValues($query);

    $queryResultUris = $pivotAdapterEngine->doQuery($query);
 
    

    $queryResultEids = array();
    foreach ($queryResultUris as $queryResultUri) {
      $queryResultEid = $pivotAdapter->getEngine()->getDrupalId($queryResultUri->x0->getUri());
      $queryResultEids[] = $queryResultEid;
    }
    //dpm($queryResultEids, "bla");
    //dpm($pivotAdapter->getEngine()->directQuery($sparql), "query results");
    dpm(count($queryResultEids), "count?");
    return $queryResultEids;
  }

  private function executeCountSingleFederation($plan) {
    // TODO: This is just a simple inefficient solution; make the actual count within the sparql query for better performance
    return count($this->executeSingleFederation);
  }


  private function executeNormalSinglePlan($plan, $pager) {
    dpm("executeNormalSinglePlan");
    $adapter = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->load($plan['adapter']);
    $query = $this->makeQueryForAdapterAndAst($adapter, $plan['ast']);
    $query = $query->normalQuery();

    
    if ($pager || !empty($this->range)) {
      $query->range($this->range['start'],$this->range['length']);
    }

    $results = $query->execute();
    dpm($results, "restults");
    return $results;
  }

  private function executeCountSinglePlan($plan) {
    dpm("executeCountSinglePlan");
    $adapter = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->load($plan['adapter']);
    $query = $this->makeQueryForAdapterAndAst($adapter, $plan['ast']);
    $query = $query->countQuery();
    return $query->execute();
  }

  private function executeNormalEmptyPlan($plan, $pager) {
    $ast = $plan['ast'];
    $results = $this->executeNormalEmptyPlanAST($ast);
    if ($pager || !empty($this->range)) {
      $results = array_slice($results, $this->range['start'], $this->range['length']);
    }
    return $results;
  }

  private function executeNormalEmptyPlanAST($ast) {
    if ($ast['type'] === ASTBuilder::TYPE_FILTER) {
      if ($ast['field'] !== 'eid') { // Unsupported
        dpm("Field is not 'eid', we do not support this.");
        return array(); 
      }
      // in case 'eid'
      // for future: perhaps we also have to consider the language here

      // if operator == Null, we can take the value directly
      if($ast['operator'] == NULL){
        return array($ast['value']);
        // if operator != NUll, we have to parse through array
      } elseif($ast['operator'] === '='){
          return array((int)($ast['value']['value']));
      } else {
          dpm("Unsupported operator.");
          return array(); 
      }
    } else {
        // TYPE_LOGICAL_AGGREGATE: and/or => in this case we want to merge recursively
        if($ast['operator'] === 'OR'){
          $results = array();
          foreach($ast['children'] as $child){
            $results = array_merge($results, $this->executeNormalEmptyPlanAST($child));
          }
          // we unique here since it is possible that (same) children exist multiple times
          return array_unique($results);
        } else {
          // if $ast['operator'] === 'AND'
          $results = array();
          foreach($ast['children'] as $child){
            $results[] = $this->executeNormalEmptyPlanAST($child);
          }
          if(count($results) === 0){
            return array();
          }

          $pivot = $results[0];
          $results = array_slice($results,1);
          $union_results = array();
          foreach($pivot as $pivot_element){
            $isContainedInAll = True;
            foreach($results as $res){     
              if(array_search($pivot_element, $res) === False){
                $isContainedInAll = False;
                break;
              }
            }
            if($isContainedInAll){
              $union_results[] = $pivot_element;
            }
          }
          return $union_results;
        }   
    }
  }

  private function executeCountEmptyPlan($plan) {
    return count($this->executeNormalEmptyPlan($plan, NULL));
  }

  private function executeSinglePartitionPlan($plan, $pager) {
    // we take the first adapter here for now, later on we want to consider all adapters
    $adapter = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->load($plan['adapters'][0]);
    $query = $this->makeQueryForAdapterAndAst($adapter, $plan['ast']);
    $query = $query->normalQuery();

    
    if ($pager || !empty($this->range)) {
      $query->range($this->range['start'],$this->range['length']);
    }

    $results = $query->execute();
    return $results;
  }

  private function executeCountSinglePartitionPlan($plan) {
    // also here we take the first adapter here for now, later on we want to consider all adapters
    $adapter = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->load($plan['adapters'][0]);
    $query = $this->makeQueryForAdapterAndAst($adapter, $plan['ast']);
    $query = $query->countQuery();
    return $query->execute();
  }

  private function executeMultiPartitionPlan($plan, $pager){
    $ast = $plan['ast'];
    if($ast['operator'] === 'OR'){
      $results = array();
      foreach($plan['plans'] as $child_plan){
        $results = array_merge($results, $this->executeNormal($child_plan));
      }
      if ($pager || !empty($this->range)) {
      $results = array_slice(array_unique($results), $this->range['start'],$this->range['length']);
      }

      return $results;
    } else {
      // $ast['operator'] === 'AND'
      // TODO: we have to finish this AND case, since this is just a copy from the other function
      // Please Tom also check if we did the 'OR' case right
      // Last one here: 07.05.2021
      $results = array();
      foreach($ast['plans'] as $child_plan){
        $results[] = $this->executeNormal($child_plan);
      }
      if(count($results) === 0){
        return array();
      }

      $pivot = $results[0];
      $results = array_slice($results,1);
      $union_results = array();
      foreach($pivot as $pivot_element){
        $isContainedInAll = True;
        foreach($results as $res){     
          if(array_search($pivot_element, $res) === False){
            $isContainedInAll = False;
            break;
          }
        }
        if($isContainedInAll){
          $union_results[] = $pivot_element;
        }
      }
      return $union_results;
    }   

    return array();
  }

  private function executeCountMultiPartitionPlan($plan){
    return count($this->executeMultiPartitionPlan($plan, NULL));
  }


// old execute function
//     // only one relevant adapter => execute it
//     if(count($this->relevant_adapter_queries) == 1) {
//       if (WISSKI_DEVEL) \Drupal::logger('wisski_query_delegator')->debug("Query Strategy: One Adapter");

//       // make use of the pager!
//       if ($pager || !empty($this->range)) {
//         return $this->executePaginatedJoin($this->range['length'], $this->range['start']);
//       }

//       $query = current($this->relevant_adapter_queries);
//       $query = $query->normalQuery();
//       return $query->execute();
//     }

//     //dpm($this->hasOnlyFederatableDependents(), "hasFederatable");
//     if($this->hasOnlyFederatableDependents()) {
//       if (WISSKI_DEVEL) \Drupal::logger('wisski_query_delegator')->debug("Query Strategy: Federation");

//       // if it is sparql, do a federated query!
//       // what does FALSE do here?
//       $first_query = $this->getFederatedQuery(FALSE);

//       $first_query = $first_query->normalQuery();
//       if ($pager || !empty($this->range)) {
//         $first_query->range($this->range['start'],$this->range['length']);
//       }

//       $ret = $first_query->execute();

//       return $ret;
//     }

//     // complicated cases below (we have > 1 adapter and can't federate!)
    
   
//     // to reduce the number of error messages
//     // at least we have a pager!
//      if ($pager || !empty($this->range)) {
//       // MyF: We have to test this in a later step; so first of all we remove this in order
//       /*if (WISSKI_DEVEL) \Drupal::logger('wisski_query_delegator')->debug("Query Strategy: In-Memory Pagination");
//       if($query instanceOf \Drupal\wisski_adapter_dms\Query\Query) {
//         $querytmp = $query->normalQuery();
//         $querytmp->range($this->range['start'],$this->range['length']);
//         $ret = $querytmp->execute();
//         if(!empty($ret)) {
//           return $ret;
//         }
        
//       }*/
    
//       // use the old behaviour if we have a pager
//       return $this->executePaginatedJoin($this->range['length'],$this->range['start']);
//     }

//     if (WISSKI_DEVEL) \Drupal::logger('wisski_query_delegator')->debug("Query Strategy: In-Memory Join");
    
// #            dpm("no pager...");
//       // if we dont have a pager, iterate it and sum it up 
//       // @todo: This here is definitely evil. We should give some warning!
//       // here be dragons
//       foreach ($this->relevant_adapter_queries as $query) {
//         $query = $query->normalQuery();
//         $sub_result = $query->execute();
//         $result = array_unique(array_merge($result,$sub_result));
// #              dpm($sub_result, "result?");
// #              dpm(self::$empties, "what is this?!");              
//       }
//       if (!empty(self::$empties)) $result = array_diff($result,self::$empties);
//       return $result;
//   }
  

    // old count function
  //   // only one dependent query => execute it
  //   if(count($this->relevant_adapter_queries) == 1) {
  //     if (WISSKI_DEVEL) \Drupal::logger('wisski_query_delegator')->debug("Count Strategy: One Adapter");

  //     $query = current($this->relevant_adapter_queries);
      
  //     $count = $query->countQuery()->execute() ? : 0;
  //     $count -= count(self::$empties);

  //     return $count;
  //   }

  //   // only federatable adapters => execute the federated query
  //   if($this->hasOnlyFederatableDependents()) {
  //     if (WISSKI_DEVEL) \Drupal::logger('wisski_query_delegator')->debug("Count Strategy: Federation");

  //     $first_query = $this->getFederatedQuery(TRUE);

  //     $count = $first_query->countQuery()->execute() ? : 0;
  //     $count -= count(self::$empties);

  //     return $count;
  //   }

  //   if (WISSKI_DEVEL) \Drupal::logger('wisski_query_delegator')->debug("Countgi Strategy: In Memory");
    

  //   // complicated case: collect a result set and count elements in it
  //   $result = array();
  
  //   foreach ($this->relevant_adapter_queries as $adapter_id => $query) {
      
  //     // TODO: dms adapter
  //     if($query instanceOf \Drupal\wisski_adapter_dms\Query\Query) {
  //       /*$query = $query->count();

  //       $sub_res = $query->execute() ? : 0;

  //       if(!empty($sub_res)) {
  //         $result = $sub_res;
  //         continue;
  //       }
  //       */
  //     }

  //     // get the result for this adapter
  //     $sub_result = $query->execute() ? : NULL;
  //     if(!is_array($sub_result)) {
  //       $sub_result = array();
  //     }

  //     // merge in the results
  //     $result = array_unique(array_merge($result, $sub_result), SORT_REGULAR); 
  //   }

  //   $count = count($result);
  //   $count -= count(self::$empties);
    
  //   return $count;
  // }

  /**
   * Add all parameters for a federated query to one of the query objects 
   * and return this.
   */
  protected function getFederatedQuery($is_count = FALSE) {
    // make a "federated query" object given all the inddividual query objects.
    // see https://www.w3.org/TR/sparql11-federated-query/

    // first query contains the 'first' of the relevant queries.
    // It is returned from this function and used to start the SERVICE<> query.
    $first_query = NULL;

    // queries should contain the query instances relevant for this federated queries.
    // it is keyed by adapter.
    $queries = array();
    foreach ($this->relevant_adapter_queries as $adapter_id => $query) {

      // if query is irrelevant, skip it!
      // TODO: This should be covered by $engine->supportsFederation()
      if($query instanceOf \Drupal\wisski_adapter_gnd\Query\Query ||
	      $query instanceOf \Drupal\wisski_adapter_geonames\Query\Query) {
        continue;
      }

      // set $first_query to the first relevant query.
      if(empty($first_query)) {
        $first_query = $query;
      }
      
      // increase count and store it in queries
      $queries[$adapter_id] = $query;
    }

    // bail out and don't actually use federation!
    // we only have one adapter.
    $count = count($queries);
    if ($count <= 1) {
      return $first_query;
    }

    // contains the longest query stringification.
    $max_query_parts = "";
    
    // contains an order string that consists of the 'order' query parts
    // from all the other queries.
    $total_order_string = "";

    foreach ($queries as $adapter_id => $query) {
      
      // build the query and grab the parts
      $query = $is_count ? $query->countQuery() : $query->normalQuery();
      $parts = $query->getQueryParts();

      // add the 'order' string to the total order string.
      $order = $parts['order'];
      if(!empty($order)) {
        $total_order_string .= $order . " ";
      }
      
      // grab non-empty eids from the query!
      $eids = $parts['eids'];
      if(!empty($eids)) {
        $eids = array_filter($eids);
      }

      // build a stringification of the query
      $string_part = $parts['where'];
      
      if (!empty($eids)) {
        $string_part .= 'VALUES ?x0 { <' . join('> <', $eids) . '> } ';
      }

      // only take the maximum, because up to now we mainly do path mode, which is bad anyway
      // @todo: a clean implementation here would be better!
      if(strlen($string_part) > strlen($max_query_parts)) {
        $max_query_parts = $string_part;
      }
    }

    
    // if there is no max query, then we can return immediatly.
    // TODO: Can this case ever occur, or is it covered by the 'count' <= 1 above?
    if (empty($max_query_parts)) {
      return $first_query;
    }

    $first_query->setOrderBy($total_order_string);
    
    
    // iterate over all the adapters and add the 'service' queries as a depdent query.
    $total_service_array = array();
    $is_first_query = true;
    foreach ($queries as $adapter_id => $query) {
      $service_url = $query->getEngine()->getFederationServiceUrl();
      
      $service_string = " { SERVICE <" . $service_url . "> { " . $max_query_parts . " } }";
      if ($is_first_query) {
        // the first query (which we're adding the dependent parts to) doesn't need the 'SERVICE' part
        // because we're querying the adapter itself.
        $service_string = $max_query_parts;
        $is_first_query = false;
      }
        
      // add it to the first query                     
      $total_service_array[] = $service_string;
    }
    
    $first_query->setDependentParts($total_service_array);
    
    return $first_query;
  }
  
  /** checks if this query only has federatable dependent queries */
  private function hasOnlyFederatableDependents() {
      //dpm($this->relevant_adapter_queries, "relevant_adapter_queries");

    foreach($this->relevant_adapter_queries as $adapter_id => $query) {
      // a query is federatable iff the engine supports federatable dependents.
      if (!$query->getEngine()->supportsFederation($query)) {
        return FALSE;        
      }
    }
    return TRUE;
  }

  /**
   * Traverses the conditions to determine if we can execute this query without sending a Query to the adapters. 
   * 
   * If yes, returns the result as it should be returned by execute. 
   * If no, returns NULL.
   */
  protected function executeEasyRet() {
    // determine if we can do an easy return on the eid field. 
    // do this only if it is != -1
    $easy_ret = -1;

    // iterate through all dependent queries 
    foreach($this->relevant_adapter_queries as $dep) {
      $cond_wrap = $dep->condition;
      
      if(empty($cond_wrap))
        continue;

      $conditions = $cond_wrap->conditions();
      
      // dpm($conditions, "cond?");
      
      // there is more than one condition
      if(count($conditions) != 1) {
        $easy_ret = -1;
        continue;
      }

      $one_cond = current($conditions);
      
      if($one_cond['field'] == "eid" && $one_cond['operator'] == "" && is_integer($one_cond['value'])) {
        // there is only one condition and this is an integer-condition, 
        // so we don't have to do anything but to return

        $easy_ret = $one_cond['value'];          
      }
    }

    if(count($this->relevant_adapter_queries) <= 1 || $easy_ret == -1) {
      return NULL;
    }

    return array($easy_ret);
  }
  
  /**
   * Implements a paginated query from the list of relevant adapter queries.
   */
  protected function executePaginatedJoin($limit,$offset) {

    $queries = array_merge(array(), $this->relevant_adapter_queries); // copy of the query array!
    $query = array_shift($queries);


    $act_offset = $offset;
    $act_limit = $limit;

    $all_results = array();
    $results = array();
    
    while (!empty($query)) {

      $query = $query->normalQuery();
      $query->range($act_offset,$act_limit);


      $new_results = $query->execute();
      $res_count = count($new_results);


      if (!empty(self::$empties)) $new_results = array_diff($new_results,self::$empties);

      //$post_res_count = count($new_results);      
      //dpm($post_res_count,$act_offset.' '.$act_limit, "post_res_count... ");
      $old_sum = count($results);
      $results = array_unique(array_merge($results,$new_results));
      $curr_sum = count($results);
      
      $res_count = $curr_sum - $old_sum;
      $post_res_count = $curr_sum - $old_sum;

//      dpm(serialize($res_count), "res");
      
      if ($res_count === 0) {
        //$query->count();
        unset($query->range);
        
#        if($query 
        
        $res_count = $query->execute();
        if(!is_array($res_count)) {
          $res_count = array();
        }

        $before = count($all_results);
        $all_results = array_unique(array_merge($all_results,$res_count));
        $after = count($all_results);
        
//        if (!is_numeric($res_count)) $res_count = count($res_count);
        
//        dpm($res_count,$key.' full count');
        $act_offset = $act_offset - ($after - $before);
	if ($act_offset < 0) {
		$act_offset = 0;
	}
        $query = array_shift($queries);
      } elseif ($post_res_count < $res_count) {
        $act_limit = $act_limit - $post_res_count;
        if ($act_limit < 1) break;
        $act_offset = $act_offset + $res_count;
        //don't load a new query, this one may have more
      } elseif ($res_count < $act_limit) {
        $act_limit = $act_limit - $res_count;
        $act_offset = 0;
        $query = array_shift($queries);
      } else break;
    }

    return $results;
  }

  //
  // =============== BUILD PHASE ===============
  //

  /**
   * {@inheritdoc}
   */
  public function condition($field, $value = NULL, $operator = NULL, $langcode = NULL) {
    parent::condition($field,$value,$operator,$langcode);
    foreach ($this->adapter_queries as $query) {
      $query->condition($field,$value,$operator.$langcode);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function exists($field, $langcode = NULL) {
    parent::exists($field,$langcode);
    foreach ($this->adapter_queries as $query) $query->exists($field,$langcode);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function notExists($field, $langcode = NULL) {
    parent::notExists($field,$langcode);
    foreach ($this->adapter_queries as $query) $query->notExists($field,$langcode);
    return $this;
  }

 /**
   * {@inheritdoc}
   */
  public function pager($limit = 10, $element = NULL) {
    parent::pager($limit,$element);
    foreach ($this->adapter_queries as $query) $query->pager($limit,$element);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function range($start = NULL, $length = NULL) {
    parent::range($start,$length);
    foreach ($this->adapter_queries as $query) $query->range($start,$length);
    return $this;
  }
 
  /**
   * {@inheritdoc}
   */
  public function sort($field, $direction = 'ASC', $langcode = NULL) {
    parent::sort($field,$direction,$langcode);
    foreach ($this->adapter_queries as $query) $query->sort($field,$direction,$langcode);
    return $this;
  }
  
  public function setPathQuery() {
    foreach ($this->adapter_queries as $query) $query->setPathQuery();
  }
  
  public function setFieldQuery() {
    foreach ($this->adapter_queries as $query) $query->setFieldQuery(); 
  }
  
  /**
   * {@inheritdoc}
   */
  public function existsAggregate($field, $function, $langcode = NULL) {
    return $this->conditionAggregate->exists($field, $function, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function notExistsAggregate($field, $function, $langcode = NULL) {
    return $this->conditionAggregate->notExists($field, $function, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function conditionAggregateGroupFactory($conjunction = 'AND') {
    return new ConditionAggregate($conjunction, $this);
  }
  
}
