<?php

namespace Drupal\wisski_core\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\NumericFilter  as ViewsNumeric;

/**
 * Numeric filter for fields.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("wisski_field_numeric")
 */
class FieldNumeric extends ViewsNumeric {

  /**
   * We don't support every operator from the parent class ("not between", for example),
   * hence the need to define only the operators we do support.
   */
  public function operators() {
    $operators = [
      '<' => [
        'title' => t('Is less than'),
        'method' => 'opSimple',
        'short' => t('<'),
        'values' => 1,
      ],
      '<=' => [
        'title' => t('Is less than or equal to'),
        'method' => 'opSimple',
        'short' => t('<='),
        'values' => 1,
      ],
      '=' => [
        'title' => t('Is equal to'),
        'method' => 'opSimple',
        'short' => t('='),
        'values' => 1,
      ],
      '<>' => [
        'title' => t('Is not equal to'),
        'method' => 'opSimple',
        'short' => t('!='),
        'values' => 1,
      ],
      '>=' => [
        'title' => t('Is greater than or equal to'),
        'method' => 'opSimple',
        'short' => t('>='),
        'values' => 1,
      ],
      '>' => [
        'title' => t('Is greater than'),
        'method' => 'opSimple',
        'short' => t('>'),
        'values' => 1,
      ],
      'BETWEEN' => [
        'title' => t('Is between'),
        'method' => 'opBetween',
        'short' => t('between'),
        'values' => 2,
      ],
    ];

    return $operators;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $info = $this->operators();
    if (!empty($info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}($this->realField);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opSimple($column) {
    // $this->query->query->condition($this->definition['field_name'], $this->value['value'], $this->operator);.
    $this->query->query->condition("eid", $this->value['value'], $this->operator);
  }

  /**
   * {@inheritdoc}
   */
  protected function opBetween($column) {
    $this->query->query->condition($this->field, [$this->value['min'], $this->value['max']], "BETWEEN");
  }

}
