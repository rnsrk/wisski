<?php

function wisski_core_make_path_array(array $settings) {

  $select = db_select('wisski_pb_pathdata','path')
                  ->fields('path')
                  ->condition('pending',0);
  if (isset($settings['field_info']['field_name'])) {
    $select = $select->condition('connected_field',$settings['field_info']['field_name']);
  }
  if (isset($settings['path_id'])) {
    $select = $select->condition('uuid',$settings['path_id']);
  }
  if (isset($settings['field_info']['mapping'])) {
    $select = $select->condition('connected_field_property',$settings['field_info']['mapping']);
  }
  $select = $select->execute();  
  $paths = array();
  while ($path = $select->fetchObject()) {
    $uuid = $path->uuid;
    $starting_concept = $path->starting_concept;
    $data_prop = $path->datatype_property;
    $connected_bundle = $path->connected_bundle;
    $connected_field = $path->connected_field;
    $connected_field_property = $path->connected_field_property;
    $disamb = $path->disamb;
    $full_path = unserialize($path->path_array);
    if (!empty($path->external_path)) {  
      while(!empty($path->external_path)) {
        $path = db_select('wisski_pb_pathdata','p')
                ->fields('p')
                ->condition('uuid',$path->external_path)
                ->condition('pending',0)
                ->execute()
                ->fetchObject();
        if (empty($path)) break;
        $full_path = array_merge($full_path,unserialize($path->path_array));
        $data_prop = $path->datatype_property;
      }
    }
    $paths[$connected_field][$connected_field_property] = array(
      'starting_concept' => $starting_concept,
      'full_path' => $full_path,
      'datatype_property' => $data_prop,
      'connected_bundle' => $connected_bundle,
      'connected_field' => $connected_field,
      'connected_field_property' => $connected_field_property,
      'disamb' => isset($disamb) ? $disamb : NULL,
      'uuid' => $uuid,
    );
  }
  while (count($paths) == 1) {
    $paths = current($paths);
  }
  return $paths;
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
      '#collapsed' => TRUE,
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
  
  $instances = field_info_instances('wisski_individual',$in_bundle->type);
  $path_info = db_select('wisski_pb_pathdata','paths')
                ->fields('paths')
                ->condition('pending','0','=')
                ->condition('connected_bundle',$in_bundle->type,'LIKE')
                ->execute()
                ->fetchAllAssoc('uuid');
  $items = array();
  foreach($path_info as $info) {
    if (isset($instances[$info->connected_field]) && $ins = $instances[$info->connected_field]) {
      $label = $ins['field_name'];
      if (isset($ins['label'])) $label = $ins['label'].' ('.$label.')';
      $items[] = l($label,'admin/structure/wisski_core_bundle/manage/'.$in_bundle->type.'/'.$ins['field_name'].'/choose');
      unset($instances[$info->connected_field]);
    } else {
      $items[] = l($info->short_name,'admin/structure/wisski_core_bundle/manage/'.$in_bundle->type.'/no_field/choose/'.$info->uuid);
    }
  }
  foreach($instances as $ins) {
    $label = $ins['field_name'];
    if (isset($ins['label'])) $label = $ins['label'].' ('.$label.')';
    $label .= ' - '.t('no path defined').' - ';
    $items[] = l($label,'admin/structure/wisski_core_bundle/manage/'.$in_bundle->type.'/'.$ins['field_name'].'/choose');
  }
  return array(
    'list' => array(
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => t('Choose Field/Path to manipulate'),
    ),
  );
}

/*
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
*/

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
    $out = '<b>'.$path->short_name.'</b>: ';
    do {
      foreach(unserialize($path->path_array) as $step) {
        $out .= $step.' => ';
      }
    } while (isset($path->external_path) && isset($array[$path->external_path]) && $path = $array[$path->external_path]);
    $out .= '<b>'.$path->datatype_property.'</b>';
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
//  dpm(array(__FUNCTION__=>func_get_args(),'result' => $title));
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
  $form_state['redirect'] = array('admin/structure/wisski_core_bundle/manage/'.$bundle->type);
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
  $form_state['redirect'] = array('admin/structure/wisski_core_bundle/manage/'.$bundle->type);
}


