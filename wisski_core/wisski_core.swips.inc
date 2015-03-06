<?php

//////////////////////////////////////////////
// SWIPS - (Field) {S}torage {w}ith {P}ath{s}
//////////////////////////////////////////////

include_once 'wisski_core.pathbuilder.inc';

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
function wisski_core_field_storage_pre_load($entity_type, $queried_entities, $age, &$skip_fields, $options) {

  module_load_include('inc','wisski_core','wisski_core.pathbuilder');    
//  dpm("call to hook_field_storage_pre_load for ".$entity_type);
//  dpm(func_get_args());
  if($entity_type == 'wisski_individual') {
//    dpm($queried_entities);
    $wisski_semaphore = &drupal_static(__FUNCTION__);
    if (isset($wisski_semaphore) && $wisski_semaphore) {
      dpm(__FUNCTION__.'('.__LINE__.'): tick');
      return;
    }
    $wisski_semaphore = TRUE;
    if ($age == FIELD_LOAD_REVISION) {
      dpm(__FUNCTION__.": load revision");
      //load from default storage
//      module_invoke('field_sql_storage','field_storage_load',$entity_type, $queried_entities, $age, $skip_fields, $options);
    } else {
      foreach($queried_entities as $entity) {
        //remember that $entity->type is the bundle, not 'wisski_individual'
        $instances = field_info_instances('wisski_individual',$entity->type);
        $individual = $entity->uri;
        $field_info = array();

#        $wrapper = entity_metadata_wrapper('wisski_individual',$entity);

#        dpm(array('instances' => $instances,'property info' => (array)($wrapper->getPropertyInfo())));

        foreach($instances as $instance) {
#          $type = $wrapper->$instance['field_name']->type();
 
          
          $path_array = wisski_core_make_path_array(array('field_info' => array('field_name' => $instance['field_name'])));

          dpm($path_array);
          $info = array();
          if(isset($path_array[$instance['field_name']])) {

            $paths_for_field = $path_array[$instance['field_name']];
            if (!empty($paths_for_field)) {
              $starting_concept = current($paths_for_field);
              $starting_concept = $starting_concept['starting_concept'];
              $info = wisski_salz_pb_query_multi_path($starting_concept,$paths_for_field);
            
            }
          }
/* 
          foreach ($paths_for_field as $mapping => $path) {
            $starting_concept = $path['starting_concept'];
            $full_path = $path['full_path'];
            $data = $path['datatype_property'];
            $disamb = $path['disamb'];
      
            $info[$mapping] = wisski_salz_pb_query($individual,$starting_concept,$full_path,$data);
          }
*/
          dpm($info);
          throw new Exception('breaker');
          $info = _wisski_core_gather_field_data($entity, $instance,$info);
          dpm($info);

/*
          // get the field infos the field wants to have
          $field_infos = $wrapper->$instance['field_name']->info();

          // if it is a file we do have to do something special magic
          if(isset($field_infos['property info']['file'])) {
            // if there is a fid
            if(!empty($info['fid'])) {
            
              $fid = $info['fid'][$entity->uri][0][0];
              
              $file = file_load($fid);
              
              $wrapper->$instance['field_name']->set(array('fid' => $fid) );
              
              continue;
              
            }
          }

          //throw new Exception('breaker');
          if (!empty($info)) {
            drupal_set_message(serialize($info));
            $info = current($info);
            
            drupal_set_message(serialize($wrapper));
            drupal_set_message(serialize($wrapper->$instance['field_name']));
            drupal_set_message(serialize($info));
            
            dpm($wrapper->$instance['field_name']->info());
            
                        
            try {
              if ($type === 'wisski_individual') {
                $target_entity = entity_load('wisski_individual',$info);
                dpm($target_entity);
                $wrapper->$instance['field_name']->set($target_entity);
              } elseif ('list' == substr($type,0,4)) {
                $wrapper->$instance['field_name']->set($info);
              } else {
              
                $wrapper->$instance['field_name']->set($info[$entity->uri][0][0]);
              }
            } catch (EntityMetadataWrapperException $e) {
              trigger_error("EntityMetadataWrapperException ".$e->getMessage(),E_USER_ERROR);
              dpm(array('info'=>$info));
            }
            
          }
          */
          
          dpm($entity);
          
          drupal_set_message(serialize($info));
          foreach($info as $fieldname => $uri_array) {
            if(!is_array($uri_array))
              continue;

            foreach($uri_array as $enturi => $lang_array) {

              if(!is_array($lang_array))
                continue;
              foreach($lang_array as $langcode => $deltarray) {

                if(!is_array($deltarray))
                  continue;

                foreach($deltarray as $delta => $value) { 
          
          #if(!property_exists($entity, $instance['field_name'])) {
                  $curarr = &$entity->$instance['field_name'];
                  
                  // get the language code for the field
                  $language = field_language($entity->type, $entity, $instance['field_name'], $langcode);
                  
                  // if it is empty, it probably is LANGUAGE_NONE
                  if(empty($language))
                    $language = LANGUAGE_NONE;
                                    
                  $curarr[$language][$delta][$fieldname]  = $value;
                  
          #}	
                }
              }
            }
          } 
          

          
          //$entity->$instance['field_name'] = $info;
          
          $skip_fields[$instance['field_id']] = $entity->id;
        }//END foreach $instances
        wisski_core_set_short_title($entity,FALSE);
      }//END foreach $queried_entities
    }
    $wisski_semaphore = FALSE;
  } else {
    module_invoke('field_sql_storage','field_storage_pre_load',$entity_type, $queried_entities, $age, $skip_fields, $options);
  }
}

