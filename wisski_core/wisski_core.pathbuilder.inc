<?php

function wisski_core_edit_bundle_paths($form,&$form_state,$in_bundle = NULL) {

  if ($in_bundle == NULL && isset($form['bundle'])) $bundle = $form_state['values']['bundle'];
  else {
    if ($in_bundle == NULL) throw new InvalidArgumentException("You must specify a bundle to be displayed");
    if (($in_bundle instanceof WisskiCoreBundle)) {
      $bundle = $in_bundle; 
    } elseif (!is_string($in_bundle) || !($bundle = entity_load_single('wisski_core_bundle',$in_bundle))) {
      throw new InvalidArgumentException("Given parameter $in_bundle is not a bundle or uri");
    }
    $form['bundle'] = array(
      '#type' => 'hidden',
      '#value' => $bundle,
    );
  }
  $old_paths = db_select('wisski_pb_pathdata','paths')
                ->fields('paths')
                ->condition('starting_concept',$bundle->uri,'LIKE')
                ->condition('pending',0,'=')
                ->execute()
                ->fetchAllAssoc('uuid');
  foreach($old_paths as $id => $old_path) {
    $form[$id] = array(
      '#type' => 'fieldset',
      '#title' => $old_path->short_name,
      '#collapsible' => TRUE,
      '#tree' => TRUE,
      '#weight' => $old_path->weight,
    );
    $count = 0;
    $path_array = unserialize($old_path->path_array);
    if (!empty($path_array)) {
      foreach($path_array as $single_step) {
        $form[$id]["step$count"] = array(
          '#markup' => $single_step,
          '#suffix' => ' <br>',
        );
        $count++;
      }
    }
    $has_external = FALSE;
    $external = $old_path->external_path;
    while ($external != NULL) {
      $has_external = TRUE;
      $external_path = db_select('wisski_pb_pathdata','paths')
                        ->fields('paths')
                        ->condition('uuid',$external,'=')
                        ->condition('pending',0,'=')
                        ->execute()
                        ->fetchObject();
      $path_array = unserialize($external_path->path_array);
      $form[$id]["sub$count"] = array(
        '#type' => 'fieldset',
        '#title' => $external_path->starting_concept.' => '.$external_path->short_name,
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#tree' => TRUE,
      );
      $subcount = 0;
      foreach($path_array as $single_step) {
        $form[$id]["sub$count"][$subcount] = array(
          '#markup' => $single_step,
          '#suffix' => ' <br>',
        );
        $subcount++;
      } 
      $count++;
      $external = $external_path->external_path;
    }
    if ($has_external) {
      $form[$id]['datatype_property'] = array(
        '#markup' => $external_path->datatype_property,
      );
    } else {    
      $form[$id]['datatype_property'] = array(
        '#markup' => $old_path->datatype_property,
      );
    }
  }
  
  $form['item'] = array(
    '#type' => 'fieldset',
    '#title' => t('Add Path'),
    '#collapsible' => FALSE,
    '#tree' => TRUE,
    '#weight' => 1000,
  );
  
  $form['item']['name'] = array(
    '#type' => 'textfield',
    '#title' => t('Path name'),
    '#default_value' => isset($form_state['values']['item']['name']) ? $form_state['values']['item']['name'] : '',
    '#description' => t(''),
    '#required' => TRUE,
  );
  
  $form['item']['path'] = array(
    '#type' => 'container',
    '#prefix' => '<div id="full_path">',
    '#suffix' => '</div>',
  );
  
  /*
  $form['item']['description'] = array(
    '#type' => 'textfield',
    '#title' => t('Description'),
    '#default_value' => '',
    '#description' => t(''),
  );
  */
  
  $path = array();
  $new = FALSE;
  if (isset($form_state['triggering_element']) && $form_state['triggering_element']['#name'] == 'item[path][step]') {    
    $step = $form_state['values']['item']['path']['step'];
    while (is_array($step)) $step = current(array_keys($step));
    if ($step == 'default') {
      if (isset($form_state['values']['item']['starting_concept'])) $path = array($form_state['values']['item']['starting_concept']);
      else $path = array();
    } else $path = array($step);
    $name = $form_state['values']['item']['name'];
    if (isset($form_state['values']['item']['uuid'])) {
      $uuid = $form_state['values']['item']['uuid'];
      $result = db_select('wisski_pb_pathdata','path')
              ->fields('path')
              ->condition('uuid',$uuid,'=')
              ->execute()
              ->fetchObject();
      $path = array_merge(unserialize($result->path_array),$path);
    } else {
      $new = TRUE;
    }
    $fields = array(
      'short_name' => $name,
      'path_array' => serialize($path),
      'weight' => 0,
      'pending' => 1,
    );
    if ($new) {
      $uuid = wisski_core_make_uuid($name);
      $fields['uuid'] = $uuid;
      if (isset($form_state['values']['item']['starting_concept'])) {
        $fields['starting_concept'] = $form_state['values']['item']['starting_concept'];
        db_insert('wisski_pb_pathdata')
          ->fields($fields)
          ->execute();
      }
    } else {
      db_update('wisski_pb_pathdata')
        ->fields($fields)
        ->condition('uuid',$uuid,'=')
        ->execute();
    }
    $form['item']['uuid'] = array(
      '#type' => 'hidden',
      '#value' => $uuid,
    );
  }//triggering_element
  
  if (isset($form_state['values']['item']['name'])) {
    foreach($path as $key => $step) {
      $form['item']['path'][$key] = array(
        '#type' => 'select',
        '#options' => array($step),
        '#default_value' => 0,
        '#disabled' => TRUE,
      );
    }
    $last = array_pop($path);
  } else {
    $last = $bundle->uri;
    $form['item']['starting_concept'] = array(
      '#type' => 'hidden',
      '#value' => $last,
    );
  }
  $entries = wisski_salz_next_steps($last);
  $options = array('default' => '-choose-');
  $superclasses = array($last => $last);
  foreach($entries as $key => $entry) {
    if (is_array($entry)) {
      $options[$key] = array();
      $superclasses[$key] = $key;
      foreach($entry as $prop) {
        $options[$key][$prop] = $prop;
      }
    } else $options[$entry] = $entry;
  }
  $form['item']['path']['step'] = array(
    '#type' => 'select',
    '#title' => '',
    '#options' => $options,
    '#default_value' => 'default',
    '#multiple' => TRUE,
    '#ajax' => array(
      'callback' => 'wisski_pb_path_step',
      'wrapper' => 'full_path',
    ),
  );
    
  $entries = wisski_salz_next_datatype_properties($last);
  
  $form['item']['correct'] = array(
      '#type' => 'hidden',
      '#value' => FALSE,
    );
  if (!empty($entries)) {
    $form['item']['correct']['#value'] = TRUE;
    $options = array('default' => '-choose-');
    foreach($entries as $entry) $options[$entry] = $entry;
    $form['item']['path']['datatype_property'] = array(
      '#type' => 'select',
      '#title' => 'datatype_property',
      '#options' => $options,
      '#default_value' => 'default',
      '#required' => TRUE,
    );
  }
  $external = array();
  foreach($superclasses as $superclass) {
    $external[$superclass] = db_select('wisski_pb_pathdata','paths')
                    ->fields('paths',array('uuid','short_name'))
                    ->condition('starting_concept',$superclass,'LIKE')
                    ->condition('pending',0,'=')
                    ->execute()
                    ->fetchAllAssoc('uuid');
  }
  if (!empty($external)) {
    $options = array('default' => '-choose-');
    foreach($external as $key => $res) {
      if (empty($res)) continue;
      $options[$key] = array();
      foreach($res as $uuid => $ext) {
        $options[$key][$uuid] = $ext->short_name;
      }
    }
    $form['item']['path']['external_path'] = array(
      '#type' => 'select',
      '#title' => 'Re-use existing path',
      '#options' => $options,
      '#default_value' => 'default',
    );
  }
  
/*
  $form['item']['enabled'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enabled'),
    '#default_value' => '',
    '#description' => t(''),
  );
*/

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
    '#submit' => array('wisski_pb_path_submit'),
    '#validate' => array('wisski_pb_validator'),
    '#weight' => 1002,
  );
  
  return $form;
}


