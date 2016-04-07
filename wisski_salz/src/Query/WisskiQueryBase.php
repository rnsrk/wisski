<?php

/**
 * contains \Drupal\wisski_salz\WisskiQueryBase
 */
namespace Drupal\wisski_salz\Query;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\Query\QueryAggregateInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\wisski_salz\AdapterInterface;

abstract class WisskiQueryBase extends QueryBase implements QueryInterface, QueryAggregateInterface {

  protected $parent_adapter;

  public function __construct(EntityTypeInterface $entity_type,$condition,array $namespaces,AdapterInterface $parent_adapter) {
    $namespaces = array_merge($namespaces,QueryBase::getNamespaces($this));
    parent::__construct($entity_type,$condition,$namespaces);
    $this->parent_adapter = $parent_adapter;
  }
  
  public function getAdapter() {
    return $this->parent_adapter;
  }
}