<?php

//////////////////////////////////////////////
// SWIPS - (Field) {S}torage {w}ith {P}ath{s}
//////////////////////////////////////////////

/**
* implements @see hook_field_storage_info
*/
function wisski_core_field_storage_info() {

  dpm("Call to hook_field_storage_info");  
  $info = module_invoke('field_sql_storage','field_storage_info');
  return $info + array(
    'wisski_core_swips' => array(
      'label' => t("WissKI path supported storage"),
      'description' => t("Stores fields in the local SPARQL 1.1 triple store"),
      'settings' => array(),
    ),
  );
}

/**
* implements hook_field_storage_info_alter
*/
function wisski_core_field_storage_info_alter(&$info) {
  
  //ensure that the standard field storage is used in the correct manner
  //normally the module would be this here (wisski_core)
  if (isset($info['field_sql_storage'])) $info['field_sql_storage']['module'] = 'field_sql_storage';
}

/**
 * Implements hook_field_storage_create_field().
 */
function wisski_core_field_storage_create_field($field) {
  module_invoke('field_sql_storage','field_storage_create_field',$field);
}

/**
 * Implements hook_field_update_forbid().
 *
 * Forbid any field update that changes column definitions if there is
 * any data.
 */
 /*
function wisski_core_field_update_forbid($field, $prior_field, $has_data) {
  module_invoke('field_sql_storage','field_update_field',$field, $prior_field, $has_data);
}
*/
/**
 * Implements hook_field_storage_update_field().
 */
function wisski_core_field_storage_update_field($field, $prior_field, $has_data) {
  module_invoke('field_sql_storage','field_storage_update_field',$field, $prior_field, $has_data);
}

/**
 * Implements hook_field_storage_delete_field().
 */
function wisski_core_field_storage_delete_field($field) {
  module_invoke('field_sql_storage','field_storage_delete_field',$field);
}

/**
* implements hook_field_storage_pre_load
*/
function wisski_core_field_storage_pre_load($entity_type, $queried_entities, $age, $skip_fields, $options) {

  module_load_include('inc','wisski_core','wisski_core.pathbuilder');    
//  dpm("call to hook_field_storage_pre_load for ".$entity_type);
//  dpm(func_get_args());
  if($entity_type == 'wisski_core_entity') {
//    dpm($queried_entities);
    $wisski_semaphore = &drupal_static(__FUNCTION__);
    if (isset($wisski_semaphore) && $wisski_semaphore) {
      dpm(__FILE__.'('.__LINE__.'): tick');
      return;
    }
    $wisski_semaphore = TRUE;
    if ($age == FIELD_LOAD_REVISION) {
      dpm(__FILE__.": load revision");
      //load from default storage
//      module_invoke('field_sql_storage','field_storage_load',$entity_type, $queried_entities, $age, $skip_fields, $options);
    } else {
      foreach($queried_entities as $entity) {
        //remember that $entity->type is the bundle, not 'wisski_core_entity'
        $instances = field_info_instances('wisski_core_entity',$entity->type);
        $path_info = db_select('wisski_pb_pathdata','path')
                      ->fields('path')
                      ->condition('pending',0,'=')
                      ->execute()
                      ->fetchAllAssoc('uuid');
        $individual = $entity->uri;
        $info = array();
        $field_info = array();
        $wrapper = entity_metadata_wrapper('wisski_core_entity',$entity);
//        dpm((array)$wrapper);
        foreach($instances as $instance) {
          $result = db_select('wisski_pb_fielddata','con')
                      ->fields('con')
                      ->condition('instance_id',$instance['id'],'=')
                      ->execute()
                      ->fetchAssoc();
          if (isset($result['path_id']) && $path = $path_info[$result['path_id']]) {
            if (!empty($info)) {
              try {
                $type = $wrapper->$instance['field_name']->type();
                if ('list' == substr($type,0,4))
                  $wrapper->$instance['field_name']->set($info);
                else
                  $wrapper->$instance['field_name']->set(current($info));
              } catch (EntityMetadataWrapperException $e) {
                trigger_error("EntityMetadataWrapperException ".$e->getMessage(),E_USER_ERROR);
                dpm($type);
                dpm($info);
              }
              $field_info[$instance['id']] = $info;
            }
            $skip_fields[$instance['field_id']] = $entity->id;
          }
        }//END foreach $instances
        wisski_core_set_short_title($entity);
      }//END foreach $queried_entities
    }
    $wisski_semaphore = FALSE;
  } else {
    module_invoke('field_sql_storage','field_storage_pre_load',$entity_type, $queried_entities, $age, $skip_fields, $options);
  }
}

