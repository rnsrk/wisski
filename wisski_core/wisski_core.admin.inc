<?php

/**
 * @file
 * Callbacks for administrating entities.
 */

/**
 * Overview form for ontology handling
 * 
 * @return form
 *   Form for the ontology handling menu
 * @author Mark Fichtner
 */
function wisski_core_ontology_overview() {
  $form = array();

  $local_store = wisski_salz_invoke_local_store();

  // check if there is a local store    
  if(empty($local_store)) {
    // give out a message that there is no local store currently - the user should select one
    $form['nothing_here'] = array(
      '#type' => 'item',
      '#markup' => '<b>No local store is specified currently.</b><br/> Please select a local store <a href="salz">here</a>',
    );

    // stop here
    return $form;
  }
  
  // if there is a local store - check if there is an ontology in the store
  
  if(stristr($local_store->getType(), 'SPARQL') !== FALSE) {
    $infos = $local_store->getOntologies();
    
    // there already is an ontology
    if(!empty($infos) && count($infos) > 0 ) {
      $form['header'] = array(
        '#type' => 'item',
        '#markup' => '<b>Currently loaded Ontology:</b><br/>',
      );
      
      $table = "<table><tr><th>Name</th><th>Iri</th><th>Version</th><th>Graph</th></tr>";
      foreach($infos as $ont) {
        $table .= "<tr><td>" . $ont->ont . "</td><td>" . $ont->iri . "</td><td>" . $ont->ver . "</td><td>" . $ont->graph . "</td></tr>";
      }
      $table .= "</table>";
      
      
      $form['table'] = array(
        '#type' => 'item',
        '#markup' => $table,
      );
      
      $form['delete_ont'] = array(
        '#type' => 'submit',
        '#name' => 'Delete Ontology',
        '#value' => 'Delete Ontology',
        '#submit' => array('wisski_core_delete_ontology'),
      );
    } else {
    // No ontology was found
    
      $form['load_onto'] = array(
        '#type' => 'textfield',
        '#title' => 'Load Ontology:',
        '#description' => 'Please give the URL to a loadable ontology.',
      );
    
      $form['delete_ont'] = array(
        '#type' => 'submit',
        '#name' => 'Load Ontology',
        '#value' => 'Load Ontology',
        '#submit' => array('wisski_core_load_ontology'),
      );
    
    }
  }
  
  $ns = $local_store->getNamespaces();  

  $table = "<table><tr><th>Short Name</th><th>URI</th></tr>";
  foreach($ns as $key => $value) {
    $table .= "<tr><td>" . $key . "</td><td>" . $value . "</td></tr>";
  }
  $table .= "</table>";
   
  $form['ns_table'] = array(
    '#type' => 'item',
    '#markup' => $table,
  );

  return $form; 
}

function wisski_core_load_ontology($form, $form_state) {
  $local_store = wisski_salz_invoke_local_store();

  // check if there is a local store    
  if(empty($local_store)) {
    return;
  }
  
  // if there is a local store - check if there is an ontology in the store
  if(stristr($local_store->getType(), 'SPARQL') !== FALSE) {
    $local_store->addOntologies($form_state['values']['load_onto']);
  }
  
  return; 
  

}

function wisski_core_delete_ontology() {

  $local_store = wisski_salz_invoke_local_store();

  // check if there is a local store    
  if(empty($local_store)) {
    return;
  }
  
  // if there is a local store - check if there is an ontology in the store
  
  if(stristr($local_store->getType(), 'SPARQL') !== FALSE) {
    $infos = $local_store->getOntologies();
    
    // there already is an ontology
    if(!empty($infos)) {
      foreach($infos as $ont) {
        if(strval($ont->graph) != "default") 
          $local_store->deleteOntology(strval($ont->graph));
        else
          $local_store->deleteOntology(strval($ont->ont), 'no-graph');
      }
    }
    
  }
  
  return; 
}




/**
 * Choose bundle of entity to add.
 * 
 * @return array
 *   Array describing a list of bundles to render.
 */
function wisski_core_choose_bundle() {
/*
  drupal_set_title(t('Choose type of entity to add.'));

  // Show list of all existing entity bundles.
  $entity_info = entity_get_info('wisski_individual');
  $items = array();
  foreach ($entity_info['bundles'] as $bundle_name => $bundle_data) {
    $items[] = l($bundle_data['label'], 'wisski_core/add/' . $bundle_name);
  }
  */
  
  $items = array();
  foreach (wisski_core_bundle_load_multiple() as $entity_type_key => $entity_type) {
    $items[] = l(entity_label('wisski_core_bundle', $entity_type), 'wisski/create/' . $entity_type_key);
  }
  return array(
    'list' => array(
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => t('Choose type of entity to add.'),
    ),
  );
}

