<?php

/**
 * contains \Drupal\wisski_salz\WisskiQueryBase
 */
namespace Drupal\wisski_salz\Query;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\Query\QueryAggregateInterface;
use Drupal\Core\Entity\EntityTypeInterface;

abstract class WisskiQueryBase extends QueryBase implements QueryInterface, QueryAggregateInterface {

  public function __construct(EntityTypeInterface $entity_type,$condition,array $namespaces) {
    $namespaces = array_merge($namespaces,QueryBase::getNamespaces($this));
    parent::__construct($entity_type,$condition,$namespaces);
  }
}