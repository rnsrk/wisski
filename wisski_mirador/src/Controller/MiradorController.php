<?php

namespace Drupal\wisski_mirador\Controller;

use Drupal\Core\Controller\ControllerBase;


class MiradorController extends ControllerBase {

  public function start() {

    $form = array();

    $form['#markup'] = '<div id="viewer"></div>';
    $form['#allowed_tags'] = array('div', 'select', 'option','a', 'script');
    $form['#attached']['library'][] = "wisski_mirador/mirador";

    return $form;
  }
  
}
