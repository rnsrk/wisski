<?php

namespace Drupal\wisski_salz\Query;

# TODO: Check if we can generalize special cases for query classes!
# perhaps we can add semantic methods for each of them

use Drupal\wisski_adapter_gnd\Query\Query;
use Drupal\wisski_core\WisskiCacheHelper;
use Drupal\Core\Entity\EntityTypeInterface;

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

    $this->populateAdapterQueries();
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
  private function populateAdapterQueries() {
    $adapters = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->loadMultiple();

    $preferred_queries = array();
    $other_queries = array();
    
    foreach ($adapters as $adapter) {
      $query = $adapter->getQueryObject($this->entityType,$condition,$this->namespaces);
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

    $pb_man = \Drupal::service('wisski_pathbuilder.manager');

    // find the IDs of adapters known for each adapter
    $adapterIDs = array();
    foreach($bundleIDs as $bid => $bundleID) {
      $adaptersForBundle = $pb_man->getPbsUsingBundle($bundleID);
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

        if ($field == "bundle") {
          array_push($bundleIds, current($cond["value"]));
        }

      }
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
    $this->populateRelevantAdapterQueries();

    // check if we can do an easy return
    $easy_ret = $this->executeEasyRet();
    if($easy_ret != NULL) {
      return $easy_ret;
    }
    
    $this->populateEmpties();
    
    // execute count query or actual query
    if ($this->count) {
      return $this->executeCount();
    }

    return $this->executeNormal();
  }

  private function executeNormal() {
    //call initializePager() to initialize the pager if we have one
    $pager = FALSE;
    if ($this->pager) {
      $pager = TRUE;
      $this->initializePager();
    }
    
    $result = array();

    // only one relevant adapter => execute it
    if(count($this->relevant_adapter_queries) == 1) {

      // make use of the pager!
      if ($pager || !empty($this->range)) {
        return $this->executePaginatedJoin($this->range['length'], $this->range['start']);
      }

      $query = current($this->adapter_queries);
      $query = $query->normalQuery();
      return $query->execute();
    }

    if($this->hasOnlyFederatableDependents()) {

      // if it is sparql, do a federated query!
      $first_query = $this->getFederatedQuery(FALSE);
      $first_query = $first_query->normalQuery();
      if ($pager || !empty($this->range)) {
        $first_query->range($this->range['start'],$this->range['length']);
      }

      // dpm(serialize($first_query), "first?");
      $ret = $first_query->execute();
      //  dpm($ret, "ret");

      return $ret;
    }

    // complicated cases below (we have > 1 adapter and can't federate!)
    
    // at least we have a pager!
    if ($pager || !empty($this->range)) {
    
      if($query instanceOf \Drupal\wisski_adapter_dms\Query\Query) {
        $querytmp = $query->normalQuery();
        $querytmp->range($this->range['start'],$this->range['length']);
        $ret = $querytmp->execute();
#              dpm(serialize($ret), "ret?");
        if(!empty($ret)) {
          return $ret;
        }
        
      }
    
      // use the old behaviour if we have a pager
      return $this->executePaginatedJoin($this->range['length'],$this->range['start']);
    }
    
#            dpm("no pager...");
      // if we dont have a pager, iterate it and sum it up 
      // @todo: This here is definitely evil. We should give some warning!
      // here be dragons
      foreach ($this->relevant_adapter_queries as $query) {
        $query = $query->normalQuery();
        $sub_result = $query->execute();
        $result = array_unique(array_merge($result,$sub_result));
#              dpm($sub_result, "result?");
#              dpm(self::$empties, "what is this?!");              
      }
      if (!empty(self::$empties)) $result = array_diff($result,self::$empties);
      return $result;
  }

   /** execute, but for a count query only */
   private function executeCount() {
    // only one dependent query => execute it
    if(count($this->relevant_adapter_queries) == 1) {
      $query = current($this->relevant_adapter_queries);
      
      $count = $query->countQuery()->execute() ? : 0;
      $count -= count(self::$empties);

      return $count;
    }

    // only federatable adapters => execute the federated query
    if($this->hasOnlyFederatableDependents()) {
      $first_query = $this->getFederatedQuery(TRUE);

      $count = $first_query->countQuery()->execute() ? : 0;
      $count -= count(self::$empties);

      return $count;
    }
    

    // complicated case: collect a result set and count elements in it
    $result = array();
  
    foreach ($this->relevant_adapter_queries as $adapter_id => $query) {
      
      // TODO: dms adapter
      if($query instanceOf \Drupal\wisski_adapter_dms\Query\Query) {
        /*$query = $query->count();

        $sub_res = $query->execute() ? : 0;

        if(!empty($sub_res)) {
          $result = $sub_res;
          continue;
        }
        */
      }

      // get the result for this adapter
      $sub_result = $query->execute() ? : NULL;
      if(!is_array($sub_result)) {
        $sub_result = array();
      }

      // merge in the results
      $result = array_unique(array_merge($result, $sub_result), SORT_REGULAR); 
    }

    $count = count($result);
    $count -= count(self::$empties);
    
    return $count;
  }

  /**
   * Add all parameters for a federated query to one of the query objects 
   * and return this.
   */
  protected function getFederatedQuery($is_count = FALSE) {
    // if everything is sparql we do a federated query
    // see https://www.w3.org/TR/sparql11-federated-query/
    $first_query = NULL;
      
    $max_query_parts = "";

    $total_order_string = "";

    $count = count($this->adapter_queries);
    
    $real_deps = array();

    foreach ($this->adapter_queries as $adapter_id => $query) {

#      dpm("dependent on $adapter_id");

#      dpm($query, "this is the query!");

      if($query instanceOf Query ||
        $query instanceOf \Drupal\wisski_adapter_geonames\Query\Query) {
        // this is null anyway... so skip it
        
        // reduce count
        $count--;
        
        continue;
      } else {
        $real_deps[$adapter_id] = $query;
      }
    }

#    dpm("I am here!!!");


    if($count > 1) {
      foreach ($real_deps as $adapter_id => $query) {
            
        if($is_count)
          $query->countQuery();
        else
          $query->normalQuery();
        
        // get the query parts
        $parts = $query->getQueryParts();
        $where = $parts['where'];
        $eids = $parts['eids'];
        $order = $parts['order'];

        if(!empty($order))     
          $total_order_string .= $order . " ";

#      dpm($where, "where");
#      dpm($eids, "eids");
#      dpm($order, "got order!");
        $filtered_uris = NULL;

        $eids_part = "";
      
        // we got eids?
        if(!empty($eids))
          $filtered_uris = array_filter($eids);
        if (!empty($filtered_uris)) {
          $eids_part .= 'VALUES ?x0 { <' . join('> <', $filtered_uris) . '> } ';
        }      

        // it might be that this is empty and this makes
        // a very ugly (while still working, but really ugly!!)
        // query. So do SOMETHING useful!
        if(empty($where)) {
#          $where = "?x0 a ?smthg . ";


          // special case: (by mark)
          // if there is no where 
          // and there is just one eid
          // we have a rather trivial answer
          // so we really dont want to ask the triple store in this
          // case!!!

#          if(count($eids) == 1) {
#                  
#          }

          #dpm($eids, "eids?");
          
        }

        // build up a whole string from that      
        $string_part = $where . "" . $eids_part;

        // only take the maximum, because up to now we mainly do path mode, which is bad anyway
        // @todo: a clean implementation here would be better!
        if(strlen($string_part) > strlen($max_query_parts))
          $max_query_parts = $string_part;
          
          // preserve the first query object for later use
        if(empty($first_query)) {
          $first_query = $query;
          continue;
        }
      }
    } else {
      // this here is a special case - there is only one
      // query left, so just pass it through!
      
      // there is only one in there anyway...
      foreach($real_deps as $adapter_id => $query) {
        $first_query = $query;
      }
    }
    
    if(!empty($max_query_parts)) {   
      $total_service_array = array();

      foreach ($this->adapter_queries as $adapter_id => $query) {
        if($query instanceOf Query ||
           $query instanceOf \Drupal\wisski_adapter_geonames\Query\Query) {
          // this is null anyway... so skip it
          continue;
        }
          
        $conf = $query->getEngine()->getConfiguration();
          
        $read_url = $conf['read_url'];
          
        // construct the service-string
        if($count > 1) 
          $service_string = " { SERVICE <" . $read_url . "> { " . $max_query_parts . " } }";
        else
          $service_string = $max_query_parts;

        // add it to the first query                     
        $total_service_array[] = $service_string;
      }
#      dpm($total_service_array, "tos");
      $first_query->setOrderBy($total_order_string);
      $first_query->setDependentParts($total_service_array);
    }
    
    return $first_query;
  }
  
  /** checks if this query only has federatable dependent queries */
  private function hasOnlyFederatableDependents() {
    foreach($this->relevant_adapter_queries as $adapter_id => $query) {
      if (!($query instanceof \Drupal\wisski_salz\WisskiQueryBase && $query->isFederatableSparqlQuery())) {
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
    
    $queries = $this->relevant_adapter_queries;
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
#      dpm("got: " . serialize($new_results));

      if (!empty(self::$empties)) $new_results = array_diff($new_results,self::$empties);

      //$post_res_count = count($new_results);      
      //dpm($post_res_count,$act_offset.' '.$act_limit);
      $old_sum = count($results);
      $results = array_unique(array_merge($results,$new_results));
      $curr_sum = count($results);
      
      $res_count = $curr_sum - $old_sum;
      $post_res_count = $curr_sum - $old_sum;

#      dpm(serialize($res_count), "res");
      
      if ($res_count === 0) {
        //$query->count();
        unset($query->range);
        
#        if($query 
        
        $res_count = $query->execute();
        #dpm($res_count, "res!");
        if(!is_array($res_count)) {
          $res_count = array();
        }

        $before = count($all_results);
        $all_results = array_unique(array_merge($all_results,$res_count));
        $after = count($all_results);
        
//        if (!is_numeric($res_count)) $res_count = count($res_count);
        
        //dpm($res_count,$key.' full count');
        $act_offset = $act_offset - ($after - $before);
        if ($act_offset < 0) $act_offset = 0;
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

#    dpm($results, "res!");
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
