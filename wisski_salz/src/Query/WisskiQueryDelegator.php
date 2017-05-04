<?php

namespace Drupal\wisski_salz\Query;

use Drupal\Core\Entity\EntityTypeInterface;

class WisskiQueryDelegator extends WisskiQueryBase {

  /**
   * an array of Query Objects keyed by the name of their parent adapter. We need this to make sure, every
   * dependent query gets the same conditions etc.
   */
  private $dependent_queries = array();
  
  /**
   * we cache a list of entity IDs whose corresponding entites have an empty title in the cache table
   * those MUST be deleted from the view
   */
  protected static $empties;

  public function __construct(EntityTypeInterface $entity_type,$condition,array $namespaces) {
    parent::__construct($entity_type,$condition,$namespaces);
    $adapters = entity_load_multiple('wisski_salz_adapter');
    $preferred_queries = array();
    $other_queries = array();
    foreach ($adapters as $adapter) {
      $query = $adapter->getQueryObject($this->entityType,$this->condition,$this->namespaces);
      if ($adapter->getEngine()->isPreferredLocalStore()) $preferred_queries[$adapter->id()] = $query;
      else $other_queries[$adapter->id()] = $query;
    }
    $this->dependent_queries = array_merge($preferred_queries,$other_queries);
  }
  
  public function execute() {
  
    if (!isset($this->empties)) {
      $bundle_id = NULL;
      foreach($this->condition->conditions() as $cond) {
        if ($cond['field'] === 'bundle') {
          $bundle_id = $cond['value'];
          break;
        }
      }
      //it is allowed to have an empty $bundle_id here
      self::$empties = \Drupal\wisski_core\WisskiCacheHelper::getEntitiesWithEmptyTitle($bundle_id);
      //dpm(self::$empties,'Empty titled Entities');
    }  
    
    if ($this->count) {
      $result = 0;
      foreach ($this->dependent_queries as $adapter_id => $query) {
        $query = $query->count();
        $sub_result = $query->execute() ? : 0;
        //dpm($adapter_id.' counted '.$sub_result);
        if (is_numeric($sub_result))
          $result += $sub_result;
        else drupal_set_message("Wrong result type from adapter $adapter_id: numeric expected, given " . gettype($subresult), 'error');
      }
      //dpm('we counted '.$result);
      if (!empty(self::$empties)) $result -= count(self::$empties);
      return $result;
    } else {
      $pager = FALSE;
      if ($this->pager) {
        $pager = TRUE;
        //initializePager() generates a clone of $this with $count = TRUE
        //this is then passed to the dependent_queries which are NOT cloned
        //thus we must reset $count for the dependent_queries
        $this->initializePager();
      }
      $result = array();
      
      if ($pager || !empty($this->range)) {
        return $this->pagerQuery($this->range['length'],$this->range['start']);
      }
      foreach ($this->dependent_queries as $query) {
        //set $query->count = FALSE;
        $query = $query->normalQuery();
        $sub_result = $query->execute();
        $result = array_unique(array_merge($result,$sub_result));
      }
      if (!empty(self::$empties)) $result = array_diff($result,$empties);
      return $result;
    }
  }
  
