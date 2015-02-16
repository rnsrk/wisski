<?php

function wisski_core_make_path_array($instance_id) {

  $result = &drupal_static(__FUNCTION__.'result'.$instance_id);
  if (isset($result)) return $result;
  $path_id = &drupal_static(__FUNCTION__.'inst'.$instance_id);
  if (!isset($path_id)) {  
    $field_path = db_select('wisski_pb_fielddata','con')
                      ->fields('con')
                      ->condition('instance_id',$instance_id)
                      ->execute()
                      ->fetchAssoc('instance_id');
    if (!empty($field_path)) $path_id = $field_path['path_id'];
  }
  if (!empty($path_id)) {
    $path_info = &drupal_static(__FUNCTION__.'path_info');
    if (!isset($path_info)) {
      $path_info = db_select('wisski_pb_pathdata','path')
                  ->fields('path')
                  ->condition('pending',0)
                  ->execute()
                  ->fetchAllAssoc('uuid');
    }
    if (!empty($path_info[$path_id])) {
      $path = $path_info[$path_id];
      $starting_concept = $path->starting_concept;
      $full_path = array();
      do {
        $full_path = array_merge($full_path,unserialize($path->path_array));
        $data_prop = $path->datatype_property;
      } while(isset($path_info[$path->external_path]) && $path = $path_info[$path->external_path]);
      if (count($full_path) % 2) watchdog('wisski_FATAL_ERROR','incorrect path for instance '.$instance_id);
      $result = array($starting_concept,$full_path,$data_prop);
      return $result;
    }
  }
}