/**
 * Form constructor for the entity add form.
 * 
 * @param string $type
 *   Bundle of the entity to add.
 * 
 * @return array
 *   Entity edit form.
 */
function wisski_core_individual_add($type) {

  $entity_type = wisski_core_bundle_load_multiple($type);
//  drupal_set_title(t('Create new %type', array('%type' => $type)));
  drupal_set_title(t('Create @name', array('@name' => entity_label('entity_type', $entity_type))));
  
  // Return form for the given entity bundle.
  $entity = entity_create('wisski_individual', array('type' => $type));

//  $output = entity_ui_get_form('wisski_individual', $entity);
  $output = drupal_get_form('wisski_core_individual_form', $entity);

  return $output;
}

/**
 * Form constructor for the entity edit form.
 * 
 * In this form we shall manually define all form elements related to editable
 * properties of the entity.
 * 
 * @param object $entity
 *   Enity to edit.
 * 
 * @return array
 *   Entity edit form.
 */
function wisski_core_individual_form($form, &$form_state, $entity) {
  
  //ensure that $entity is actually an entity
  if (is_array($entity)) {
    if (count($entity) == 1) $entity = current($entity);
    else throw new InvalidArgumentException("\$entity must be a drupal entity");
  }
  
  // Store the entity in the form.
  $form_state['wisski_core'] = $entity;

  // Describe all properties of the entity which shall be shown on the form.
//  $wrapper = entity_metadata_wrapper('wisski_individual', $entity);
  $form['title'] = array(
    '#type' => 'textfield',
    '#required' => TRUE,
    '#title' => t('Title'),
    '#default_value' => property_exists($entity,'title') ? $entity->title : entity_label('wisski_individual',$entity),
    '#weight' => -20,
    '#disabled' => TRUE,
  );
  $form['uri'] = array(
    '#type' => 'textfield',
    '#required' => TRUE,
    '#title' => 'URI',
    '#default_value' => $entity->uri,
    '#weight' => -10,
  );
/*  $form['description'] = array(
    '#type' => 'textfield',
    '#title' => t('Description'),
    '#default_value' => $wrapper->description->value(),
    '#weight' => -10,
  );*/
/*  
  try {
    $same_inds = $wrapper->same_individuals->value();
    $same_string = "";
    if ($same_inds != NULL) {
      foreach($same_inds as $ind) {
        $same_string .= $ind."\n";
      }
    }
    $form['same_individuals'] = array(
      '#type' => 'textarea',
      '#title' => t('Same Individuals'),
      '#default_value' => $same_string,
      '#weight' => -5,
    );
  } catch (EntityMetadataWrapperException $ex) {
    dpm((array)$wrapper);
  }
  */
  // Add fields of the entity to the form.
  field_attach_form('wisski_individual', $entity, $form, $form_state);

  // Add some buttons.
  $submit = array();
  if (!empty($form['#submit'])) {
    $submit += $form['#submit'];
  }
  $form['actions'] = array(
    '#weight' => 100,
  );
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save entity'),
    '#submit' => $submit + array('wisski_core_individual_form_submit'),
  );
  $entity_id = entity_id('wisski_individual', $entity);
  if (!empty($entity_id)) {
    $form['actions']['delete'] = array(
      '#type' => 'submit',
      '#value' => t('Delete'),
      '#submit' => array('wisski_core_individual_form_submit_delete'),
    );
  }

/*
  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
    '#weight' => 5,
    '#submit' => array('wisski_core_form_submit'),
  );
  if (empty($entity->is_new)) {
    $form['actions']['delete'] = array(
      '#type' => 'submit',
      '#value' => t('Delete'),
      '#weight' => 15,
      '#submit' => array('wisski_core_form_submit_delete'),
    );
  }
*/

  return $form;
}

/**
 * Entity edit form "Save" submit handler.
 */