function wisski_pb_path_step($form,&$form_state) {

  return $form['item']['path'];
}

function wisski_pb_path_submit($form, &$form_state) {

  $name = $form_state['values']['item']['name'];
  $fields = array(
      'short_name' => $name,
      'pending' => 0,
      'external_path' => isset($form_state['values']['item']['path']['external_path']) && $form_state['values']['item']['path']['external_path'] != 'default' ? $form_state['values']['item']['path']['external_path'] : NULL,
      'datatype_property' => isset($form_state['values']['item']['path']['datatype_property']) ? $form_state['values']['item']['path']['datatype_property'] : NULL,
    );
  if (!isset($form_state['values']['item']['uuid'])) {
    $uuid = wisski_core_make_uuid($name);
    $fields['uuid'] = $uuid;
    $fields['weight'] = 0;
    if (isset($form_state['values']['item']['starting_concept'])) {
      $fields['starting_concept'] = $form_state['values']['item']['starting_concept'];
    }
    db_insert('wisski_pb_pathdata')
      ->fields($fields)
      ->execute();
  } else {
    $uuid = $form_state['values']['item']['uuid'];
    db_update('wisski_pb_pathdata')
      ->fields($fields)
      ->condition('uuid',$uuid,'=')
      ->execute();
  }
  return $form;
}