function wisski_core_edit_bundle_paths($form,&$form_state,$in_bundle = NULL) {

  if (isset($form_state['storage']['bundle'])) {
    $bundle = $form_state['storage']['bundle'];
  } else {
    if ((($in_bundle instanceof WisskiCoreBundle) && $bundle = $in_bundle) || $bundle = entity_load_single('wisski_core_bundle',$in_bundle)) $form_state['storage']['bundle'] = $bundle;
    else throw new InvalidArgumentException('Given Argument is not a bundle name');
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
  $full_count = 1;
  foreach($entries as $key => $entry) {
    if (is_array($entry)) {
      $options[$key] = array();
      $full_count++;
      $superclasses[$key] = $key;
      foreach($entry as $prop) {
        $options[$key][$prop] = $prop;
        $full_count++;
      }
    } else {
      $options[$entry] = $entry;
      $full_count++;
    }
  }
  $form['item']['path']['step'] = array(
    '#type' => 'select',
    '#title' => '',
    '#options' => $options,
    '#default_value' => 'default',
    '#size' => $full_count > 15 ? 15 : $full_count,
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
  foreach (field_info_instances('wisski_individual',$in_bundle->type) as $instance) {
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

function array_extract_key(array $array, $key) {

  $out = array();
  foreach ($array as $pos => $subarray) {
    if (isset($subarray[$key])) $out[$pos] = $subarray[$key];
  }
  return $out;
}

function array_change_key($array, $key) {
  
  if (empty($array)) return array();
  $out = array();
  foreach($array as $subarray) {
    if (isset($subarray[$key])) $out[$subarray[$key]] = $subarray;
  }
  return $out;
}

function array_compress($array) {

  if (!is_array(current($array))) return;
  $keys = array_keys(current($array));
  $out = array();
  $out['keys'] = $keys;
  $out['data'] = array();
  foreach ($array as $pos => $info) {
    $out['data'][$pos] = array();
    foreach ($keys as $index => $key) {
      $out['data'][$pos][$index] = $info[$key];
    }
  }
  return $out;
}

function array_expand($array) {
  
  $keys = $array['keys'];
  $out = array();
  foreach ($array['data'] as $pos => $data) {
    foreach ($keys as $index => $key) {
      $out[$pos][$key] = $array['data'][$pos][$index];
    }
  }
  return $out;

}

function array_flatten(array $array) {

  $out = array();
  foreach($array as $value) {
    if (is_array($value)) $out = array_merge($out,array_flatten($value));
    else $out[] = $value;
  }
  return $out;
}

function wisski_core_make_short_title($field_data,$short_title_pattern) {
  
//  dpm(func_get_args());
  if (!is_array($field_data)) {
    $field_data = (array) $field_data;  
    foreach ($field_data as $key => $value) {
      if (is_array($value)) $field_data[$key] = array_flatten($value);
    }
  }
  $title = '';
  $empty = TRUE;
  foreach ($short_title_pattern as $elem) {
    if ($elem['use']) {
      if ((isset($field_data[$elem['name']]) && $entry = $field_data[$elem['name']]) || (isset($field_data[$elem['id']]) && $entry = $field_data[$elem['id']])) {
//        dpm(array('elem' => $elem, 'entry' => $entry));
        if (is_array($entry)) {
          if (empty($entry)) {
            if ($elem['obligatory']) return FALSE;
            $title .= $elem['fallback'];
          } elseif (trim(implode('',$entry)) === '') $title .= $elem['fallback'];
          else {
            $empty = FALSE;
            $title .= $elem['prefix'].array_shift($entry);
            $i = 1;
            while(!empty($entry) && ($elem['number'] == -1 || $i < $elem['number'])) {
              $title .= $elem['delimiter'].array_shift($entry);
              $i++;
            }
            $title .= $elem['suffix'];
          }
        } else {
          if (trim($entry) === '') $title .= $elem['fallback'];
          else {
            $empty = FALSE;
            $title .= $elem['prefix'].$entry.$elem['suffix'];
          }
        }
      } else {
        if ($elem['obligatory']) return FALSE;
        $title .= $elem['fallback'];
      }
    }
  }
  if ($empty) return FALSE;
  return $title;
}

function wisski_core_short_titles_form($form,&$form_state,$in_bundle = NULL) {

  if ((($in_bundle instanceof WisskiCoreBundle) && $bundle = $in_bundle) || 
    ($bundle = entity_load_single('wisski_core_bundle',$in_bundle))) $form_state['storage']['bundle'] = $bundle;
  else {
    throw new InvalidArgumentException('Given Argument is not a bundle name');
  }
  
  $form_state['storage']['bundle'] = $bundle;
  
  $title_order = variable_get('wisski_core_short_titles_order',FALSE);
  if (!$title_order) {
    dpm('load short title pattern from DB');
    $result = db_select('wisski_entity_bundles','bund')
              ->fields('bund',array('type','uri','short_title_pattern'))
              ->condition('uri',$bundle->uri)
              ->execute()
              ->fetchAssoc();

    if (isset($result['short_title_pattern'])) {
      $title_order = unserialize($result['short_title_pattern']);
      $title_order = array_expand($title_order);
    }
    $last = end($title_order);
    $i = $last['weight'] + 1;
    //re-key the title order to field instance ids
    $title_order = array_change_key($title_order,'name');
    $instance_info = field_info_instances('wisski_individual',$bundle->type);
    $title_order = array_intersect_key($title_order,$instance_info);
    foreach ($instance_info as $name => $field_info) {
      if (isset($title_order[$name]) && $info = &$title_order[$name]) {
        $info['title'] = $field_info['label'];
        $info['use'] = TRUE;
        if (!isset($info['single'])) {
          $field = field_info_field($name);
          $info['single'] = $field['cardinality'] === '1';
        }
        if (!isset($info['obligatory'])) $info['obligatory'] = FALSE;
        if (!isset($info['number'])) {
          $info['number'] = $info['single'] ? 1 : -1;
          $info['delimiter'] = $info['single'] ? '' : ', ';
        }
      } else {
        $field = field_info_field($field_info['field_name']);
        $single = $field['cardinality'] == '1';
        $title_order[$name] = array(
          'id' => $field_info['id'],
          'title' => $field_info['label'],
          'name' => $name,
          'weight' => $i,
          'prefix' => ' ',
          'suffix' => '',
          'use' => FALSE,
          'fallback' => '-',
          'obligatory' => FALSE,
          'single' => $single,
          'number' => $single ? 1 : -1,
          'delimiter' => $single ? '' : ', ',
        );
        $i++;
      }
    }
    //change keys back to original
    $title_order = array_change_key($title_order,'weight');
    ksort($title_order);
  }
  if (!isset($title_order)) {
    $title_order = array();
    $i = 0;
    foreach (field_info_instances('wisski_individual',$bundle->type) as $field_info) {
      $field = field_info_field($field_info['field_name']);
      $single = $field['cardinality'] == '1';
      $title_order[$i] = array(
        'id' => $field_info['id'],
        'name' => $field_info['label'],
        'weight' => $i,
        'prefix' => '',
        'suffix' => ' ',
        'use' => TRUE,
        'fallback' => '-',
        'obligatory' => FALSE,
        'single' => $single,
        'number' => $single ? 1 : -1,
        'delimiter' => $single ? '' : ', ',
      );
      $i++;
    }
  }
  
  $form_state['storage']['title_order'] = $title_order;

  $form['example'] = array(
    '#type' => 'fieldset',
    '#tree' => TRUE,
    '#weight' => -1,
    '#prefix' => '<div id="short-titles-example">',
    '#suffix' => '</div>',
  );
  
  $form['example']['entity'] = array(
    '#type' => 'fieldset',
    '#tree' => TRUE,
    '#title' => t('Fields'),
  );

  $example_entity = variable_get('wisski_core_short_titles_example_entity',FALSE);
  foreach ($title_order as $elem) {
    $key = $elem['name'];
    if (!$example_entity || !isset($example_entity[$key])) {
      if($elem['single']) {
        $example_entity[$key] = t(preg_replace('/[^a-zA-Z0-9]/','',substr($elem['title'],0,5)));
      } else {
        $example_entity[$key][] = t(preg_replace('/[^a-zA-Z0-9]/','',substr($elem['title'],0,5))).rand(100,999);
        $example_entity[$key][] = t(preg_replace('/[^a-zA-Z0-9]/','',substr($elem['title'],0,5))).rand(100,999);
      }
    }
    if ($elem['single']) {
      $form['example']['entity'][$key] = array(
        '#type' => 'textfield',
        '#title' => $elem['title'],
        '#default_value' => $example_entity[$key],
      );
    } else {
      foreach($example_entity[$key] as $pos => $entry) {
        $form['example']['entity'][$key][$pos] = array(
          '#type' => 'textfield',
          '#title' => $elem['title'],
          '#default_value' => $entry,
        );
      }
    }      
  }
//  drupal_set_message("aus der form: ");
//  dpm($example_entity);
//  dpm($form_state['input']);
//  dpm($form);
  
  $example_title = wisski_core_make_short_title($example_entity,$title_order)?:variable_get('wisski_titles_master_fallback','-!- '.t('invalid title').' -!-');
  $form['example']['#title'] = t('Example Entity: ')."'$example_title'";
  
  $form['titles'] = array(
    '#tree' => TRUE,
  );
  
  foreach ($title_order as $item) {
    
    if ($item['single']) {
      $options = array(1 => 1);
    } else {
      $options = array(
        -1 => 'all',
        1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5,
      );
    }
      
    $disable = !$item['use'];
    
    $form['titles'][$item['name']] = array(
    
      
      'use' => array(
        '#type' => 'checkbox',
        '#default_value' => !$disable,
      ),

      'prefix' => array(
        '#type' => 'textfield',
        '#size' => 10,
        '#maxlenght' => 8,
        '#default_value' => $item['prefix'],
        '#disabled' => $disable,
      ),
      
      'name' => array(
        '#markup' => check_plain($item['title']),
      ),
      
      'suffix' => array(
        '#type' => 'textfield',
        '#size' => 10,
        '#maxlenght' => 8,
        '#default_value' => $item['suffix'],
        '#disabled' => $disable,
      ),

      'fallback' => array(
        '#type' => 'textfield',
        '#size' => 10,
        '#maxlenght' => 8,
        '#default_value' => $item['fallback'],
        '#disabled' => $disable,
      ),
      
      'obligatory' => array(
        '#type' => 'checkbox',
        '#default_value' => $item['obligatory'],
        '#disabled' => $disable,
      ),
      
      'number' => array(
        '#type' => 'select',
        '#options' => $options,
        '#default_value' => $item['number'],
        '#disabled' => $disable,
      ),

      'delimiter' => array(
        '#type' => 'textfield',
        '#size' => 10,
        '#maxlength' => 8,
        '#default_value' => $item['delimiter'],
        '#disabled' => $disable,
      ),
  
      'weight' => array(
        '#type' => 'weight',
        '#title' => t('Weight'),
        '#default_value' => $item['weight'],
        '#delta' => 10,
        '#title_display' => 'invisible',
      ),
    );
  }

  $form['actions'] = array('#type' => 'actions');
  $form['actions']['apply'] = array('#type' => 'submit', '#value' => t('Apply'), '#submit' => array('wisski_core_short_titles_form_apply'));
  $form['actions']['cancel'] = array('#type' => 'submit', '#value' => t('Cancel'), '#submit' => array('wisski_core_short_titles_form_cancel'));
  $form['actions']['submit'] = array('#type' => 'submit', '#value' => t('Save Changes'), '#submit' => array('wisski_core_short_titles_form_exit'));
//  dpm($form);
  return $form;
}

function theme_wisski_core_short_titles_form($variables) {
  $form = $variables['form'];

  $rows = array();

  foreach (element_children($form['titles']) as $id) {

    $form['titles'][$id]['weight']['#attributes']['class'] = array('title-weight');

    $rows[] = array(
      'data' => array(
        drupal_render($form['titles'][$id]['use']),
        drupal_render($form['titles'][$id]['prefix']),
        drupal_render($form['titles'][$id]['name']),
        drupal_render($form['titles'][$id]['suffix']),
        drupal_render($form['titles'][$id]['fallback']),
        drupal_render($form['titles'][$id]['obligatory']),
        drupal_render($form['titles'][$id]['number']),
        drupal_render($form['titles'][$id]['delimiter']),
        drupal_render($form['titles'][$id]['weight']),
      ),
      'class' => array('draggable'),
    );
  }

  $header = array(t('Use'),t('Prefix'),t('Field'),t('Suffix'),t('Fallback'),t('Required'),t('Maximum Number'),t('Delimiter'), t('Weight'));

  $table_id = 'wisski_core_short_titles_table';

  $output = theme('table', array(
    'header' => $header,
    'rows' => $rows,
    'attributes' => array('id' => $table_id),
  ));

  $output .= drupal_render_children($form);

  drupal_add_tabledrag($table_id, 'order', 'sibling', 'title-weight');

  return $output;
}

function wisski_core_short_titles_form_exit($form, &$form_state) {

  $new_title_order = array();
  foreach($form_state['storage']['title_order'] as $values) {
    if ($form_state['values']['titles'][$values['name']]['use']) {
      $new_values = $values;
      foreach($form_state['values']['titles'][$values['name']] as $key => $value) {
        if ($key !== 'use' && $key !== 'single') $new_values[$key] = $value;
      }
      $new_title_order[$new_values['weight']] = $new_values;
    }
  }
  ksort($new_title_order);
  $bundle = $form_state['storage']['bundle'];
  db_update('wisski_entity_bundles')
    ->fields(array('short_title_pattern' => serialize(array_compress($new_title_order))))
    ->condition('uri',$bundle->uri)
    ->execute();
  variable_del('wisski_core_short_titles_order');
  variable_del('wisski_core_short_titles_example_entity');
  $form_state['redirect'] = 'admin/structure/wisski_core_bundle/manage/'.$bundle->type;
}

function wisski_core_short_titles_form_apply($form,&$form_state) {

  $new_title_order = array();
  dpm($form_state['values']['titles']);
  dpm($form_state['storage']['title_order']);
  foreach($form_state['storage']['title_order'] as $values) {
    $new_values = $values;
    foreach($form_state['values']['titles'][$values['name']] as $key => $value) {
      $new_values[$key] = $value;
    }
    $new_title_order[$new_values['weight']] = $new_values;
  }
  ksort($new_title_order);
  variable_set('wisski_core_short_titles_order',$new_title_order);
  variable_set('wisski_core_short_titles_example_entity',$form_state['values']['example']['entity']);
}

function wisski_core_short_titles_form_cancel($form,&$form_state) {
  dpm($form_state);
  variable_del('wisski_core_short_titles_order');
  variable_del('wisski_core_short_titles_example_entity');
  $bundle = $form_state['storage']['bundle'];
  $form_state['redirect'] = 'admin/structure/wisski_core_bundle/manage/'.$bundle->type;
}