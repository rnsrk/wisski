<?php

/**
 * @file
 * Contains \Drupal\wisski_core\Plugin\views\argument\StringArgument.
 *
 */

namespace Drupal\wisski_core\Plugin\views\argument;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument\StringArgument as ViewsString;

/**
 * Numeric argument for fields.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("wisski_string")
 */
class StringArgument extends ViewsString {

  /** 
   * 
   */
  protected function prepareValue() {
    // this is taken from Drupal\views\Plugin\views\argument\StringArgument::query()
    // It's strange that there is no function wrapper for that, as it is
    // reused oftentimes
    $argument = $this->argument;
    if (!empty($this->options['transform_dash'])) {
      $argument = strtr($argument, '-', ' ');
    }

    if (!empty($this->options['break_phrase'])) {
      $this->unpackArgumentValue();
    }
    else {
      $this->value = [$argument];
      $this->operator = 'or';
    }
  }


  /**
   * {@inheritdoc}
   */
  public function query() {
    // note that $this->value may not be set already, we have to set it here
    $this->prepareValue();

    $field = isset($this->configuration['wisski_field']) ? $this->configuration['wisski_field'] : $this->realField;
    if ($this->operator == 'or') {
      $this->query->query->condition($field, $this->value, 'IN');
    }
    else {
      foreach($this->value as $value) {
        $this->query->query->condition($field, $value, '=');
      }
    }
    
  }

}