function wisski_core_individual_form_submit(&$form, &$form_state) {
  // Automatically get edited entity from the form and save it.
  // @see entity_form_submit_build_entity()
  $entity = $form_state['wisski_core'];
  entity_form_submit_build_entity('wisski_individual', $entity, $form, $form_state);
  $wrapper = entity_metadata_wrapper('wisski_individual', $entity);
  $wrapper->save();

  // Redirect user to edited entity page.
  $entity_uri = entity_uri('wisski_individual', $entity);
  $form_state['redirect'] = $entity_uri['path'];
}

/**
 * Entity edit form "Delete" submit handler.
 */
function wisski_core_individual_form_submit_delete($form, &$form_state) {
  // Redirect user to "Delete" URI for this entity.
  $entity = $form_state['wisski_core'];
  $entity_uri = entity_uri('wisski_individual', $entity);
  $form_state['redirect'] = $entity_uri['path'] . '/delete';
}

/**
 * Form constructor for the entity delete confirmation form.
 * 
 * @param object $entity
 *   Entity to delete.
 * 
 * @return array
 *   Confirmation form.
 */
function wisski_core_individual_form_delete($form, &$form_state, $entity) {
  // Store the entity in the form.
  $form_state['wisski_core'] = $entity;

  // Show confirm dialog.
  $entity_uri = entity_uri('wisski_individual', $entity);
  $message = t('Are you sure you want to delete wisski_core %title?', array('%title' => entity_label('wisski_individual', $entity)));
  return confirm_form(
    $form,
    $message,
    $entity_uri['path'],
    t('This action cannot be undone.'),
    t('Delete'),
    t('Cancel')
  );
}

/**
 * Entity "Confirm delete" form submit handler.
 */
function wisski_core_individual_form_delete_submit($form, &$form_state) {
  // Delete the entity.
  $entity = $form_state['wisski_core'];
  $wrapper = entity_metadata_wrapper('wisski_individual', $entity);
  $wrapper->delete();

  // Redirect user.
  drupal_set_message(t('Custom entity v4 %title deleted.', array('%title' => entity_label('wisski_individual', $entity))));
  $form_state['redirect'] = '<front>';
}


function wisski_core_bundle_form($form, &$form_state, $entity_type, $op = 'edit') {
//  dpm($entity_type);
  // Handle the case when cloning is performed.
  if ($op == 'clone') {
    $entity_type->label .= ' (cloned)';
    $entity_type->type = '';
  }

  // Describe all properties of the entity which shall be shown on the form.
  $form['label'] = array(
    '#title' => t('Label'),
    '#type' => 'textfield',
    '#default_value' => $entity_type->label,
    '#description' => t('The human-readable name of this entity type.'),
    '#required' => TRUE,
    '#size' => 30,
  );
  $form['uri'] = array(
    '#title' => 'URI',
    '#type' => 'textfield',
    '#default_value' => property_exists($entity_type,'uri') ? $entity_type->uri : '',
    '#required' => TRUE,
    '#description' => t('The URI of the corresponding owl:Class in the local ontology'),
  );
  $form['type'] = array(
    '#type' => 'machine_name',
    '#default_value' => isset($entity_type->type) ? $entity_type->type : '',
    '#maxlength' => 32,
    '#disabled' => $entity_type->isLocked() && $op != 'clone',
    '#machine_name' => array(
      'exists' => 'wisski_core_bundle_load_multiple',
      'source' => array('label'),
    ),
    '#description' => t('A unique machine-readable name for this entity type. It must only contain lowercase letters, numbers, and underscores.'),
  );
  $form['description'] = array(
    '#type' => 'textarea',
    '#default_value' => isset($entity_type->description) ? $entity_type->description : '',
    '#description' => t('Description about the entity type.'),
  );

  // Add some buttons.
  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save entity type'),
    '#weight' => 40,
  );
  if (!$entity_type->isLocked() && $op != 'add' && $op != 'clone') {
    $form['actions']['delete'] = array(
      '#type' => 'submit',
      '#value' => t('Delete entity type'),
      '#weight' => 45,
      '#limit_validation_errors' => array(),
      '#submit' => array('wisski_core_bundle_form_submit_delete'),
    );
  }
 $form['actions']['pathbuilder'] = array(
    '#type' => 'submit',
    '#value' => t('Go to Pathbuilder Fields'),
    '#weight' => 50,
    '#submit' => array('wisski_core_pathbuilder_form_submit'),
  );


  return $form;
}

/**
 * Entity type edit form "Save" submit handler.
 */
