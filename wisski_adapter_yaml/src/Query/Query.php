<?php

namespace Drupal\wisski_adapter_yaml\Query;

use Drupal\wisski_salz\Query\Query as QueryBase;
use Drupal\wisski_salz\Query\ConditionAggregate;
use Drupal\Core\Entity\EntityTypeInterface;

class Query extends QueryBase {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $ents = $this->getAdapter()->loadMultiple();
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