<?php

/**
 * @file
 * Callbacks for viewing entities.
 */

function wisski_core_list_bundles() {
  $items = array();
  foreach (wisski_core_bundle_load_multiple() as $entity_type_key => $entity_type) {
    $items[] = l(entity_label('wisski_core_bundle', $entity_type), 'wisski/navigate/' . $entity_type_key);
  }
  return array(
    'list' => array(
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => t('Choose type of entity to view.'),
    ),
  );
}

/**
 * Show list of existing entities.
 */

function wisski_core_list($type) {

  module_invoke_all('wisski_update_class',$type);
//  drupal_set_title(t('WissKI Entities'));

  //drupal_set_message(serialize($type));

  // Show link to the entity "Add" page.
  $output = '<br/>';
  /*
  $output .= theme_link(
    array(
      'text' => t('Add new entity'),
      'path' => 'wisski_core/add',
      'options' => array(
        'attributes' => array(),
        'html' => TRUE,
      ),
    )
  );
  */

  // Show list of existing entities.
//  $entities = entity_load('wisski_core_entity');

  $query = new EntityFieldQuery();
  
  $query->entityCondition('entity_type', 'wisski_core_entity');
  
  $query->propertyCondition('type', $type);
  
  $results = $query->execute();

  //drupal_set_message(serialize($results));
  
  if(!empty($results['wisski_core_entity']))
    $entities = entity_load('wisski_core_entity', array_keys($results['wisski_core_entity']));
  else
    $entities = array();
  
  $items = array();
  foreach ($entities as $entity) {
    
    //drupal_set_message(serialize($entity));
    $entity_uri = entity_uri('wisski_core_entity', $entity);
    
    $items[] = l(entity_label('wisski_core_entity', $entity), $entity_uri['path']);
  }
  $output .= theme_item_list(
    array(
      'items' => $items,
      'title' => t('List of existing entities.'),
      'type' => 'ul',
      'attributes' => array(),
    )
  );

  return $output;
}

/**
 * Entity view callback.
 * 
 * @param object $entity
 *   Entity to view.
 * 
 * @return array
 *   Renderable array.
 */
function wisski_core_entity_view($entity) {
  //since this implements hook_entity_view which is invoked by entity_view() again
  //we want to avoid cyclic calls
  $id = entity_id('wisski_core_entity', $entity);
  if (variable_get('wisski_core_semaphore_'.$id,FALSE)) {
    trigger_error('Cyclic call to wisski_core_entity_view for entity '.$id,E_USER_WARNING);
    variable_set('wisski_core_semaphore_'.$id,FALSE);
    return;
  }
  variable_set('wisski_core_semaphore_'.$id,TRUE);
  module_invoke_all('wisski_update_entity',$entity);
  drupal_set_title(entity_label('wisski_core_entity', $entity));
  // Return automatically generated view page.
  $entity_view = entity_view('wisski_core_entity',array($id => $entity)/*,'full'*/);
  variable_del('wisski_core_semaphore_'.$id);
  return $entity_view;
}

function wisski_core_edit_bundle_paths($form,&$form_state) {

  $old_paths = db_select('wisski_pb_pathdata','paths')
                ->fields('paths')
                ->condition('starting_concept',arg(2),'LIKE')
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
    $entity = entity_load_single('wisski_core_bundle',arg(2));
    $last = $entity->label;
    $form['item']['starting_concept'] = array(
      '#type' => 'hidden',
      '#value' => $last,
    );
  }
  $entries = sparql11_adapter_next_steps($last);
  $options = array('default' => '-choose-');
  foreach($entries as $entry) $options[$entry] = $entry;
  
  $form['item']['path']['step'] = array(
    '#type' => 'select',
    '#title' => '',
    '#options' => $options,
    '#default_value' => 'default',
    '#ajax' => array(
      'callback' => 'wisski_pb_path_step',
      'wrapper' => 'full_path',
    ),
  );
    
  $entries = sparql11_adapter_next_datatype_properties($last);
  
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
  $external = db_select('wisski_pb_pathdata','paths')
                ->fields('paths')
                ->condition('starting_concept',$last,'LIKE')
                ->condition('pending',0,'=')
                ->execute()
                ->fetchAllAssoc('uuid');
  if (!empty($external)) {
    $options = array('default' => '-choose-');
    foreach($external as $key => $res) $options[$key] = $res->short_name;
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
      'external_path' => isset($form_state['values']['item']['path']['external_path']) ? $form_state['values']['item']['path']['external_path'] : NULL,
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