function wisski_core_set_short_title(&$entity) {

  module_load_include('inc','wisski_core','wisski_core.pathbuilder');
  //check, whether we have the title cached
  $result = db_select('wisski_entity_title_cache','tit')
                ->fields('tit')
                ->condition('uri',$entity->uri)
                ->execute()
                ->fetchAssoc();
  if (!empty($result)) {
    $entity->title = $result['short_title'];
    return;
  }
//  dpm($entity);
  //else create the title from the pattern
  $result = db_select('wisski_entity_bundles','bund')
              ->fields('bund',array('type','short_title_pattern'))
              ->condition('type',$entity->type)
              ->execute()
              ->fetchAssoc();
  if (!empty($result)) {
    //since the entity stores the field info keyed by field id,
    //we can directly use the entity as field info
    $pattern = unserialize($result['short_title_pattern']);
    if ($pattern) $entity->title = wisski_core_make_short_title($entity,array_expand($pattern));  }
//  dpm($entity);
  db_insert('wisski_entity_title_cache')
    ->fields(array('uri' => $entity->uri, 'short_title' => $entity->title))
    ->execute();
}

/**
* implements hook_field_storage_load
*/ 
function wisski_field_storage_load($entity_type, $entities, $age, $fields, $options) {
  
  dpm("call to hook_field_storage_load");
  dpm(func_get_args());
  if($entity_type == 'wisski_core_entity') {
    if ($age == FIELD_LOAD_REVISION) {
      //load from default storage
//      module_invoke('field_sql_storage','field_storage_load',$entity_type, $entities, $age, $fields, $options);
    } else {
      foreach($entities as $entity) {
        //remember that $entity->type is the bundle, not 'wisski_core_entity'
        $instances = field_info_instances('wisski_core_entity',$entity->type);
        $path_info = db_select('wisski_pb_pathdata','path')
                      ->fields('path')
                      ->condition('pending',0,'=')
                      ->execute()
                      ->fetchAllAssoc('uuid');
        $individual = $entity->uri;
        $info = array();
        $wrapper = entity_metadata_wrapper('wisski_core_entity',$entity);
        foreach($instances as $instance) {
          if (!array_key_exists($instance['field_id'],$fields)) continue;
          $result = db_select('wisski_pb_fielddata','con')
                      ->fields('con')
                      ->condition('instance_id',$instance['id'],'=')
                      ->execute()
                      ->fetchAssoc();
          if (empty($result)) {
            trigger_error("there is no path defined for the field ".$instance['field_name']." on this bundle",E_USER_ERROR);
            return;
          }
          $path = $path_info[$result['path_id']];
          $starting_concept = $path->starting_concept;
          $full_path = array();
          do {
            $full_path = array_merge($full_path,unserialize($path->path_array));
            $data = $path->datatype_property;
          } while(isset($path_info[$path->external_path]) && $path = $path_info[$path->external_path]);
          $info = wisski_salz_pb_query($individual,$starting_concept,$full_path,$data);
          if (!empty($info)) {
            $is_list = 'list' == substr($wrapper->$instance['field_name']->type(),0,4);
            if ($is_list)
              $wrapper->$instance['field_name']->set($info);
            else
              $wrapper->$instance['field_name']->set(current($info));
          } else {
            $wrapper->$instance['field_name']->set('');
            dpm(__FILE__.": No results for this individual");
          }
        }//END foreach $instances
        $wrapper->save();
      }//END foreach $entities
    }
  } else {
    module_invoke('field_sql_storage','field_storage_load',$entity_type, $entities, $age, $fields, $options);
  }
}