function wisski_core_set_short_title(&$entity,$cached = TRUE) {

  module_load_include('inc','wisski_core','wisski_core.pathbuilder');
  if ($cached) {
    //check, whether we have the title cached
    $result = db_select('wisski_entity_data','tit')
                ->fields('tit')
                ->condition('uri',$entity->uri)
                ->condition('dirty',0)
                ->condition('type',$entity->type)
                ->execute()
                ->fetchAssoc();
    if (isset($result['title'])) {
      $entity->title = $result['title'];
      return;
    }
  }
//  dpm($entity);
  //else create the title from the pattern
  $result = db_select('wisski_entity_bundles','bund')
              ->fields('bund',array('type','short_title_pattern'))
              ->condition('type',$entity->type)
              ->execute()
              ->fetchAssoc();
  $title = FALSE;
  if (!empty($result)) {
    //since the entity stores the field info keyed by field id,
    //we can directly use the entity as field info
    $pattern = unserialize($result['short_title_pattern']);
    if ($pattern) $title = wisski_core_make_short_title($entity,array_expand($pattern)); 
  }
  $entity->title = $title?:variable_get('wisski_titles_master_fallback','').' ('.$entity->uri.')';
//  dpm($entity);
  db_update('wisski_entity_data')
    ->fields(array('title' => $entity->title,'dirty' => 0))
    ->condition('uri',$entity->uri)
    ->condition('type',$entity->type)
    ->execute();
}

/**
* implements hook_field_storage_load
*/ 
function wisski_field_storage_load($entity_type, $entities, $age, $fields, $options) {
  
}



