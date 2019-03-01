<?php

namespace Drupal\wisski_core\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\Bundle as ViewsBundle;

/**
 * Filter handler for string.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("wisski_bundle")
 */
class Bundle extends ViewsBundle {

  /**
   *
   */
  public function operators() {
    $operators = [
      'IN' => [
        'title' => t('Is equal to'),
        'short' => t('='),
        'method' => 'opIn',
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
  public function opIn() {
    $this->query->query->condition($this->realField, $this->value, $this->operator);
  }

}
