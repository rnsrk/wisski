<?php

namespace Drupal\wisski_salz\Query;

use Drupal\Core\Entity\EntityTypeInterface;

class WisskiQueryDelegator extends WisskiQueryBase {

  /**
   * an array of Query Objects keyed by the name of their parent adapter. We need this to make sure, every
   * dependent query gets the same conditions etc.
   */
  private $dependent_queries = array();

  public function __construct(EntityTypeInterface $entity_type,$condition,array $namespaces) {
    parent::__construct($entity_type,$condition,$namespaces);
    $adapters = entity_load_multiple('wisski_salz_adapter');
    foreach ($adapters as $adapter) {
      $query = $adapter->getQueryObject($this->entityType,$this->condition,$this->namespaces);
      $this->dependent_queries[$adapter->id()] = $query;
    }
  }
  
  public function execute() {
#dpm($this,__METHOD__);
    if ($this->count) {
      $result = 0;
      foreach ($this->dependent_queries as $adapter_id => $query) {
        $query = $query->count();
        $sub_result = $query->execute();
        if (is_numeric($sub_result))
          $result += $sub_result;
        else dpm($sub_result,'Wrong result type from '.$adapter_id);
      }
      return $result;
    } else {
      $result = array();
      $pager = FALSE;
      if ($this->pager) {
        $pager = TRUE;
        //initializePager() generates a clone of $this with $count = TRUE
        //this is then passed to the dependent_queries which are NOT cloned
        //thus we must reset $count for the dependent_queries
        $this->initializePager();
      }
      if ($pager) {
        return $this->pagerQuery($this->range['length'],$this->range['start']);
      }
      foreach ($this->dependent_queries as $query) {
        //set $query->count = FALSE;
        $query = $query->normalQuery();
        $sub_result = $query->execute();
        $result = array_merge($result,$sub_result);
      }
      return $result;
    }
  }
  
  protected function pagerQuery($limit,$offset) {
    
    $num_queries = count($this->dependent_queries);
    $running_queries = array();
    //we now go and ask all sub_queries whether they have enough answers to fill the offset.
    //If not, the following queries have to fill more
    foreach ($this->dependent_queries as $key => $query) {
      $query = $query->count();
      $sub_count = $query->execute();
      if ($sub_count * $num_queries < $offset) {
        //we enlearge the offset for following queries
        $num_queries--;
      } else $running_queries[] = $key;
    }
    $sub_queries = array_intersect_key($this->dependent_queries,array_flip($running_queries));
    $results = array();
    foreach ($sub_queries as $query) {
      $query = $query->normalQuery();
      $query->range($offset / $num_queries,$limit);
      $results = array_merge($results,$query->execute());
    }
    asort($results);
    return array_slice($results,0,$limit);
  }
  
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
    foreach ($this->dependent_queries as $query) $query->pager($limit,$element);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function range($start = NULL, $length = NULL) {
    parent::range($start,$length);
    foreach ($this->dependent_queries as $query) $query->range($start,$length);
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