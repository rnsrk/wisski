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

  $form = array();
  $form['item'] = array(
    '#type' => 'fieldset',
    '#title' => t('Add Path'),
    '#collapsible' => FALSE,
    '#tree' => TRUE,
    '#weight' => -2,
  );
  
  $form['item']['name'] = array(
    '#type' => 'textfield',
    '#title' => t('Path name'),
    '#default_value' => '',
    '#description' => t(''),
  );
  
  $form['item']['description'] = array(
    '#type' => 'textfield',
    '#title' => t('Description'),
    '#default_value' => '',
    '#description' => t(''),
  );
  
  if (empty($form_state['steps']['count'])) {
    $form_state['steps']['count'] = 0;
  } else if (isset($form_state['triggering_element']) && $form_state['triggering_element']['#name'] == 'step') {
    $form_state['steps']['count']++;
  }
  $entity = entity_load_single('wisski_core_bundle',arg(2));
  $entries = sparql11_adapter_next_steps($entity->label);
  
  $form['item']['path'] = array(
    '#type' => 'fieldset',
    '#title' => t('Path'),
    '#collapsible' => FALSE,
    '#tree' => TRUE,
    '#prefix' => '<div id="full_path">',
    '#suffix' => '</div>',
  );
  
  $form['item']['path']['step'] = array(
    '#type' => 'select',
    '#title' => '',
     '#options' => $entries,
    '#default_value' => 0,
    '#ajax' => array(
      'callback' => 'wisski_pb_path_step',
      'wrapper' => 'full_path',
    ),
  );
    
  $form['item']['enabled'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enabled'),
    '#default_value' => '',
    '#description' => t(''),
  );
  
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
    '#submit' => array('wisski_pb_path_submit'),
  );
  
  return $form;
}



function wisski_pb_path_step($form,&$form_state) {
  
  $name = $form_state['values']['item']['name'];
  $path = array($form_state['values']['item']['path']);
  $fields = array(
    'uuid' => $name,
    'path_array' => $path,
    'weight' => 0,
  );
  db_insert('wisski_pb_pathdata')
    ->fields($fields)
    ->execute();
  return $form['item']['path'];
}

function wisski_pb_path_submit($form, $form_state) {

  dpm($form);
  dpm($form_state);
  return $form;
}