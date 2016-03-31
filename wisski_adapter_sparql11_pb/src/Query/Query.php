<?php

namespace Drupal\wisski_adapter_sparql11_pb\Query;

use Drupal\wisski_salz\Query\Query as QueryBase;
use Drupal\wisski_salz\Query\ConditionAggregate;
use Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB;
use Drupal\Core\Entity\EntityTypeInterface;

class Query extends QueryBase {

  private $parent_engine;

  public function __construct(EntityTypeInterface $entity_type,$condition,array $namespaces,Sparql11EngineWithPB $parent_engine) {
    parent::__construct($entity_type,$condition,$namespaces);
    $this->parent_engine = $parent_engine;
    drupal_set_message("yeah!");
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    drupal_set_message("Yeah!");
    $ents = $this->parent_engine->loadMultiple();
    return array_keys($ents);
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