function wisski_pb_validator($form,&$form_state) {

  if (!isset($form_state['values']['item']['correct']) || !$form_state['values']['item']['correct']) {
    form_set_error('item][path][step',"you must choose a class and a datatype property");
  }
  $boo = FALSE; 
  if (isset($form_state['values']['item']['path']['datatype_property']) && $form_state['values']['item']['path']['datatype_property'] == 'default') {
    if (isset($form_state['values']['item']['path']['external_path'])) {
      if ($form_state['values']['item']['path']['external_path'] == 'default') {
        $boo = TRUE;  
      }
    } else $boo = TRUE;
  }
  if ($boo) form_set_error('item][path][datatype_property',"Choose either a datatype property or an external path");
}

function wisski_core_make_uuid($concept,$path_name = '') {
  
  $now = time();
  return md5($concept.$now);
}

function wisski_core_pb_all_paths($form,&$form_state) {

  $form = array();
  $header = array(
    'starting_concept' => t('Starting Concept'),
    'title' => t('Title'),
    'path' => t('Path'),
  );
  $paths = db_select('wisski_pb_pathdata','paths')
            ->fields('paths')
            ->condition('pending',0,'=')
            ->execute()
            ->fetchAllAssoc('uuid');
  $options = array();
  foreach($paths as $path) {
    $path_array = unserialize($path->path_array);
    $current = $path;
    while ($current->external_path != NULL) {
      $current = $paths[$current->external_path];
      $path_array = array_merge($path_array,unserialize($current->path_array));
    }
    $item = $path->starting_concept;
    foreach ($path_array as $step) {
      $item .= ' ==> '.$step;
    }
    $item .= ' --> '.$current->datatype_property;
    $options[$path->uuid] = array(
      'title' => $path->short_name,
      'starting_concept' => $path->starting_concept,
      'path' => $item,
    );
  }
  
  
  $form['table'] = array(
    '#type' => 'tableselect',
    '#header' => $header,
    '#options' => $options,
    '#empty' => 'no data available',
    '#multiple' => FALSE,
  );
  $form['chosen'] = array(
    '#type' => 'hidden',
    '#default_value' => 0,
  );
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Edit Path'),
    '#submit' => array('wisski_core_pb_all_edit'),
    '#validate' => array('wisski_core_pb_all_validate'),
  );
  return $form;
}

function wisski_core_pb_all_validate($form,&$form_state) {

  dpm(func_get_args());
  if ($form_state['values']['table'] == '') {
    form_set_error('table',"choose a path to edit");
  } else {
    $form_state['values']['chosen'] = $form_state['values']['table'];
  }
}

function wisski_core_pb_all_edit($form,&$form_state) {
  
  $concept = $form['table']['#options'][$form_state['values']['chosen']]['starting_concept'];
  $entity = db_select('wisski_entity_bundles','bundles')
              ->fields('bundles')
              ->condition('label',$concept,'LIKE')
              ->execute()
              ->fetchObject();
  $form_state['redirect'] = array('wisski/navigate/'.$entity->type.'/edit_bundle');
}

