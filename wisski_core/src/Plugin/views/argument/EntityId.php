<?php

namespace Drupal\wisski_core\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\NumericArgument  as ViewsNumeric;

/**
 * Numeric argument for fields.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("wisski_entity_id")
 */
class EntityId extends ViewsNumeric {

  /**
   * We don't support every operator from the parent class ("not between", for example),
   * hence the need to define only the operators we do support.
   */
  public function operators() {
    dpm(__METHOD__, __METHOD__);
    $operators = [
      'IN' => [
        'title' => t('Is equal to'),
        'method' => 'opSimple',
        'short' => t('='),
        'values' => 1,
      ],
      'NOT IN' => [
        'title' => t('Is not equal to'),
        'method' => 'opSimple',
        'short' => t('!='),
        'values' => 1,
      ],
    ];

    return $operators;
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    // $this->value may be an array or a single value depending on the "Allow multiple values" option.
    $values = $this->value;
    if (empty($values)) {
      $values = [];
    }
    elseif (!is_array($values)) {
      $values = [$values];
    }
    $this->query->query->condition("eid", $values, 'IN');
  }

}
