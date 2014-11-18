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
/*  if (variable_get('wisski_core_semaphore_'.$id,FALSE)) {
    trigger_error('Cyclic call to wisski_core_entity_view for entity '.$id,E_USER_WARNING);
    variable_set('wisski_core_semaphore_'.$id,FALSE);
    return;
  }
  */
  drupal_set_message("muhaha!");
  /*variable_set('wisski_core_semaphore_'.$id,TRUE);*/
  module_invoke_all('wisski_update_entity',$entity);
//  drupal_set_message("miau!");
  drupal_set_title(entity_label('wisski_core_entity', $entity));
  // Return automatically generated view page.
  //$entity_view = entity_view('wisski_core_entity',array($id => $entity)/*,'full'*/);
  $entity_view = entity_view('wisski_core_entity', array(entity_id('wisski_core_entity', $entity) => $entity));
//  variable_del('wisski_core_semaphore_'.$id);
  return $entity_view;
}