function wisski_core_bundle_form_submit(&$form, &$form_state) {
  // Automatically get edited entity type from the form and save it.
  // @see entity_ui_form_submit_build_entity()
  $entity_type = entity_ui_form_submit_build_entity($form, $form_state);
  entity_save('wisski_core_bundle', $entity_type);

  // Redirect user.
  $form_state['redirect'] = 'admin/structure/wisski_core_bundle';
}

/**
 * Entity edit form "Delete" submit handler.
 */
function wisski_core_bundle_form_submit_delete(&$form, &$form_state) {
  // Redirect user to "Delete" URI for this entity type.
  $form_state['redirect'] = 'admin/structure/wisski_core_bundle/' . $form_state['wisski_core_bundle']->type . '/delete';
}

/**
 * Form constructor for the entity type delete confirmation form.
 * 
 * @param object $entity_type
 *   Entity type to delete.
 * 
 * @return array
 *   Confirmation form.
 */
function wisski_core_bundle_form_delete_confirm($form, &$form_state, $entity_type) {
  // Store the entity in the form.
  $form_state['entity_type'] = $entity_type;

  // Show confirm dialog.
  $message = t('Are you sure you want to delete entity type %title?', array('%title' => entity_label('entity_type', $entity_type)));
  return confirm_form(
    $form,
    $message,
    'wisski_core_bundle/' . entity_id('wisski_core_bundle', $entity_type),
    t('This action cannot be undone.'),
    t('Delete'),
    t('Cancel')
  );
}

/**
 * Entity type "Confirm delete" form submit handler.
 */
function wisski_core_bundle_form_delete_confirm_submit($form, &$form_state) {
  // Delete the entity type.
  $entity_type = $form_state['entity_type'];
  entity_delete('wisski_core_bundle', entity_id('wisski_core_bundle', $entity_type));

  // Redirect user.
  drupal_set_message(t('@type %title has been deleted.', array('@type' => $entity_type->type, '%title' => $entity_type->label)));
  $form_state['redirect'] = 'admin/structure/wisski_core_bundle';
}

function wisski_core_pathbuilder_form($form, &$form_state, $entity_type) {
  $form['label'] = array(
    '#title' => t('Label'),
    '#type' => 'textfield',
    '#default_value' => $entity_type->label,
    '#description' => t('The human-readable name of this entity type.'),
    '#required' => TRUE,
    '#size' => 35,
  );
  $form['type'] = array(
    '#type' => 'machine_name',
    '#default_value' => isset($entity_type->type) ? $entity_type->type : '',
    '#maxlength' => 32,
    //'#disabled' => $entity_type->isLocked() && $op != 'clone',
    '#machine_name' => array(
      'exists' => 'wisski_core_bundle_load_multiple',
      'source' => array('label'),
    ),
    '#description' => t('A unique machine-readable name for this entity type. It must only contain lowercase letters, numbers, and underscores.'),
  );
  
  $paths = array();
  $paths = wisski_salz_next_steps($entity_type->label);
  foreach($paths as $path){
  

  $form['pb_path' . $path] = array(
    '#title' => t('Pathbuilder Path'),
    '#type' => 'textfield',
    '#default_value' => $path,
 //   '#description' => t('The pathbuilder path of this wisski core bundle.'),
    '#required' => TRUE,
    '#size' => 35,
  );
  }
  $form['pb_external_path'] = array(
    '#title' => t('External Path'),
    '#type' => 'textfield',
    //'#default_value' => $entity_type->label,
   // '#description' => t('An external path of this wisski core bundle.'),
    '#required' => FALSE,
    '#size' => 35,
);
    return $form;
}

function wisski_core_pathbuilder_form_submit(&$form, &$form_state) {
    //$form_state['values']['pb_path'] = 'ecrm_e18_physical_thing';	
    $form_state['redirect'] = 'admin/structure/wisski_core_bundle/' . $form_state['wisski_core_bundle']->type . '/paths';
}

function wisski_core_individual_display($form,&$form_state) {
  
  $form['max_entites_per_page'] = array(
    '#type' => 'textfield',
    '#default_value' => variable_get('wisski_max_entities_per_page',20),
    '#description' => t('Maximum number of entities that are shown per listing page'),
  );

  // Add some buttons.
  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Set'),
    '#weight' => 40,
  );
  return $form;
}

function wisski_core_individual_display_submit($form,&$form_state) {

  variable_set('wisski_max_entities_per_page',(int)$form_state['values']['max_entites_per_page']);
  drupal_flush_all_caches();
}