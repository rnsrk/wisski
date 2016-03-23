<?php

/**
 * contains \Drupal\wisski_salz\WisskiQuery
 */
namespace Drupal\wisski_salz;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\Query\QueryAggregateInterface;

abstract class WisskiQuery extends QueryBase implements QueryInterface, QueryAggregateInterface {

  public function __construct($entity_type,$condition,array $namespaces) {
    $namespaces = array_merge($namespaces,QueryBase::getNamespaces($this));
    parent::__construct($entity_type,$condition,$namespaces);
  }
}