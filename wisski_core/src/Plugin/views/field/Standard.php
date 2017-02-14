<?php

namespace Drupal\wisski_core\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow; 

/**
 * Default implementation of the base field plugin.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("wisski_standard")
 */
class Standard extends FieldPluginBase {
  
  /**
   * {@inheritdoc}
   */ 
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    if (is_array($value)) {
      $return = [];
      foreach ($value as $v) {
        $return[] = $this->sanitizeValue($v);
      }
      return join(', ', $return);
    }
    else {
      return $this->sanitizeValue($value);
    }
  }

}   