function wisski_core_field_paths($form,&$form_state,$in_bundle = NULL) {
  
  $header = array(
    'field' => t('Field'),
    'path' => t('Connected Path'),
  );
  $connections = db_select('wisski_pb_fielddata','cons')
                    ->fields('cons')
                    ->execute()
                    ->fetchAllAssoc('instance_id');
  $path_info = db_select('wisski_pb_pathdata','paths')
                ->fields('paths')
                ->condition('pending','0','=')
                ->condition('starting_concept',$in_bundle->uri,'LIKE')
                ->execute()
                ->fetchAllAssoc('uuid');
  $options = array();
  $path_strings = array('default' => '-'.t('none').'-') + _wisski_core_extract_paths($path_info);
  if (isset($form_state['triggering_element']) && $form_state['triggering_element']['#name'] == 'wisski_core_field_paths_single_button') {
    $path_id = $form_state['values']['edited']['item']['selection'];
    $instance_id = $form_state['#chosen'];
    $returns = -1;
    if (array_key_exists($form_state['#chosen'],$connections)) {
      $returns = db_update('wisski_pb_fielddata')
        ->fields(array('path_id' => $path_id))
        ->condition('instance_id',$instance_id,'=')
        ->execute();
      $connections[$instance_id]->path_id = $path_id;
    } else {
      $returns = db_insert('wisski_pb_fielddata')
        ->fields(array('instance_id' => $instance_id, 'path_id' => $path_id))
        ->execute();
      $connections[$instance_id]->id = $returns;
      $connections[$instance_id]->instance_id = $instance_id;
      $connections[$instance_id]->path_id = $path_id;
    }
  }
  foreach (field_info_instances('wisski_core_entity',$in_bundle->type) as $instance) {
    $path = t('none');
    if (isset($connections[$instance['id']]) && $connections[$instance['id']]->path_id != NULL) {
      $path = $path_strings[$connections[$instance['id']]->path_id];
    }
    $options[$instance['id']] = array(
      'field' => $instance['label'].' ('.$instance['field_name'].')',
      'path' => $path,
    );
  }
  
  $form['edited'] = array(
    '#type' => 'container',
    '#prefix' => '<div id="wisski_core_field_paths_edited">',
    '#suffix' => '</div>',
    '#tree' => TRUE,
  );
  
  $form['edited']['table'] = array(
    '#type' => 'tableselect',
    '#header' => $header,
    '#options' => $options,
    '#empty' => 'No fields defined yet',
    '#multiple' => FALSE,
  );
  
//  dpm($form_state['triggering_element']['#name']);
  if (isset($form_state['triggering_element']) && ($form_state['triggering_element']['#name'] == 'wisski_core_field_paths_button' || $form_state['triggering_element']['#name'] == 'edited[item][selection]')) {
    $form['edited']['item'] = array(
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
      '#title' => t('Choose Path'),
      '#prefix' => '<div id="wisski_core_field_paths_item">',
      '#suffix' => '</div>',
    );
    $form['edited']['item']['selection'] = array(
      '#type' => 'select',
      '#title' => $options[$form_state['#chosen']]['field'],
      '#options' => $path_strings,
      '#default_value' => $connections[$form_state['#chosen']]->path_id,
      '#ajax' => array(
        'callback' => 'wisski_core_field_paths_edit_single',
        'wrapper' => 'wisski_core_field_paths_item',
      ),
    );
    $form['edited']['item']['submit'] = array(
      '#type' => 'button',
      '#value' => t('Save'),
      '#name' => 'wisski_core_field_paths_single_button',
      '#ajax' => array(
        'callback' => 'wisski_core_field_paths_edit',
        'wrapper' => 'wisski_core_field_paths_edited',
      ),
    );
  } else {
    $form['edited']['submit'] = array(
      '#type' => 'button',
      '#name' => 'wisski_core_field_paths_button',
      '#value' => t('Edit Connection'),
//    '#submit' => array('wisski_core_field_paths_edit'),
      '#validate' => array('wisski_core_field_paths_validate'),
      '#ajax' => array(
        'callback' => 'wisski_core_field_paths_edit',
        'wrapper' => 'wisski_core_field_paths_edited',
      ),
    );
  }
  return $form;
}

function wisski_core_field_paths_validate($form,&$form_state) {

  if($form_state['values']['edited']['table'] == '') {
    form_set_error('table',"choose a field to edit");
  } else {
    $form_state['#chosen'] = $form_state['values']['edited']['table'];
  }
}

function wisski_core_field_paths_edit($form,&$form_state) {
  
  return $form['edited'];
}

function wisski_core_field_paths_edit_single($form,&$form_state) {
  
  return $form['edited']['item'];
}

function _wisski_core_extract_paths($array) {

  $output = array();
  $single = FALSE;
  if (!is_array($array)) {
    $array = array($array);
    $single = TRUE;
  }
  foreach($array as $key => $path) {
    $out = $path->short_name.': ';
    do {
      foreach(unserialize($path->path_array) as $step) {
        $out .= $step.' => ';
      }
    } while (isset($path->external_path) && isset($array[$path->external_path]) && $path = $array[$path->external_path]);
    $out .= $path->datatype_property;
    $output[$key] = $out;
  }
  if ($single) return current($output);
  return $output;
}