///////////////////////////////
//
// DATA MAPPING
//
///////////////////////////////
function wisski_core_data_mapping_form($form,$form_state,$bundle,$field_name) {
  
  $field = field_info_field($field_name);
  $instance = field_info_instance('wisski_individual',$field_name,$bundle->type);
  $module = $field['module'];
  
  $schema = module_invoke($module,'field_schema',$field,$instance);
  $mapping_keys = array_keys($schema['columns']);
  
  $result = db_select('wisski_pb_pathdata','p')
            ->fields('p')
            ->condition('pending',0)
            ->condition('connected_bundle',$bundle->type)
            ->condition('connected_field',$field_name)
            ->execute();
  $options = array();
  while($elem = $result->fetchObject()) {
    $path_array = wisski_core_make_path_array(array('path_id'=>$elem->uuid));
    $starting_concept = $path_array['starting_concept'];
    $full_path = $path_array['full_path'];
    $datatype_property = $path_array['datatype_property'];
    $options[$elem->uuid] = array();
    if (isset($elem->connected_field_property)) {
      $options[$elem->uuid]['mapping'] = $elem->connected_field_property;
      $options[$elem->uuid]['edit'] = l(t('Edit'),'admin/structure/wisski_core_bundle/manage/'.$bundle->type.'/'.$field_name.'/mapping/'.$elem->connected_field_property);
      $mapping_keys = array_diff($mapping_keys,array($elem->connected_field_property));
    } else {
      $options[$elem->uuid]['mapping'] = '';
      $options[$elem->uuid]['edit'] = l(t('Edit'),'admin/structure/wisski_core_bundle/manage/'.$bundle->type.'/'.$field_name.'/mapping/unspecified');
    }
    if (isset($elem->short_name)) $options[$elem->uuid]['short_name'] = $elem->short_name;
    else $options[$elem->uuid]['short_name'] = '';
    $options[$elem->uuid]['full_path'] = implode(' => ',$full_path);
    $options[$elem->uuid]['datatype_property'] = $datatype_property;
  }
  foreach ($mapping_keys as $map) {
    //only unspecified mapping keys
    $options[] = array(
      'mapping' => $map,
      'full_path' => ' - '.t('specify').' - ',
      'short_name' => '',
      'datatype_property' => '',
      'edit' => l(t('Edit'),'admin/structure/wisski_core_bundle/manage/'.$bundle->type.'/'.$field_name.'/mapping/'.$map),
    );
  }
  $header = array(
    'short_name' => t('Name'),
    'mapping' => t('Mapped Field Value'),
    'full_path' => t('Path'),
    'datatype_property' => t('Datatype Property'),
    'edit' => '',
  );
  $rows = array();
  foreach($options as $opt) {
    $row = array();
    foreach(array_keys($header) as $key) {
      $row[] = $opt[$key];
    }
    $rows[] = $row;
  }
  return array(
    'table' => array(
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
      '#empty' => t('No paths defined yet'),
      '#title' => t('Choose Field/Path to manipulate'),
    ),
  );
}

function wisski_core_data_mapping_form_callback_settings($form,$form_state) {
  return $form['settings'];
}