/**
* implements hook_field_storage_write
*/
function wisski_core_field_storage_write($entity_type, $entity, $op, $fields) {

//  dpm("call to hook_field_storage_write for $entity_type");
  if ($entity_type == 'wisski_core_entity') {
    $bundle_info = NULL;
//    dpm(func_get_args());
    if (property_exists($entity,'uri') && $entity->uri != NULL) {
      $individual = $entity->uri;
    } else {
      trigger_error("The entity ".$entity->title." does not have a uri",E_USER_ERROR);
      return;
/*      $individual =  wisski_core_make_uri($entity->title,$entity->type);
      $entity->uri = $individual;
      db_update('wisski_entity_data')
        ->fields(array('uri' => $individual))
        ->condition('id',$entity->id)
        ->execute();*/
    }
//    dpm(entity_uri($entity_type,$entity));
//    dpm("computed uri: ".$individual);
    $path_info = db_select('wisski_pb_pathdata','path')
                ->fields('path')
                ->condition('pending',0,'=')
                ->execute()
                ->fetchAllAssoc('uuid');
//    dpm("Tick 1");
    $new_individuals = array();
    foreach($fields as $field_id) {
//      dpm("Tick 2");
      $field = field_info_field_by_id($field_id);
      $instance = field_info_instance($entity_type,$field['field_name'],$entity->type);
      if (!$instance) continue;
//      dpm("Tick 3");
      $result = db_select('wisski_pb_fielddata','con')
                ->fields('con')
                ->condition('instance_id',$instance['id'],'=')
                ->execute()
                ->fetchAssoc();
      if (empty($result)) {
        trigger_error("there is no path defined for the field ".$instance['field_name']." on this bundle",E_USER_ERROR);
        return;
      }
      $path = $path_info[$result['path_id']];
      $starting_concept = $path->starting_concept;
      $full_path = array();
      do {
        $full_path = array_merge($full_path,unserialize($path->path_array));
        $data = $path->datatype_property;
      } while(isset($path_info[$path->external_path]) && $path = $path_info[$path->external_path]);
      $new_values = field_get_items($entity_type,$entity,$instance['field_name']);
      $delete = !property_exists($entity,'is_new') || !$entity->is_new;
      foreach($new_values as $delta) {
        if ($news = wisski_salz_pb_update($individual,$entity->title,$starting_concept,$full_path,$data,$delta['value'],$delete)) {
          dpm($news);
          foreach($news as $class => $inds) {
            if (!isset($new_individuals[$class])) $new_individuals[$class] = array();
            $new_individuals[$class] += $inds;
          }
        }
      }
    }
    if ($op == FIELD_STORAGE_UPDATE) { 
      //write a revision to the sql table
//      module_invoke('field_sql_storage','field_storage_write',$entity_type, $entity, $op, $fields);
    }
    if (!empty($new_individuals)) {
      if (is_null($bundle_info)) {
        $bundle_info = db_select('wisski_entity_bundles','b')
                    ->fields('b',array('type','uri','label'))
                    ->execute()
                    ->fetchAllAssoc('uri');
      }
//      dpm($new_individuals);
//      dpm($bundle_info);
      foreach($new_individuals as $class => $inds) {
        $type = $bundle_info[$class]->type;
        $title = t('anonymous').' '.$bundle_info[$class]->label;
        foreach($inds as $ind_uri) {
          $info = array(
            'type' => $type,
//            'name' => substr($ind_uri,0,32),
            'id' => $ind_uri,
            'uri' => $ind_uri,
            'title' => $title,
            'same_individuals' => array(),
          );
          $new_entity = entity_create('wisski_core_entity',$info);
          entity_save('wisski_core_entity',$new_entity);
        }
      }
    }
  } else {
    module_invoke('field_sql_storage','field_storage_write',$entity_type, $entity, $op, $fields);
  }
}

function wisski_core_make_uri($entity_title,$bundle_name,$ind_only = TRUE) {
  
  $bundle_uri = db_select('wisski_entity_bundles','b')
                  ->fields('b',array('type','uri'))
                  ->condition('type',$bundle_name)
                  ->execute()
                  ->fetchObject()
                  ->uri;
//  dpm($bundle_uri);
  $ind_uri = wisski_salz_pb_make_uri($bundle_uri,substr($entity_title,0,8));
  $trace = debug_backtrace();
  watchdog('wisski_make_uri',$entity_title.' '.$bundle_name.' '.$ind_uri.' from '.serialize($trace));
  if ($ind_only)
    return $ind_uri;
  else return array($ind_uri,$bundle_uri);
}


