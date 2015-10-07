<?php

//////////////////////////////////////////////
// SWIPS - (Field) {S}torage {w}ith {P}ath{s}
//////////////////////////////////////////////

include_once 'wisski_core.pathbuilder.inc';

/**
* implements @see hook_field_storage_info
*/
function wisski_core_field_storage_info() {

//  dpm("Call to hook_field_storage_info");  
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
//  dpm(func_get_args(),__FUNCTION__);
  //we track the loading process for all entities to ensure we know
  //where the field collection is used
/*
  $loaded_field_collections = &drupal_static(__FUNCTION__.'field_collections');
  //we must make sure that "our" field collection items are stored correctly
  if ($entity_type == 'field_collection_item') {
    $strange_entities = array();
    foreach ($queried_entities as $key => $ent) {
      $top_bundle = wisski_core_get_bundles_for_field($ent->field_name);
      if (empty($top_bundle)) {
        $strange_entities[$key] = $ent;
      }
    }
    if (!empty($strange_entities)) {
      module_invoke('field_sql_storage','field_storage_pre_load',$entity_type, $strange_entities, $age, $skip_fields, $options);
      $queried_entities = array_diff_key($queried_entities,$strange_entities);
    }
    $referencing_entities = array();
    foreach(array_keys($queried_entities) as $field_collection_id) {
      if (isset($loaded_field_collections[$field_collection_id])) {
        $referencing_entities[$field_collection_id] = $loaded_field_collections[$field_collection_id];
      }
    }
    dpm($referencing_entities,__FUNCTION__.'BOO YA');
  }
*/  
  if($entity_type == 'wisski_individual' /*|| $entity_type === 'field_collection_item'*/) {

//    dpm($queried_entities);

/*
    $wisski_semaphore = &drupal_static(__FUNCTION__);
    if(!isset($wisski_semaphore))
      $wisski_semaphore = 0;
      
    if (isset($wisski_semaphore) && $wisski_semaphore > 3) {
      dpm(func_get_args(),'DOUBLE CALL '.__FUNCTION__);
      drupal_set_message(serialize(debug_backtrace()));
//      throw new Exception('breaker');
      return;
    }
    $wisski_semaphore++;
*/
      
    if ($age == FIELD_LOAD_REVISION) {
      dpm(__FUNCTION__.": load revision");
      //load from default storage
//      module_invoke('field_sql_storage','field_storage_load',$entity_type, $queried_entities, $age, $skip_fields, $options);
    } else {
#      $loaded = &drupal_static(__FUNCTION__);
#      if (!isset($loaded)) $loaded = array();
      
      foreach($queried_entities as $entity_id => $entity) {

/*        if (isset($referencing_entities[$entity_id])) {
          $loaded_field_collection = $referencing_entities[$entity_id];
        } else {
          //we don't know this one
          //it must come from elsewhere i.e. is not connected to a wisski_core_entity
          trigger_error('failed loading due to referencing entities',E_USER_WARNING);
          module_invoke('field_sql_storage','field_storage_pre_load',$entity_type, $queried_entities, $age, $skip_fields, $options);
          return;
        }
*/        //since we have at least two entity_types to care about we need
        //a generic approach to the field instances
        list($entity_id,,$bundle_name) = entity_extract_ids($entity_type,$entity);
        $instances = field_info_instances($entity_type,$bundle_name);
        //reading the uridirectly will only work for wisski_individual
        //field_collections are handled by our cache
        if (isset($loaded_field_collections)) {
          if (isset($loaded_field_collections['target_uri'])) {
            $entity_uri = $loaded_field_collections['target_uri'];
          } else {
            //oops, not sure what to do now
            throw new Exception('field collection has no related URI');
          }
        } else {
          if (empty($entity->uri)) {
            static $boo = 0;
            if ($boo < 1) {
              $entity = entity_load_single($entity_type,$entity->id);
              $boo++;
            } else {
              dpm($entity,'false entity');
              throw new Exception('break');
            }
          }
          $entity_uri = $entity->uri;
        }
        $field_info = array();

#        $wrapper = entity_metadata_wrapper('wisski_individual',$entity);

#        dpm(array('instances' => $instances,'property info' => (array)($wrapper->getPropertyInfo())));

        foreach($instances as $instance) {
#          $type = $wrapper->$instance['field_name']->type();
          //dpm($instance,'$instance '.$instance['field_name'].$instance['id']);
          
#          if (in_array($entity->id,$loaded)) {
            //we don't want to load the fields multiple times
#            $skip_fields[$instance['field_name']] = $entity->id;
#            continue;
#          }
          if (isset($loaded_field_collection['paths'])) {
            $path_array = $loaded_field_collection['paths'];
          } else {
            $path_array = wisski_core_make_path_array(array('connected_bundle'=>$instance['bundle'],'field_info' => array('field_name' => $instance['field_name'])));
          }
#          dpm($path_array,'computed path array');
          $info = array();
          if(isset($path_array[$instance['field_name']])) {

            $paths_for_field = $path_array[$instance['field_name']];
            if (!empty($paths_for_field)) {
#              dpm($paths_for_field,'paths for field '.$instance['field_name']);
              $starting_concept = current($paths_for_field);
              $starting_concept = $starting_concept['starting_concept'];
              $info = wisski_salz_pb_query_single_uri($entity_uri,$starting_concept,$paths_for_field);
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
//          dpm($info,'load info');
//          throw new Exception('breaker');
          $info = _wisski_core_gather_field_data($entity_uri, $bundle_name,$instance['field_name'],$info);
          // key order is: [entity_uri][delta][mapping] => values
//          dpm($info,'gathered info');
//          dpm($entitylll);
          
//          drupal_set_message(serialize($info));
          foreach($info as $result_entity_uri => $uri_array) {
            
            if(!is_array($uri_array))
              continue;

/*            foreach($uri_array as $enturi => $lang_array) {

              if(!is_array($lang_array))
                continue;*/
//              foreach($lang_array as $langcode => $deltarray) {
                  // get the language code for the field
//                  $language = field_language($entity->type, $entity, $instance['field_name'], $langcode);
                  
                  // if it is empty, it probably is LANGUAGE_NONE
                  if(empty($language))
                    $language = LANGUAGE_NONE;

//                if(!is_array($deltarray)) continue;
                if ($instance['widget']['module'] === 'double_field') {
                  //double_field seems to get something wrong
                  if (count($uri_array) === 2) {
                    $new_delta = array_merge($uri_array[0],$uri_array[1]);
                    $uri_array = array($new_delta);
                  }
                }
                foreach($uri_array as $delta => $values) { 

                    if ($instance['widget']['module'] === 'entityreference') {
                      // make sure we load the right one by loading it
                      // the value might be a non-numeric id
                      // by loading it it is a numeric one after that.
                      if(!is_numeric($values['target_id'])) {
                        $tmp_entity = entity_load_single('wisski_individual',$values['target_id']);
                      
                        $values['target_id'] = $tmp_entity->id;
                      }
                    }
                    if ($instance['widget']['module'] === 'field_collection') {
                      //hack to ensure field_collection working correctly:
                      //we always need a revision id, so let's assume
                      //we have no revision
                      if (!isset($values['revision_id'])) {
                        $values['revision_id'] = $values['value'];
                      }
                      //dpm($info,'values for field collection');
                      //pb_query gives us the values, which should save the target id
                      //plus the target entity uri, which is not saved in the FieldCollectionItem
                      //but used by our loading routine.
                      //The paths we need for loading the fields contained in the
                      //field collection can be gathered from $paths_for_field
                      $group_paths = array();
                      foreach($paths_for_field as $pff_key => $pff) {
                        if ($pff_key != 'value' && $pff['group_id'] === $paths_for_field['value']['group_id']) {
                          $group_paths[$pff_key] = $pff;
                        }
                      }
                      /*
                      $loaded_field_collections[$values['value']] = array(
                        'target_uri' => isset($values['target_uri']) ? $values['target_uri'] : NULL,
                        'paths' => $group_paths,
                      );
                      */
                      $fc_uri = $values['target_uri'];
 #                     $fc_exists = db_select('field_collection_item','f')
  #                      ->fields('f')
   #                     ->condition('uri',$fc_uri)
    #                    ->execute();
     #                 if ($fc_exists->rowCount() > 0) {
      #                  $fc_exists = $fc_exists->fetchObject();
       #                 $fc_id = $fc_exists->item_id;
        #                $fc = entity_load_single('field_collection_item',$fc_id);
         #               //$fc->updateHostEntity($entity);
          #              //$fc->is_new = TRUE;
           #             //$fc->setHostEntity('wisski_individual',$entity);
            #          } else {
                        $fc = entity_create('field_collection_item',array('field_name' => $instance['field_name']));
                        $fc->uri = $fc_uri;
                        $fc->setHostEntity('wisski_individual',$entity);
           #           }
                      //$fc->bundle = $instance['field_name'];
                      //dpm($fc,'created collection');
                      //entity_save('field_collection_item',$fc);
                      $fc->save(TRUE);
                      //dpm($fc,'saved collection');
                      $values['value'] = $fc->item_id;
                      $values['revision_id'] = $fc->revision_id;
                      field_attach_load('field_collection_item',array($fc->item_id=>$fc));
                      //$values = (array)$fc;
                    }
                    
                    $curarr = &$entity->$instance['field_name'];                                                      
                    $curarr[$language][$delta] = $values;
          #}	
                }
              }
//            }
//          } 
          

          
          //$entity->$instance['field_name'] = $info;
          
          $skip_fields[$instance['field_id']] = $entity_id;
        }//END foreach $instances

        if ($entity_type === 'wisski_individual') wisski_core_set_short_title($entity,FALSE);
//dpm($entity,'entity after '.__METHOD__);
      }//END foreach $queried_entities
#      $loaded += array_keys($queried_entities);
    }
    
  
#    $wisski_semaphore--;

  } else {
    module_invoke('field_sql_storage','field_storage_pre_load',$entity_type, $queried_entities, $age, $skip_fields, $options);
  }
  
//  return;
}

/**
 * implements hook_field_storage_pre_insert
 */
function wisski_core_field_storage_pre_insert($entity_type,$entity,&$skip_fields) {
  
  if ($entity_type === 'field_collection_item') {
    
//    dpm(func_get_args(),__METHOD__);
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
    //$entity_info = (array) $entity;
    if ($pattern) $title = wisski_core_make_short_title($entity,array_expand($pattern));
  }

  $entity->title = $title?:wisski_salz_ensure_short_namespace($entity->uri);

  db_update('wisski_entity_data')
    ->fields(array('title' => $entity->title,'dirty' => 0))
    ->condition('uri',$entity->uri)
    ->condition('type',$entity->type)
    ->execute();

}

/**
* implements hook_field_storage_load
*/ 
function wisski_core_field_storage_load($entity_type, $entities, $age, $fields, $options) {
//  dpm(func_get_args(),__FUNCTION__);
  if ($entity_type === 'field_collection_item') {
    foreach ($entities as $entity_id => &$entity) {
      wisski_core_ensure_field_collection_uri($entity);
      $super_bundles = wisski_core_get_bundles_for_field($entity->field_name);
      $bundle_name = $super_bundles['bundle'];
//    dpm($fields, 'fields');
      foreach ($fields as $field_id => $value) {
        $field = field_info_field_by_id($field_id);
//        dpm($field,'field info');
        $field_name = $field['field_name'];
        $paths_for_field = wisski_core_make_path_array(array('field_info'=>array('field_name' => $field_name)));
//        dpm($paths_for_field,__FUNCTION__.' paths');
        if (isset($paths_for_field[$field_name])) {
          $paths_for_field = $paths_for_field[$field_name];
          $starting_concept = current($paths_for_field);
          $starting_concept = $starting_concept['starting_concept'];
          $info = wisski_salz_pb_query_single_uri($entity->uri,$starting_concept,$paths_for_field);
//          dpm($info,'info');
        }
        $info = _wisski_core_gather_field_data($entity->uri,$bundle_name,$field_name,$info);
        if ($field['module'] === 'field_collection') {
          $values = &$info[$entity->uri][0];
          $fc_uri = $values['target_uri'];

          $fc = entity_create('field_collection_item',array('field_name' => $field_name));
          $fc->uri = $fc_uri;
#          $entity->is_new = true;

          $fc->setHostEntity('field_collection_item',$entity);

          $fc->save(TRUE);

          $values['value'] = $fc->item_id;
          $values['revision_id'] = $fc->revision_id;
          field_attach_load('field_collection_item',array($fc->item_id=>$fc));            
//          dpm($field + $info, "info: muahaha ");
        }
      
        $entity->{$field_name}[LANGUAGE_NONE] = $info[$entity->uri];
       }
    }
  }
//  dpm($entities,'after');
  if ($entity_type != 'wisski_individual') module_invoke('field_sql_storage','field_storage_load',$entity_type, $entities, $age, $fields, $options);
}



/**
* implements hook_field_storage_write
*/
function wisski_core_field_storage_write($entity_type, $entity, $op, $fields) {

  static $loading_order = array();
  $referenced_field_collections = &drupal_static(__FUNCTION__.'references');
  if (!isset($referenced_field_collections)) $referenced_field_collectons = array();
  list($entity_id,,$bundle_name) = entity_extract_ids($entity_type,$entity);
  $entity_title = entity_label($entity_type,$entity);
  if ($entity_type === 'field_collection_item') {
    wisski_core_ensure_field_collection_uri($entity);
    //dpm($entity->hostEntity(),'host entity');
    $top_bundle = wisski_core_get_bundles_for_field($bundle_name);
    $connected_bundle = $top_bundle['bundle'];
    if (empty($bundle_name)) {
      //no top bundle is given for this field collection in 'wisski_individual'
      //i.e. this is not connected to a 'wisski_individual'
      module_invoke('field_sql_storage','field_storage_write',$entity_type, $entity, $op, $fields);
      return;
    }
    if (!property_exists($entity,'uri') || empty($entity->uri) || !isset($referenced_field_collections[$bundle_name][$entity_id]['bundle_name'])) {
      //here we can profit from the bundle name of a field collection
      //being equal to the field name it is referenced by
      $referenced_field_collections[$bundle_name] = array(
        'fields' => $fields,
        'field_collection' => $entity,
      );
      
      $return = TRUE;
    }
    
  }
  $loading_order[$entity_id] = $entity;
//  dpm($loading_order,'loading order');
  if (!isset($connected_bundle)) $connected_bundle = $bundle_name;
  //$loading_order[] = array($entity_type,$bundle_name,$entity_id,$connected_bundle,isset($entity->uri)?$entity->uri:'');
#  if (isset($return) && $return) return;
//  dpm($referenced_field_collections,'referenced field collections');
  
//  dpm("call to hook_field_storage_write for $entity_type");
  if ($entity_type == 'wisski_individual' || $entity_type === 'field_collection_item') {
    
    $bundle_info = NULL;
//    dpm(func_get_args(),__FUNCTION__);
    if (property_exists($entity,'uri') && $entity->uri != NULL) {
      $individual = $entity->uri;
    } else {
#      dpm($entity,'false entity');
#      trigger_error("The entity ".$entity_title." does not have a uri",E_USER_ERROR);
#      return;
      $individual =  wisski_core_make_uri($entity_title,$connected_bundle);
      $entity->uri = $individual;
      dpm($entity,'false entity');
#      db_update('wisski_entity_data')
#        ->fields(array('uri' => $individual))
#        ->condition('id',$entity->id)
#        ->execute();
    }
//    dpm(entity_uri($entity_type,$entity));
//    dpm("computed uri: ".$individual);
    $new_individuals = array();
    //calling the field by reference gives us the possibility to add fields
    foreach($fields as &$field_id) {
//      dpm("Tick 2");
      if (!is_numeric($field_id)) {          
        $field_id = $field_id['field_id'];
      }
      $field = field_info_field_by_id($field_id);
      $field_name = $field['field_name'];
      
      $new_values = field_get_items($entity_type,$entity,$field_name);
//      dpm("Tick 3");      
      
      $path_array = wisski_core_make_path_array(array('connected_bundle'=>$connected_bundle,'field_info' => array('field_name' => $field_name)));
      $set_val = _wisski_core_divide_field_data($entity->uri, $connected_bundle, $field_name, array($entity->uri=>$new_values));
//      if ($field['type'] == 'field_collection') dpm($set_val,'set_val');
/*      dpm(array(
        'entity' => $entity,
        //'uri'=>$entity->uri,
        'new_values'=>$new_values,
        'path_array'=>$path_array,
        //'field_name'=>$field_name,
        'bundle_name'=>$bundle_name,
        'connected_bundle'=>$connected_bundle,
        'set_val'=>$set_val,
      ), 'computed values for '.$field_name);*/
      $delete = !property_exists($entity,'is_new') || !$entity->is_new;

      if (empty($set_val)) continue;

      foreach($set_val[$entity->uri] as $delta) {
        
//        if ($field['type'] == 'entityreference') dpm($delta,'delta');
        
//        if ($field['type'] === 'field_collection') {
//          $field_collection_id = $delta['value'];
//          if (isset($referenced_field_collections[$field_name][$field_collection_id])) {
//            $field_collection_info = &$referenced_field_collections[$field_name][$field_collection_id];
//            $field_collection = $field_collection_info['field_collection'];
//            if (!property_exists($field_collection,'uri') || empty($field_collection->uri)) {
//              $field_collection->uri = wisski_core_make_uri('fc_'.$field_collection->item_id.'_',$bundle_name,TRUE);
//              unset($field_collection_info['field_collection']);
//              $field_collection_info['bundle_name'] = $bundle_name;
              //this one will save the field collection again
              //this time with a valid uri
//              field_attach_update('field_collection_item',$field_collection);
//            }
//          }
//        }

        
        // pb handling if possible.        
        if(isset($path_array[$field_name])) {
        
        $paths_for_field = $path_array[$field_name];
        
#        dpm($set_val);

        foreach ($paths_for_field as $mapping => $path) {
        
          // evil hack
          if(!isset($delta[$mapping])) {
            dpm($delta,'something wrong');
            echo 'something wrong';
            continue;
          }
          $starting_concept = $path['starting_concept'];
          $path_array = $path['path_array'];
          $data = $path['datatype_property'];
          $disamb = $path['disamb'];
//          dpm("ecco the dolphin");
          if ($field['type'] === 'field_collection') {
            if (isset($loading_order[$delta['value']])) {
              $target_collection = $loading_order[$delta['value']];
            } else {
              echo 'SOMETHING\'S WRONG';
              $target_collection = entity_load_single('field_collection_item',$delta['value']);
            }
            /*dpm(array(
              'target_collection'=>$target_collection,
              'HOST'=>$target_collection->hostEntity(),
              'entity'=>$entity,
            ),'TARGET');
            $host = $target_collection->hostEntity();
            if (empty($host)) {
              $entity->is_new = TRUE;
              $target_collection->setHostEntity($entity_type,$entity);
            }*/
            $target_uri = $target_collection->uri;
          } else $target_uri = NULL;
          if ($news = wisski_salz_pb_update($individual,$entity_title,$starting_concept,$path_array,$data,$disamb,$delta[$mapping],$delete,$target_uri)) {
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

function wisski_core_ensure_uri($entity) {

	if (property_exists($entity,'uri') && isset($entity->uri)) return;
	list(,,$bundle_name) = entity_extract_ids('wisski_individual',$entity);
	$uri = wisski_core_make_uri('',$bundle_name,TRUE);
	$entity->uri = $uri;
}

function wisski_core_make_uri($entity_title,$bundle_name,$ind_only = TRUE) {
  
  include_once(dirname(__FILE__) . '/wisski_core.lod.inc');
  return wisski_core_lod_make_internal_uri($entity_title, $bundle_name, $ind_only);
}

function wisski_core_ensure_field_collection_uri($entity) {

  if (!property_exists($entity,'uri') || empty($entity->uri)) {
    $field_name = $entity->field_name;
    $top_bundles = wisski_core_get_bundles_for_field($field_name);
    if (!empty($top_bundles)) {
      if (strpos($field_name,'field_') === 0) {
        $field_name = substr($field_name,6); 
      }
      $entity->uri = wisski_core_make_uri($field_name,$top_bundles['bundle'],TRUE);
    }
  }
}

/**
 * This function decides which of the field data is stored by this storage engine
 * and which to keep in the db table. The storage engine data is returned.
 */
function _wisski_core_divide_field_data($entity_uri,$bundle_name,$field_name,array $data) {

//  dpm(func_get_args(),__FUNCTION__);
  // key order is: [entity_uri][delta][mapping] => values
  $out = array();
  $saveable = db_select('wisski_pb_pathdata','p')
              ->fields('p',array('connected_field_property'))
              ->condition('connected_field',$field_name)
              ->condition('connected_bundle',$bundle_name)
              ->execute()
              ->fetchAllAssoc('connected_field_property');
  $saveable = array_keys($saveable);
  foreach ($data[$entity_uri] as $delta => &$sub_data) {
    foreach ($sub_data as $mapping => $values) {
      if (in_array($mapping,$saveable)) {
        $out[$entity_uri][$delta][$mapping] = $values;
        unset($sub_data[$mapping]);
        if (empty($sub_data)) unset($data[$entity_uri][$delta]);
      }
    }  
  }
//  dpm('insert into DB: '.serialize($data[$entity_uri]));
  if (!empty($data[$entity_uri])) {
      $result = db_select('wisski_special_field_cache','cache')
        ->fields('cache')
        ->condition('bundle_name',$bundle_name)
        ->condition('field_name',$field_name)
        ->condition('entity_uri',$entity_uri)
//      ->condition('langcode',$langcode)
        ->execute();
      if ($result->rowCount() === 0) {
        db_insert('wisski_special_field_cache')
          ->fields(array(
            'bundle_name' => $bundle_name,
            'field_name' => $field_name,
            'entity_uri' => $entity_uri,
            'field_data' => serialize($data[$entity_uri]),
//	          'langcode' => $langcode,
            ))
          ->execute();
      } else {
        db_update('wisski_special_field_cache')
          ->fields(array('field_data' => serialize($data[$entity_uri])))
          ->condition('bundle_name',$bundle_name)
          ->condition('field_name',$field_name)
          ->condition('entity_uri',$entity_uri)
//        ->condition('langcode',$langcode)
          ->execute();
      }
    }
  return $out;
}

function _wisski_core_gather_field_data($entity_uri,$bundle_name,$field_name,$data) {
  
  //key order is: [entity_uri][delta][mapping] => values
        
//dpm(func_get_args(),__METHOD__);
  $out = $data;
  if (!isset($out[$entity_uri])) $out[$entity_uri] = array();
  $result = db_select('wisski_special_field_cache','cache')
              ->fields('cache')
              ->condition('bundle_name',$bundle_name)
              ->condition('field_name',$field_name)
              ->condition('entity_uri',$entity_uri)
//              ->condition('langcode',$langcode)
              ->execute()
              ->fetchObject();
  if (!empty($result) && isset($result->field_data)) {    
    $out[$entity_uri] += unserialize($result->field_data);
  }
//dpm($out,__METHOD__.' Result');
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
//  dpm(func_get_args(),__FUNCTION__);
  return array();
//  watchdog(__FUNCTION__,serialize($query));
//  ddebug_backtrace();
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
    dpm($query->propertyConditions,'Property Conditions');
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