function wisski_core_single_path_form($form,$form_state,$bundle,$field_name,$map) {
  
//  dpm(array(__FUNCTION__=>func_get_args()));
  variable_set('wisski_throw_exceptions',TRUE);
  $set = FALSE;
  if (isset($form_state['storage'])) {
    if (isset($form_state['storage']['full_path'])) {
      $full_path = $form_state['storage']['full_path'];
      if (isset($form_state['storage']['datatype_property'])) {
        $datatype_property = $form_state['storage']['datatype_property'];
        if (isset($form_state['storage']['starting_concept'])) {
          $starting_concept = $form_state['storage']['starting_concept'];
          $set = TRUE;
        }
      }
    }
  }
  if (!$set) {
    if (!isset($form_state['storage'])) $form_state['storage'] = array();
    $select = db_select('wisski_pb_pathdata','p')
          ->fields('p')
          ->condition('pending',0)
          ->condition('connected_field',$field_name)
          ->condition('connected_bundle',$bundle->type);
    if ($map !== 'unspecified') {
      $select = $select->condition('connected_field_property',$map);
    }
    $select = $select->execute();
    if ($select->rowCount() === 1) {
      $path = $select->fetchObject();
      $starting_concept = $path->starting_concept;
      $full_path = unserialize($path->path_array);
      $datatype_property = $path->datatype_property;
      $form_state['storage']['uuid'] = $path->uuid;
      if (isset($path->external_path)) {
        $datatype_property = 'external';
        $external_path = wisski_core_make_path_array(array('path_id' => $path->external_path));
        $form_state['storage'] += array('external_path' => $external_path);
      }  
    } else {
      $starting_concept = $bundle->uri;
      $full_path = array();
      $datatype_property = 'empty';
    }
    $form_state['storage'] += array(
      'full_path' => $full_path,
      'datatype_property' => $datatype_property,
      'starting_concept' => $starting_concept,
      'bundle_name' => $bundle->type,
      'field_name' => $field_name,
      'map' => $map,
    );
  }
  
  $selected_row = -1;
  $selected_options = array();
  if (!isset($external_path) && isset($form_state['storage']['external_path'])) $external_path = $form_state['storage']['external_path'];
  dpm($full_path);
  if (isset($form_state['triggering_element']) && $form_state['triggering_element']['#name'] === 'single_path_remove') {
    //remove
    dpm($form_state);
    $sel = explode(':',$form_state['values']['table'],2);
    $sel = $sel[1];
    if($sel === 'bottom') {
      unset($external_path);
      $datatype_property = 'empty';
    } elseif ($sel !== 'new') {
      unset($external_path);
      $full_path = array_slice($full_path,0,$sel);
      $form_state['storage']['full_path'] = $full_path;
      $datatype_property = 'empty';
    }
  }
  if (isset($form_state['triggering_element']) && $form_state['triggering_element']['#name'] === 'single_path_button') {
    //select
    if (isset($form_state['storage']['selected_row'])) {
      $sel = $form_state['storage']['selected_row'];
      dpm(array('sel'=>$sel,'input'=>$form_state['input']));
      if($sel === 'bottom') {  
//        $datatype_property = $form_state['input']['step:datatype_property'];
//        $form_state['storage']['datatype_property'] = $datatype_property;
        if (isset($form_state['input']['step:external'])) {
          $explode = explode(':',$form_state['input']['step:external'],2);
          if ($explode[0] == 'external') {
            $external_path = wisski_core_make_path_array(array('path_id' => $explode[1]));
          } else {
            trigger_error('Unexpected Problem: "'.$form_state['input']['external'].'" is not formatted correctly',E_USER_WARNING);
            if (isset($external_path)) unset($external_path);
          }
        } else {
          if (isset($external_path)) unset($external_path);
        }
        if (isset($form_state['input']['step:datatype_property'])) {
          $datatype_property = $form_state['input']['step:datatype_property'];
        }
      } elseif ($sel === 'new' && isset($form_state['input']['adds:new']) && $form_state['input']['adds:new'] !== 'empty') {
        $full_path[] = $form_state['input']['adds:new'];
        $form_state['storage']['full_path'] = $full_path;
        $datatype_property = 'empty';
      } elseif (isset($form_state['input']['step:'.$sel])) {
        if (isset($full_path[$sel])) {
          if ($full_path[$sel] !== $form_state['input']['step:'.$sel]) {
            unset($external_path);
            $datatype_property = 'empty';
          }
        }
        $full_path[$sel] = $form_state['input']['step:'.$sel];
        $form_state['storage']['full_path'] = $full_path;
        if((int)$sel === count($full_path) - 1) {
          $datatype_property = 'empty';
        }
      }
    }
    $selection = explode(':',$form_state['values']['table'],2);
    $selected_row = $selection[1];
    $form_state['storage']['selected_row'] = $selected_row;
    if (isset($external_path)) {
      $form_state['storage']['external_path'] = $external_path;
    } elseif (isset($form_state['storage']['external_path'])) {
      unset($form_state['storage']['external_path']);
    }
    if ($selected_row === 'bottom') {  
      $elem = empty($full_path) ? $starting_concept : end($full_path);
      $selected_options = wisski_salz_next_datatype_properties($elem);
      if (count($full_path) % 2 === 0) {
        $external_options = wisski_core_pb_get_external_paths($elem);
      }
    } elseif ($selected_row === 'new' && empty($external_path)) {
      $elem = empty($full_path) ? $starting_concept : end($full_path);
      $selected_options = wisski_salz_next_steps($elem);
      if (count($full_path) % 2 === 0) {
        $external_options = wisski_core_pb_get_external_paths($elem);
      }
    } else {
      if (((int)$selected_row) === 0) {
        $before = $starting_concept;
      } else {
        $before = $full_path[$selected_row-1];
      }
      if (((int)$selected_row) < count($full_path) - 2) {
        $selected_options = wisski_salz_next_steps($before,$full_path[$selected_row+1]);
      } elseif (((int)$selected_row) === count($full_path) - 2) {
        if (isset($external_path)) {
          $selected_options = wisski_salz_next_steps($before,$external_path['starting_concept']);
        } else {
          $selected_options = wisski_salz_next_steps($before,$full_path[$selected_row+1]);
        }
      } else {
        $selected_options = wisski_salz_next_steps($before);
        if (((int)$selected_row) % 2 === 0) {  
          $external_options = wisski_core_pb_get_external_paths($before);
        }
      }
    }
//    dpm(array('selected_row'=>$selected_row,'selected_options'=>$selected_options));
  }
  dpm($full_path);
  $rows = array();
  $empty_options = array('empty' => ' - '.t('select').' -');
  $rows['starting_concept'] = array('step' => '<b>'.t('Starting Concept').'</b>: '.$starting_concept);
  foreach($full_path as $key => $entry) {
    if ($selected_row !== 'new' && $selected_row !== 'bottom' && ((int)$selected_row) == $key) {
      $rows['step:'.$key] = array(
        'step' => array(
          'data' => array(
            '#type' => 'select',
            '#options' => $empty_options + $selected_options,
            '#name' => 'step:'.$key,
            '#value' => $entry,
          ),
        ),
      );
      if (count($full_path) === $key+1) {
        if ($datatype_property !== 'empty') $rows['step:'.$key]['step']['data']['#title'] = '! '.t('Datatype Property will be removed, if you change this');
        if (isset($external_path)) $rows['step:'.$key]['step']['data']['#title'] = '! '.t('Re-Used Path will be removed, if you change this');
      }
    } else {
      $rows['step:'.$key] = array('step' => $entry);
    }
  }
  $bottom_line = array();
  if(!empty($external_path)) {
    $str = '<b>'.t('Re-Used Path').':</b> ';
    $str .= $external_path['connected_bundle'].' '.$external_path['connected_field'].'-'.$external_path['connected_field_property'];
    foreach($external_path['full_path'] as $p) {
      $str .= ' -> '.$p;
    }
    $str .= ' => '.$external_path['datatype_property'];
    $bottom_line['external'] = array(
      '#markup' => $str,
    );
  } else {
    if ($selected_row === 'new') {
      $rows['adds:new'] = array(
        'step' => array(
          'data' => array(
            '#type' => 'select',
            '#options' => $empty_options + $selected_options,
            '#name' => 'adds:new',
          ),
        ),
      );
      if ($datatype_property !== 'empty') $rows['adds:new']['step']['data']['#title'] = '! '.t('Datatype Property will be removed, if you change this');
    } else {
      $rows['adds:new'] = array('step' => '<b>'.t('NEW').'</b>: '.t('Add new step'));
    }
  }
  if ($selected_row === 'bottom') {
    if (!empty($external_options)) {
      $bottom_line['external'] = array(
        '#type' => 'select',
        '#options' => $empty_options + $external_options,
        '#title' => '<b>'.t('Re-Use Existing Path').'</b>',
        '#name' => 'step:external',
        '#value' => isset($external_path) ? 'external:'.$external_path['uuid'] : 'empty',
      );
      if (count($full_path) === $key+1) {
        if ($datatype_property !== 'empty') $bottom_line['external']['#title'] .= ' ! '.t('Datatype Property will be removed, if you change this');
      }
    }
    $bottom_line['datatype_property'] = array(
      '#type' => 'select',
      '#options' => $empty_options + $selected_options,
      '#title' => '<b>'.t('Datatype Property').'</b>',
      '#name' => 'step:datatype_property',
      '#value' => $datatype_property,
    );
  } elseif (count($full_path) % 2 === 0) {
    $text = $datatype_property === 'empty' ? '('.t('not set').')' : $datatype_property;
    if (!isset($external_path)) $bottom_line['datatype_property'] = array('#markup' => '<b>'.t('Datatype Property').'</b>: '.$text);
  }
  if (!empty($bottom_line)) {
    $rows['step:bottom'] = array('step'=>array('data'=>array('#type'=>'container')+$bottom_line));
  }
  $form['table'] = array(
    '#type' => 'tableselect',
    '#options' => $rows,
    '#tree' => TRUE,
    '#header' => array('step' => t('Step')),
    '#multiple' => FALSE,
    '#prefix' => '<div id=wisski-core-single-path-table>',
    '#suffix' => '</div>',
    '#name' => 'single_path_table',
    '#default_value' => 'starting_concept',
  );
  $form['op'] = array(
    '#name' => 'single_path_button',
    '#type' => 'button',
    '#value' => t('Edit Selected'),
    '#ajax' => array(
      'wrapper' => 'wisski-core-single-path-table',
      'callback' => 'wisski_core_single_path_callback',
    ),
    '#validate' => array('wisski_core_single_path_validate'),
  );
  $form['remove'] = array(
    '#name' => 'single_path_remove',
    '#type' => 'button',
    '#value' => t('Remove from here'),
    '#ajax' => array(
      'wrapper' => 'wisski-core-single-path-table',
      'callback' => 'wisski_core_single_path_callback',
    ),
    '#validate' => array('wisski_core_single_path_validate'),
  );
  $form['submit'] = array(
    '#name' => 'single_path_submit',
    '#type' => 'submit',
    '#value' => t('Save this path'),
    '#validate' => array('wisski_core_single_path_validate'),
  );
  variable_set('wisski_throw_exceptions',FALSE);
//  dpm($form);
  return $form;
}

