<?php

/**
 * @file
 */

/**
 *
 */
function wisski_apus_dialog_select_entity() {

  $params = (object) [
    'search_string' => '',
    'search_mode' => 'contains',
    'classes' => '',
  ];
  wisski_core_parse_http_params($params, 'params');

  $form = [];

  return drupal_render($form);

}
