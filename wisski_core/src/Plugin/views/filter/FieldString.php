<?php
/**
 * @file
 * Contains \Drupal\wisski_core\Plugin\views\filter\StringFilter.
 */

namespace Drupal\wisski_core\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\StringFilter as ViewsString;

/**
 * Filter handler for string.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("wisski_field_string")
 */
class FieldString extends ViewsString {

  function operators() {
    $operators = array(
      '=' => array(
        'title' => t('Is equal to'),
        'short' => t('='),
        'method' => 'opSimple',
        'values' => 1,
      ),
      '!=' => array(
        'title' => t('Is not equal to'),
        'short' => t('!='),
        'method' => 'opSimple',
        'values' => 1,
      ),
      'CONTAINS' => array(
        'title' => t('Contains'),
        'short' => t('contains'),
        'method' => 'opSimple',
        'values' => 1,
      ),
      'STARTS_WITH' => array(
        'title' => t('Starts with'),
        'short' => t('begins'),
        'method' => 'opSimple',
        'values' => 1,
      ),
      'ENDS_WITH' => array(
        'title' => t('Ends with'),
        'short' => t('ends'),
        'method' => 'opSimple',
        'values' => 1,
      ),
      'EMPTY' => array(
        'title' => t('Is empty'),
        'short' => t('empty'),
        'method' => 'opSimple',
        'values' => 1,
      ),
      'IN' => array(
        'title' => t('One of'),
        'short' => t('in'),
        'method' => 'opMulti',
        'values' => 1,
      ),
    );

    return $operators;
  }

  /**
   * {@inheritdoc}
   */
  function query() {
    $field = isset($this->configuration['wisski_field']) ? $this->configuration['wisski_field'] : $this->realField;
    $info = $this->operators();
    if (!empty($info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}($field);
    }
  }


  /**
   * {@inheritdoc}
   */
  function opSimple($field) {
    $this->query->query->condition($field, $this->value, $this->operator);
  }

  function opMulti($field) {
    $value = explode(',', $this->value);
    $this->query->query->condition($field, $value, $this->operator);
    
  }

}