function wisski_core_single_path_callback($form,$form_state) {
  
  return $form['table'];
}

function wisski_core_single_path_form_submit($form,&$form_state) {

  $storage = $form_state['storage'];
  $path_info = array(
    'path_array' => serialize($storage['full_path']),
    'short_name' => isset($storage['short_name']) ? $storage['short_name'] : $storage['field_name'],
    'datatype_property' => isset($storage['datatype_property']) && $storage['datatype_property'] !== 'empty' ? $storage['datatype_property'] : NULL,
    'external_path' => isset($storage['external_path']['uuid']) ? $storage['external_path']['uuid'] : NULL,
    'starting_concept' => $storage['starting_concept'],  
    'connected_field' => $storage['field_name'],
    'connected_field_property' => $storage['map'],
    'connected_bundle' => $storage['bundle_name'],
  );
  if (isset($storage['uuid'])) {
    db_update('wisski_pb_pathdata')
      ->fields($path_info)
      ->condition('uuid',$storage['uuid'])
      ->execute();
  } else {
    $path_info += array('uuid' => wisski_core_make_uuid($storage['starting_concept']));
    db_insert('wisski_pb_pathdata')
      ->fields($path_info)
      ->execute();
  }
  $form_state['redirect'] = array('admin/structure/wisski_core_bundle/manage/'.$storage['bundle_name'].'/'.$storage['field_name'].'/choose');
}

