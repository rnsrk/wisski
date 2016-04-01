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
    
    if ($this->count) {
      $result = 0;
      foreach ($this->dependent_queries as $query) {
        $result += $query->execute();
      }
      return $result;
    } else {
      $result = array();
      foreach ($this->dependent_queries as $query) {
        $sub_result = $query->execute();
        $result = array_merge($result,$sub_result);
      }
      return $result;
    }
  }
  
   /**
   * {@inheritdoc}
   */
  public function condition($field, $value = NULL, $operator = NULL, $langcode = NULL) {
    foreach ($this->dependent_queries as $query) $query->condition($field,$value,$operator.$langcode);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function exists($field, $langcode = NULL) {
    foreach ($this->dependent_queries as $query) $query->exists($field,$langcode);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function notExists($field, $langcode = NULL) {
    foreach ($this->dependent_queries as $query) $query->notExists($field,$langcode);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function pager($limit = 10, $element = NULL) {
    foreach ($this->dependent_queries as $query) $query->pager($limit,$element);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function range($start = NULL, $length = NULL) {
    foreach ($this->dependent_queries as $query) $query->range($start,$length);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function sort($field, $direction = 'ASC', $langcode = NULL) {
    foreach ($this->dependent_queries as $query) $query->sort($field,$direction,$langcode);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function count() {
    foreach ($this->dependent_queries as $query) $query->count();
    return $this;
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