<?php

/**
 * contains \Drupal\wisski_salz\WisskiQueryBase
 */
namespace Drupal\wisski_salz\Query;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\Query\QueryAggregateInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\wisski_salz\EngineInterface;

abstract class WisskiQueryBase extends QueryBase implements QueryInterface, QueryAggregateInterface {

  protected $parent_engine;

  public function __construct(EntityTypeInterface $entity_type,$condition,array $namespaces,EngineInterface $parent_engine) {
    $namespaces = array_merge($namespaces,QueryBase::getNamespaces($this));
    parent::__construct($entity_type,$condition,$namespaces);
    $this->parent_engine = $parent_engine;
  }
  
  public function getEngine() {
    return $this->parent_engine;
  }
  
  public function normalQuery() {
  
    $this->count = FALSE;
    return $this;
  }
}