/**
* implements hook_field_storage_write
*/
function wisski_core_field_storage_write($entity_type, $entity, $op, $fields) {

//  dpm("call to hook_field_storage_write for $entity_type");
  if ($entity_type == 'wisski_individual') {
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
    $new_individuals = array();
    foreach($fields as $field_id) {
//      dpm("Tick 2");
      $field = field_info_field_by_id($field_id);
      $instance = field_info_instance($entity_type,$field['field_name'],$entity->type);
      if (empty($instance)) continue;
//      dpm("Tick 3");
      $path_array = wisski_core_make_path_array(array('field_info' => array('field_name' => $instance['field_name'])));
      
      #$paths_for_field = $path_array[$instance['field_name']];
      /*
      foreach ($paths_for_field as $mapping => $path) {
        $starting_concept = $path['starting_concept'];
        $full_path = $path['full_path'];
        $data = $path['datatype_property'];
        $disamb = $path['disamb'];
      }
      */
      
      
      $new_values = field_get_items($entity_type,$entity,$instance['field_name']);
      $delete = !property_exists($entity,'is_new') || !$entity->is_new;
      $db_values = array();
      foreach($new_values as $delta) {
        dpm($instance);
        dpm($delta);
        
        $set_val = _wisski_core_divide_field_data($entity, $instance, $delta);

        dpm($set_val);

        // pb handling if possible.        
        if(isset($path_array[$instance['field_name']])) {
        
        $paths_for_field = $path_array[$instance['field_name']];
        
#        dpm($set_val);

        foreach ($paths_for_field as $mapping => $path) {
        
          // evil hack
          if(!isset($set_val[$mapping]))
            continue;
        
          $starting_concept = $path['starting_concept'];
          $full_path = $path['full_path'];
          $data = $path['datatype_property'];
          $disamb = $path['disamb'];
        

          if ($news = wisski_salz_pb_update($individual,$entity->title,$starting_concept,$full_path,$data,$disamb,$set_val[$mapping],$delete)) {
//          dpm($news);
            foreach($news as $class => $inds) {
              if (!isset($new_individuals[$class])) $new_individuals[$class] = array();
              $new_individuals[$class] += $inds;
            }
          }
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
          $new_entity = entity_create('wisski_individual',$info);
          entity_save('wisski_individual',$new_entity);
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
 * This function decides which of the field data is stored by this storage engine
 * and which to keep in the db table. The storage engine data is returned.
 */
function _wisski_core_divide_field_data($entity,$instance, array $data) {

  dpm(array(__FUNCTION__=>func_get_args()));
  //while in development we only store ['value'] entries, rest is DB stuff
  $out = array();
  $saveable = db_select('wisski_pb_pathdata','p')
              ->fields('p',array('connected_field_property'))
              ->condition('connected_field',$instance['field_name'])
              ->condition('connected_bundle',$instance['bundle'])
              ->execute()
              ->fetchAllAssoc('connected_field_property');
  if (!empty($saveable)) {
    foreach (array_keys($saveable) as $mapping) {
      if (isset($data[$mapping])) {
        $out[$mapping] = $data[$mapping];
        unset($data[$mapping]);
      }
    }
  }
  if (!empty($data)) {
    $result = db_select('wisski_special_field_cache','cache')
      ->fields('cache')
      ->condition('instance_id',$instance['id'])
      ->condition('entity_uri',$entity->uri)
//      ->condition('langcode',$langcode)
      ->execute();
    if ($result->rowCount() === 0) {
      db_insert('wisski_special_field_cache')
        ->fields(array(
          'instance_id' => $instance['id'],
          'entity_uri' => $entity->uri,
          'field_data' => serialize($data),
//          'langcode' => $langcode,
          ))
        ->execute();
    } else {
      db_update('wisski_special_field_cache')
        ->fields(array('field_data' => serialize($data)))
        ->condition('instance_id',$instance['id'])
        ->condition('entity_uri',$entity->uri)
//        ->condition('langcode',$langcode)
        ->execute();
    }
  }
  return $out;
}

function _wisski_core_gather_field_data($entity,$instance,$data) {
  
//  dpm(array(__FUNCTION__=>func_get_args()));
  $out = array();
  $loadable = db_select('wisski_pb_pathdata','p')
              ->fields('p',array('connected_field_property'))
              ->condition('connected_field',$instance['field_name'])
              ->condition('connected_bundle',$instance['bundle'])
              ->execute()
              ->fetchAllAssoc('connected_field_property');
  if (!empty($loadable)) {
    foreach (array_keys($loadable) as $mapping) {
      if (isset($data[$mapping])) {
        $out[$mapping] = $data[$mapping];
      }
    }
  }
  $result = db_select('wisski_special_field_cache','cache')
              ->fields('cache')
              ->condition('instance_id',$instance['id'])
              ->condition('entity_uri',$entity->uri)
//              ->condition('langcode',$langcode)
              ->execute()
              ->fetchObject();
  if (!empty($result) && isset($result->field_data)) {
    $out += unserialize($result->field_data);
  }
  return $out;
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
  watchdog(__FUNCTION__,serialize($query));
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