function wisski_core_single_path_validate($form,$form_state) {
  
  $explode = explode(':',$form_state['values']['table'],2);
  if($explode[0] !== 'step' && $explode[0] !== 'adds') {
    form_set_error('single_path_table',t('Choose a concept or property to modify'));
  }
//  dpm($form_state);
  if ($explode[0] === 'step' && $explode[1] === 'bottom') {
    $set = 0;
    if (isset($form_state['input']['step:datatype_property'])) {
      $set += ($form_state['input']['step:datatype_property'] !== 'empty');
    }
    if (isset($form_state['input']['step:external'])) {
      $set += $set && ($form_state['input']['step:external'] !== 'empty');
    }
    if ($set > 1) form_set_error('single_path_table][bottom',t('Choose either a datatype property or an external path'));
  }
}

function wisski_core_pb_get_external_paths($concept) {
  
  $return = array();
  $result = db_select('wisski_pb_pathdata','p')
              ->fields('p',array('uuid','short_name','connected_bundle'))
              ->condition('pending',0)
              ->condition('starting_concept',$concept)
              ->execute();
  while ($obj = $result->fetch()) {
    if (!isset($return[$obj->connected_bundle])) $return[$obj->connected_bundle] = array();
    $return[$obj->connected_bundle]['external:'.$obj->uuid] = $obj->short_name;
  }
  return $return;
}