  protected function pagerQuery($limit,$offset) {
    //old versions below  
    $queries = $this->dependent_queries;
    $query = array_shift($queries);
    $act_offset = $offset;
    $act_limit = $limit;
    $results = array();
    while (!empty($query)) {
      $query = $query->normalQuery();
      $query->range($act_offset,$act_limit);
      $new_results = $query->execute();
      $res_count = count($new_results);
      if (!empty(self::$empties)) $new_results = array_diff($new_results,self::$empties);
      $post_res_count = count($new_results);      
      //dpm($post_res_count,$act_offset.' '.$act_limit);
      $results = array_unique(array_merge($results,$new_results));
      if ($res_count === 0) {
        $query->count();
        $res_count = $query->execute();
        if (!is_numeric($res_count)) $res_count = 0;
        //dpm($res_count,$key.' full count');
        $act_offset = $act_offset - $res_count;
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
    return $results;
  }

/*
  protected function pagerQuery($limit,$offset) {

    //old version below
    //wisski_tick();
    
    $results = array();
    $act_offset = $offset;
    $act_limit = $limit;
    foreach ($this->dependent_queries as $key => $query) {
      $query = $query->normalQuery();
      $query->range($act_offset,$act_limit);
      $new_results = $query->execute();
      if (!empty(self::$empties)) $new_results = array_diff($new_results,self::$empties);
      $res_count = count($new_results);
      dpm($res_count,$key.' '.$act_offset.' '.$act_limit);
      $results = array_unique(array_merge($results,$new_results));
      if ($res_count === 0) {
        $query->count();
        $res_count = $query->execute();
        if (!is_numeric($res_count)) $res_count = 0;
        //dpm($res_count,$key.' full count');
        $act_offset = $act_offset - $res_count;
      } elseif ($res_count < $act_limit) {
        $act_limit = $act_limit - $res_count;
        $act_offset = 0;
      } else break;
    }
    
    return array_slice($results,0,$limit);
  }
*/  

/*  
  protected function pagerQuery($limit,$offset) {
    
    //wisski_tick();
    $num_queries = count($this->dependent_queries);
    $running_queries = array();
    //we now go and ask all sub_queries whether they have enough answers to fill the offset.
    //If not, the following queries have to fill more
    foreach ($this->dependent_queries as $key => $query) {
      $query = $query->count();
      $sub_count = $query->execute() ? : 0;
      $sub_count = (int) $sub_count;
      if ($sub_count * $num_queries < $offset) {
        //we enlearge the offset for following queries
        $num_queries--;
      } else $running_queries[] = $key;
    }
    $sub_queries = array_intersect_key($this->dependent_queries,array_flip($running_queries));
    $results = array();
    //wisski_tick('Count');
    foreach ($sub_queries as $key => $query) {
      $query = $query->normalQuery();
      $query->range($offset / $num_queries,$limit);
      $new_results = $query->execute();
      //wisski_tick('query '.$key);
      $results = array_unique(array_merge($results,$new_results));
      //wisski_tick('rest '.$key);
    }
    //wisski_tick('Gather');
    asort($results);
    //wisski_tick('sort');
    return array_slice($results,0,$limit);
  }
*/  
  /**
   * {@inheritdoc}
   */
  public function condition($field, $value = NULL, $operator = NULL, $langcode = NULL) {
    parent::condition($field,$value,$operator,$langcode);
    foreach ($this->dependent_queries as $query) $query->condition($field,$value,$operator.$langcode);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function exists($field, $langcode = NULL) {
    parent::exists($field,$langcode);
    foreach ($this->dependent_queries as $query) $query->exists($field,$langcode);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function notExists($field, $langcode = NULL) {
    parent::notExists($field,$langcode);
    foreach ($this->dependent_queries as $query) $query->notExists($field,$langcode);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function pager($limit = 10, $element = NULL) {
    parent::pager($limit,$element);
    //foreach ($this->dependent_queries as $query) $query->pager($limit,$element);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function range($start = NULL, $length = NULL) {
    parent::range($start,$length);
    //foreach ($this->dependent_queries as $query) $query->range($start,$length);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function sort($field, $direction = 'ASC', $langcode = NULL) {
    parent::sort($field,$direction,$langcode);
    foreach ($this->dependent_queries as $query) $query->sort($field,$direction,$langcode);
    return $this;
  }
  
  public function setPathQuery() {
    foreach ($this->dependent_queries as $query) $query->setPathQuery();
  }
  
  public function setFieldQuery() {
    foreach ($this->dependent_queries as $query) $query->setFieldQuery(); 
  }
  
  /**
   * {@inheritdoc}
   */
// removed: we do this in execute() now
//  public function count() {
//    parent::count();
//    foreach ($this->dependent_queries as $query) $query->count();
//    return $this;
//  }

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