/**
 * Implements hook_field_storage_delete().
 *
 * This function deletes data for all fields for an entity from the database.
 */
function wisski_core_field_storage_delete($entity_type, $entity, $fields) {
  
  if ($entity_type == 'wisski-core_entity') {
    wisski_salz_pb_delete_entity_data($entity->uri);
  } else module_invoke('field_sql_storage','field_storage_delete',$entity_type,$entity,$fields);
}

/**
 * Implements hook_field_storage_purge().
 *
 * This function deletes data from the database for a single field on
 * an entity.
 */
function wisski_core_field_storage_purge($entity_type, $entity, $field, $instance) {
  module_invoke('field_sql_storage','field_storage_field_purge',$entity_type, $entity, $field, $instance);
}

/**
 * Implements hook_field_storage_query().
 */
function wisski_core_field_storage_query(EntityFieldQuery $query) {
  dpm((array) $query);
  if ($query->age == FIELD_LOAD_CURRENT) {
    
  }
  else {
    
  }
  
  foreach ($query->fields as $key => $field) {
    
    if ($key) {
    
    }
    else {
    
    }
    if ($field['cardinality'] != 1 || $field['translatable']) {
    
    }
  }

  if (isset($query->deleted)) {

  }

  // Is there a need to sort the query by property?
  $has_property_order = FALSE;
  foreach ($query->order as $order) {
    if ($order['type'] == 'property') {
      $has_property_order = TRUE;
    }
  }

  if ($query->propertyConditions || $has_property_order) {
    if (empty($query->entityConditions['entity_type']['value'])) {
      throw new EntityFieldQueryException('Property conditions and orders must have an entity type defined.');
    }
    $entity_type = $query->entityConditions['entity_type']['value'];

    $query->entityConditions['entity_type']['operator'] = '=';
    foreach ($query->propertyConditions as $property_condition) {

    }
  }
  foreach ($query->entityConditions as $key => $condition) {

  }

  // Order the query.
  foreach ($query->order as $order) {
    if ($order['type'] == 'entity') {
      $key = $order['specifier'];

    }
    elseif ($order['type'] == 'field') {
      $specifier = $order['specifier'];
      $field = $specifier['field'];

    }
    elseif ($order['type'] == 'property') {

    }
  }
  $result = array();
//  $result = $query->finishQuery($select_query, $id_key);
  return $result;
}


/**
 * Implements hook_field_storage_delete_revision().
 *
 * This function actually deletes the data from the database.
 */
function wisski_core_field_storage_delete_revision($entity_type, $entity, $fields) {
  module_invoke('fiel_sql_storage','field_storage_delete_revision',$entity_type, $entity, $fields);
}

/**
 * Implements hook_field_storage_delete_instance().
 *
 * This function simply marks for deletion all data associated with the field.
 */
function wisski_core_field_storage_delete_instance($instance) {
  module_invoke('field_sql_storage','field_storage_delete_instance',$instance);
}

/**
 * Implements hook_field_storage_purge_field().
 *
 * All field data items and instances have already been purged, so all
 * that is left is to delete the table.
 */
function wisski_core_field_storage_purge_field($field) {
  module_invoke('field_sql_storage','field_storage_purge_field',$field);
}

/**
* implements @see hook_field_storage_details
*/
function wisski_core_field_storage_details($field) {

  $info = module_invoke('field_sql_storage','field_storage_details',$field);
  return $info + array(
    'wisski_core_swips' => array(
      FIELD_LOAD_CURRENT => array(
      
      ),
      FIELD_LOAD_REVISION => $info['sql'][FIELD_LOAD_REVISION],
    ),
  );
}

function _wisski_core_rekey_array($array,$key) {

  $out = array();
  foreach ($array as $entry) {
    if (is_array($entry) && isset($entry[$key]))
      $out[$entry[$key]] = $entry;
  }
  return $out;
}