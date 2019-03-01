<?php

namespace Drupal\wisski_core\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\StringFilter as ViewsString;

/**
 * Filter handler for string.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("wisski_path_string")
 */
class PathString extends ViewsString {

  /**
   *
   */
  public function operators() {
    $operators = [
      '=' => [
        'title' => t('Is equal to'),
        'short' => t('='),
        'method' => 'opSimple',
        'values' => 1,
      ],
      '!=' => [
        'title' => t('Is not equal to'),
        'short' => t('!='),
        'method' => 'opSimple',
        'values' => 1,
      ],
      'CONTAINS' => [
        'title' => t('Contains'),
        'short' => t('contains'),
        'method' => 'opSimple',
        'values' => 1,
      ],
      'STARTS_WITH' => [
        'title' => t('Starts with'),
        'short' => t('begins'),
        'method' => 'opSimple',
        'values' => 1,
      ],
      'ENDS_WITH' => [
        'title' => t('Ends with'),
        'short' => t('ends'),
        'method' => 'opSimple',
        'values' => 1,
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
      $this->{$info[$this->operator]['method']}();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function opSimple() {
    $this->query->query->condition($this->realField, $this->value, $this->operator);
  }

}
