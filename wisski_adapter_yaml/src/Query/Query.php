<?php

namespace Drupal\wisski_adapter_yaml\Query;

use Drupal\wisski_salz\Query\Query as QueryBase;
use Drupal\wisski_salz\Query\ConditionAggregate;
use Drupal\wisski_adapter_yaml\Plugin\wisski_salz\Engine\YamlAdapterEngine;
use Drupal\Core\Entity\EntityTypeInterface;

class Query extends QueryBase {

  private $parent_engine;

  public function __construct(EntityTypeInterface $entity_type,$condition,array $namespaces,YamlAdapterEngine $parent_engine) {
    parent::__construct($entity_type,$condition,$namespaces);
    $this->parent_engine = $parent_engine;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
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