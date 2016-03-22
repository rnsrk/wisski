<?php

namespace Drupal\wisski_adapter_yaml\Query;

use Drupal\wisski_core\Query\Query as QueryBase;
use Drupal\wisski_adapter_yaml\Plugin\wisski_salz\Engine\YamlAdapterEngine;

class Query extends QueryBase {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $engine = new YamlAdapterEngine();
    $ents = $engine->loadMultiple();
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