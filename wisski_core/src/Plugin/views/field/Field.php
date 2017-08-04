<?php

namespace Drupal\wisski_core\Plugin\views\field;

use Drupal\views\Plugin\views\field\Field as ViewsField;
use Drupal\views\ResultRow; 

/**
 * Default implementation of the base field plugin.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("wisski_field")
 */
class Field extends ViewsField {
  
  /**
   * {@inheritdoc}
   */ 
  public function query($use_groupby = FALSE) {
    $this->query->addField($this->realField, $this->realField);
    $this->query->addField('_entity', '_entity');
  }

}   

