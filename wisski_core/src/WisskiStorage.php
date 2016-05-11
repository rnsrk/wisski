<?php

namespace Drupal\wisski_core;

use Drupal\Core\Entity\ContentEntityStorageBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityStorageException;

use Drupal\Core\Field\FieldDefinitionInterface;

use Drupal\file\FileStorage;
use Drupal\file\Entity\File;

use Drupal\wisski_core\Entity\WisskiEntity;
use Drupal\wisski_core\Query\WisskiQueryInterface;
//use Drupal\wisski_core\WisskiInvalidArgumentException;

use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Test Storage that returns a Singleton Entity, so we can see what the FieldItemInterface does
 */
class WisskiStorage extends ContentEntityStorageBase implements WisskiStorageInterface {

  /**
   * stores mappings from entity IDs to arrays of storages, that handle the id
   * and arrays of bundles the entity is in
   */
  private $entity_info = array();

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $ids = NULL) {
//  dpm($ids,__METHOD__);
    $entities = array();
    $values = $this->getEntityInfo($ids);
//  dpm($values,'values');    
    foreach ($ids as $id) {
      //@TODO combine this with getEntityInfo
      if (!empty($values[$id])) $entities[$id] = $this->create($values[$id]);
    }
    return $entities;
  }

  /**
   * gathers entity field info from all available adapters
   * @param $id entity ID
   * @param $cached TRUE for static caching, FALSE for forced update
   * @return array keyed by entity id containing entity field info
   */
  protected function getEntityInfo(array $ids,$cached = FALSE) {

#    $bundles = $this->entityManager->getBundleInfo('wisski_individual');
//    dpm($bundles);
    $entity_info = &$this->entity_info;
    if ($cached) {
      $ids = array_diff_key($ids,$entity_info);
      if (empty($ids)) return $entity_info;
    }
    $adapters = entity_load_multiple('wisski_salz_adapter');
#    dpm(serialize($adapters));
#    drupal_set_message("hallo welt");
    $info = array();
    $all_field_definitions = array();
    $all_field_definitions['BASE_FIELDS'] = array();
    $field_storage_definitions = $this->entityManager->getFieldStorageDefinitions('wisski_individual');
    foreach ($field_storage_definitions as $key => $fsd) {
      if ($fd_ids = \Drupal::entityQuery('field_config')->condition('id',$key,'ENDS_WITH')->execute()) {
        $fds = $this->entityManager->getStorage('field_config')->loadMultiple($fd_ids);
        foreach ($fds as $fd) {
          $all_field_definitions[$fd->get('bundle')][$key] = $fd;
        }
      } else {
        $all_field_definitions['BASE_FIELDS'][$key] = $fsd;
      }
    }
    dpm($all_field_definitions,'field_definitions before');
    // for every id
    foreach($ids as $id) {
      // ask all adapters
      foreach($adapters as $aid => $adapter) {
        // if they know that id
        if($adapter->hasEntity($id)) {
          // if so - ask for the bundles for that id
          //$bundles = $adapter->getBundleIdsForEntityId($id);
          #drupal_set_message("Yes, I know " . $id . " and I am " . $aid . ". The bundles are " . serialize($bundles) . ".");
          foreach($all_field_definitions as $bundleid => $field_definitions) {
            dpm($field_definitions,'Field defs for '.$bundleid);
            if ($bundleid === 'BASE_FIELDS') $bundleid = NULL;
            else {
              //$field_definitions = $this->entityManager->getFieldDefinitions('wisski_individual',$bundleid);
              $view_ids = \Drupal::entityQuery('entity_view_display')
                ->condition('id', 'wisski_individual.' . $bundleid . '.', 'STARTS_WITH')
                ->execute();
              $entity_view_displays = \Drupal::entityManager()->getStorage('entity_view_display')->loadMultiple($view_ids);
            }
                      #drupal_set_message("asking for: " . serialize(array_keys($field_definitions)));
            try {
              $adapter_info = $adapter->loadFieldValues(array($id),array_keys($field_definitions),$bundleid);

              #drupal_set_message('ive got: ' . serialize($adapter_info));
              //dpm($adapter_info,$aid);
              
              foreach($adapter_info as $entity_id => $entity_values) {
                //if we don't know about that entity yet, this adapter's info can be used without a change
                if (!isset($info[$entity_id])) $info[$entity_id] = $entity_values;
                //else {
                  //integrate additional values on existing entities
                  foreach($entity_values as $field_name => $value) {
                    if (empty($value)) continue;
                    #drupal_set_message("looking for $entity_id in " . serialize($info));
                    $actual_field_info = $info[$entity_id][$field_name];
                    
                    dpm($field_definitions,$field_name.' short before');  
                    // if there is no field definition throw an error.
                    if(empty($field_def = $field_definitions[$field_name])) {
                      if (!isset($all_field_definitions['BASE_FIELDS'][$field_name])) {
                        drupal_set_message("Asked for field definition of field " . $field_name . " on WissKI Individual but there was nothing.", 'error');
                        continue;
                      } else $field_def = $all_field_definitions['BASE_FIELDS'][$field_name];
                    }
                    
                    $cardinality = 1;
                    
#                    $cardinality = $field_def->getCardinality();
                    if ($field_def instanceof BaseFieldDefinition) {
                      //this is a base field and cannot have multiple values
                      //@TODO make sure, we load the RIGHT value
                      if (!empty($actual_field_info) && $value != $actual_field_info) drupal_set_message(
                        $this->t('1Multiple values for %field_name in entity %id: %val1, %val2',array(
                          '%field_name'=>$field_name,
                          '%id'=>$entity_id,
                          '%val1'=>is_array($actual_field_info)?implode($actual_field_info,', '):$actual_field_info,
                          '%val2'=>$value,
                        )),'error');
                    }
                    elseif ($cardinality === 1) {
                      //this field cannot have multiple values
                      //@TODO make sure, we load the RIGHT value
                      if (!empty($actual_field_info) && $value != $actual_field_info) drupal_set_message(
                        $this->t('Multiple values for field %field_name in entity %id: %val1, %val2',array(
                          '%field_name'=>$field_name,
                          '%id'=>$entity_id,
                          '%val1'=>is_array($actual_field_info)?implode($actual_field_info,', '):$actual_field_info,
                          '%val2'=>$value,
                        )),'error');
                    }
                    else {
                      if (!is_array($actual_field_info)) $actual_field_info = array($actual_field_info);
                      if ($cardinality > 0 && count($actual_field_info) >= $cardinality && !in_array($value,$actual_field_info)) {
                        drupal_set_message(
                          $this->t('Too many values for field %field_name in entity %id. %card allowed. Tried to add %val2',array(
                            '%field_name'=>$field_name,
                            '%id'=>$entity_id,
                            '%card'=>$cardinality,
                            '%val1'=>$value, )),'error');
                      }
                    }
                    //dpm($field_def->getType(),$field_name);
                    if ($field_def->getType() === 'image') {
                      // we assume that $value is an image URI which is to be rplaced by a FileID
                      drupal_set_message('we got an image to handle. Field name:'.$field_name);
                      dpm($value,'image_info');
                      foreach ($entity_view_displays as $evd) {
                        $component = $evd->getComponent($field_name);
                        dpm($component['type'],$field_name);
                      }
                      // $value must be the image uri
                      $file_uri = current($value);
                      // we now check for an existing 'file managed' with that uri
                      $query = \Drupal::entityQuery('file');
                      $query->condition('uri',$file_uri);
                      $ids = $query->execute();
                      if (!empty($ids)) {
                        // if there is one, we must set the field value to the image's FID
                        $value = current($ids);
                        dpm('replaced with existing file '.current($ids));
                        //@TODO find out what to do if there is more than one file with that uri
                      } else {
                        // if we have no managed file with that uri, we try to generate one
                        try {
                          
                          //$file = File::create(array(
                          //  'uri'=>$file_uri,
                          //));
                          //dpm($file,'File Object');
                          //$file->save();
                          
                          $data = file_get_contents($file_uri);
                          $stripped_filename = substr($file_uri,strrpos($file_uri,'/'));
                          $file = file_save_data($data, 'public://'.$stripped_filename);
                          $value = $file->id();
                          dpm('replaced with new file '.$file->id());
                        } catch (EntityStorageException $e) {
                          drupal_set_message($this->t('Could not create file with uri %uri. Exception Message: %message',array('%uri'=>$file_uri,'%message'=>$e->getMessage())),'error');
                        }
                      }
                    }
                    if (is_array($actual_field_info)) {
                      $actual_field_info[] = $value;
                      //array_unique($actual_field_info);
                    }
                    else
                      $actual_field_info = $value;
                    $info[$entity_id][$field_name] = $actual_field_info;
                  }
                //}  
              }
            } catch (\Exception $e) {
              drupal_set_message('Could not load entities in adapter '.$adapter->id() . ' because ' . serialize($e));
            }              
          }     
          
        } else {
#          drupal_set_message("No, I don't know " . $id . " and I am " . $aid . ".");
        }
      }
    }
/*

    foreach ($bundles as $bundle_name => $bundle_label) {
      $field_definitions = $this->entityManager->getFieldDefinitions('wisski_individual',$bundle_name);
      dpm($field_definitions,$bundle_name);
      foreach ($adapters as $aid => $adapter) {
  //      if ($adapter->getEngineId() === 'sparql11_with_pb') continue;
        try {
          $adapter_info = $adapter->loadFieldValues($ids,array_keys($field_definitions));
          dpm($adapter_info,"info from $aid");#return array();
          foreach($adapter_info as $entity_id => $entity_values) {
            //if we don't know about that entity yet, this adapter's info can be used without a change
            if (!isset($info[$entity_id])) $info[$entity_id] = $entity_values;
            else {
              //integrate additional values on existing entities
              foreach($entity_values as $field_name => $value) {
                if (empty($value)) continue;
                $actual_field_info = $info[$entity_id][$field_name];
                
                // if there is no field definition throw an error.
                if(empty($field_definitions[$field_name])) {
                  drupal_set_message("Asked for field definition of field " . $field_name . " on WissKI Individual but there was nothing.", 'error');
                  continue;
                }
                
                if ($field_definitions[$field_name] instanceof BaseFieldDefinition) {
                  //this is a base field and cannot have multiple values
                  //@TODO make sure, we load the RIGHT value
                  if (!empty($actual_field_info) && $actual_field_info != $value) drupal_set_message(
                    $this->t('1Multiple values for %field_name in entity %id: %val1, %val2',array(
                      '%field_name'=>$field_name,
                      '%id'=>$entity_id,
                      '%val1'=>is_array($actual_field_info)?implode($actual_field_info,', '):$actual_field_info,
                      '%val2'=>$value,
                    )),'error');
                  else $info[$entity_id][$field_name] = $value;
                  continue;
                }
                
                drupal_set_message("what do we have here: " . serialize($field_definitions[$field_name]));
                
                //rest is a field
                $cardinality = $field_definitions[$field_name]->getCardinality();
                
                if ($cardinality === 1) {
                  //this field cannot have multiple values
                  //@TODO make sure, we load the RIGHT value
                  if (!empty($actual_field_info) && $actual_field_info != $value) drupal_set_message(
                    $this->t('Multiple values for field %field_name in entity %id: %val1, %val2',array(
                      '%field_name'=>$field_name,
                      '%id'=>$entity_id,
                      '%val1'=>is_array($actual_field_info)?implode($actual_field_info,', '):$actual_field_info,
                      '%val2'=>$value,
                    )),'error');
                  else $info[$entity_id][$field_name] = $value;
                  continue;
                }
                if (!is_array($actual_field_info)) $actual_field_info = array($actual_field_info);
                if ($cardinality > 0 && count($actual_field_info) >= $cardinality) {
                  drupal_set_message(
                    $this->t('Too many values for field %field_name in entity %id. %card allowed. Tried to add %val2',array(
                      '%field_name'=>$field_name,
                      '%id'=>$entity_id,
                      '%card'=>$cardinality,
                      '%val1'=>$value, )),'error');
                } else $actual_field_info[] = $value;
                $info[$entity_id][$field_name] = $actual_field_info;
              }
            }
          }
          //dpm(array('adapter_info'=>$adapter_info,'entity_info_after'=>$info),$aid);
        } catch (\Exception $e) {
          drupal_set_message('Could not load entities in adapter '.$adapter->id() . ' because ' . serialize($e));
        }
      }
    }*/
    $entity_info = WisskiHelper::array_merge_nonempty($entity_info,$info);
    dpm(func_get_args()+array('info'=>$info,'result'=>$entity_info),__METHOD__);
    return $entity_info;
  }

#  /**
#   * This function is called by the Views module.
#   */
#  public function getTableMapping(array $storage_definitions = NULL) {
#
#    $definitions = $storage_definitions ? : \Drupal::getContainer()->get('entity.manager')->getFieldStorageDefinitions($this->entityTypeId);
#    if (!empty($definitions)) {
#      if (\Drupal::moduleHandler()->moduleExists('devel')) {
#        dpm($definitions,__METHOD__);
#      } else drupal_set_message('Non-empty call to '.__METHOD__);
#    }
#    return NULL;
#  }

  /**
   * {@inheritdoc}
   */
//  public function load($id) {
//    //@TODO load WisskiEntity here
//  }

  /**
   * {@inheritdoc}
   */
#  public function loadRevision($revision_id) {
#    return NULL;
#  }

  /**
   * {@inheritdoc}
   */
#  public function deleteRevision($revision_id) {
#  }

  /**
   * {@inheritdoc}
   */
#  public function loadByProperties(array $values = array()) {
#    
#    return array();
#  }

  /**
   * {@inheritdoc}
   */
#  public function delete(array $entities) {
#  }

  /**
   * {@inheritdoc}
   */
#  protected function doDelete($entities) {
#  }

  /**
   * {@inheritdoc}
   */
#  public function save(EntityInterface $entity) {
#
#    return parent::save($entity);
#  }

  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() {
    return 'entity.query.wisski_core';
  }

  /**
   * {@inheritdoc}
   * @TODO must be implemented
   */
  protected function doLoadRevisionFieldItems($revision_id) {
  }

  /**
   * {@inheritdoc}
   * @TODO must be implemented
   */
  protected function doSaveFieldItems(ContentEntityInterface $entity, array $names = []) {
//    dpm(func_get_args(),__METHOD__);
    
//    $adapters = entity_load_multiple('wisski_salz_adapter');
#    dpm(serialize($adapters));
      // ask all adapters
    $values = $this->extractFieldData($entity);
    dpm(func_get_args()+array('values'=>$values),__METHOD__);


#    $bundles = $this->entityManager->getBundleInfo('wisski_individual');
//    dpm($bundles);

    $local_writeable_adapters = array();
    $writeable_adapters = array();

    $adapters = entity_load_multiple('wisski_salz_adapter');
#    dpm(serialize($adapters));
#    drupal_set_message("hallo welt");

    // ask all adapters
    foreach($adapters as $aid => $adapter) {
      // we locate all writeable stores
      // then we locate all local stores in these writeable stores

      if($adapter->getEngine()->isWritable()) {
        if($adapter->getEngine()->isPreferredLocalStore())
          $local_writeable_adapters[$aid] = $adapter;
        else
          $writeable_adapters[$aid] = $adapter;           

      }      
      
    }

    // if there are local writeable adapters, use them
    $adapters_to_use = $local_writeable_adapters;
    
    // if there were no local adapters, use the writeable
    if(empty($adapters_to_use))
      $adapters_to_use = $writeable_adapters;
      
    // if there are no adapters by now we die...
    if(empty($adapters_to_use)) {
      drupal_set_message("There is no storage backend defined.", "error");
      return;
    }      
      
    
#    drupal_set_message("lwa: " . serialize($local_writeable_adapters));
#    drupal_set_message("wa: " . serialize($writeable_adapters));
    
    foreach($adapters_to_use as $aid => $adapter) {
      // if it is a new entity
      if($entity->isNew()) {
        // in this case we have to add the triples for a new entity
        // after that it should be the same for edit and for create
      }
      
      // we locate all writeable stores
      // then we locate all local stores in these writeable stores
      // and write to them
      
#      drupal_set_message("I ask adapter " . serialize($adapter) . " for id " . serialize($entity->id()) . " and get: " . serialize($adapter->hasEntity($id)));
      // if they know that id
      if($adapter->hasEntity($entity->id())) {
        // if so - ask for the bundles for that id
#        $bundles = $adapter->getBundleIdsForEntityId($id);
        #drupal_set_message("Yes, I know " . $id . " and I am " . $aid . ". The bundles are " . serialize($bundles) . ".");
          
          // perhaps we have to check for the field definitions - we ignore this for now.
#          foreach($bundles as $bundleid) {
#            $field_definitions = $this->entityManager->getFieldDefinitions('wisski_individual',$bundleid);
            #drupal_set_message("asking for: " . serialize(array_keys($field_definitions)));
            try {
#              drupal_set_message(" I ask adapter: " . serialize($adapter));
              $adapter_info = $adapter->writeFieldValues($entity->id(), $values);
              /*
              $adapter_info = $adapter->loadFieldValues(array($id),array_keys($field_definitions));

              #drupal_set_message('ive got: ' . serialize($adapter_info));
                            
              foreach($adapter_info as $entity_id => $entity_values) {
                //if we don't know about that entity yet, this adapter's info can be used without a change
                if (!isset($info[$entity_id])) $info[$entity_id] = $entity_values;
                else {
                  //integrate additional values on existing entities
                  foreach($entity_values as $field_name => $value) {
                    if (empty($value)) continue;
                    #drupal_set_message("looking for $entity_id in " . serialize($info));
                    $actual_field_info = $info[$entity_id][$field_name];
                
                    // if there is no field definition throw an error.
                    if(empty($field_definitions[$field_name])) {
                      drupal_set_message("Asked for field definition of field " . $field_name . " on WissKI Individual but there was nothing.", 'error');
                      continue;
                    }
                
                    if ($field_definitions[$field_name] instanceof BaseFieldDefinition) {
                      //this is a base field and cannot have multiple values
                      //@TODO make sure, we load the RIGHT value
                      if (!empty($actual_field_info) && $actual_field_info != $value) drupal_set_message(
                        $this->t('1Multiple values for %field_name in entity %id: %val1, %val2',array(
                          '%field_name'=>$field_name,
                          '%id'=>$entity_id,
                          '%val1'=>is_array($actual_field_info)?implode($actual_field_info,', '):$actual_field_info,
                          '%val2'=>$value,
                        )),'error');
                      else $info[$entity_id][$field_name] = $value;
                      continue;
                    }
                
                    #drupal_set_message("what do we have here: " . serialize($field_definitions[$field_name]));
                
                    //rest is a field
                    $cardinality = 1; #cardinality on text fields seems to be evil?#$field_definitions[$field_name]->getCardinality();
                
                    if ($cardinality === 1) {
                      //this field cannot have multiple values
                      //@TODO make sure, we load the RIGHT value
                      if (!empty($actual_field_info) && $actual_field_info != $value) drupal_set_message(
                        $this->t('Multiple values for field %field_name in entity %id: %val1, %val2',array(
                          '%field_name'=>$field_name,
                          '%id'=>$entity_id,
                          '%val1'=>is_array($actual_field_info)?implode($actual_field_info,', '):$actual_field_info,
                          '%val2'=>$value,
                        )),'error');
                      else $info[$entity_id][$field_name] = $value;
                      continue;
                    }
                    if (!is_array($actual_field_info)) $actual_field_info = array($actual_field_info);
                    if ($cardinality > 0 && count($actual_field_info) >= $cardinality) {
                      drupal_set_message(
                        $this->t('Too many values for field %field_name in entity %id. %card allowed. Tried to add %val2',array(
                          '%field_name'=>$field_name,
                          '%id'=>$entity_id,
                          '%card'=>$cardinality,
                          '%val1'=>$value, )),'error');
                    } else $actual_field_info[] = $value;
                    $info[$entity_id][$field_name] = $actual_field_info;
                  }
                }  
              }*/
            } catch (\Exception $e) {
              drupal_set_message('Could not load entities in adapter '.$adapter->id() . ' because ' . serialize($e));
            }              
        #  }     
          
        } else {
#          drupal_set_message("No, I don't know " . $id . " and I am " . $aid . ".");
        }
      }
    #}


//    foreach($adapters as $aid => $adapter) {
      
//    }
  }

  private function extractFieldData(ContentEntityInterface $entity) {
    
    $out = array();
    //$entity is iterable itself, iterates over field list
    foreach ($entity as $field_name => $field_item_list) {
      $out[$field_name] = array();
      
      foreach($field_item_list as $field_item) {
        //we transfer the main property name to the adapters
        $out[$field_name]['main_property'] = $field_item->mainPropertyName();
        //gathers the ARRAY of field properties for each field list item
        //e.g. $out[$field_name][] = array(value => 'Hans Wurst', 'format' => 'basic_html');
        $out[$field_name][] = $field_item->getValue();
      }
    }
    return $out;
  }

  /**
   * {@inheritdoc}
   * @TODO must be implemented
   */
  protected function doDeleteFieldItems($entities) {
  }

  /**
   * {@inheritdoc}
   * @TODO must be implemented
   */
  protected function doDeleteRevisionFieldItems(ContentEntityInterface $revision) {
  }

  /**
   * {@inheritdoc}
   * @TODO must be implemented
   */
  protected function readFieldItemsToPurge(FieldDefinitionInterface $field_definition, $batch_size) {
    return array();
  }

  /**
   * {@inheritdoc}
   * @TODO must be implemented
   */
  protected function purgeFieldItems(ContentEntityInterface $entity, FieldDefinitionInterface $field_definition) {
  }

  /**
   * {@inheritdoc}
   */
#  protected function doSave($id, EntityInterface $entity) {
#  }

  /**
   * {@inheritdoc}
   * @TODO must be implemented
   */
  protected function has($id, EntityInterface $entity) {
  }

  /**
   * {@inheritdoc}
   * @TODO must be implemented
   */
  public function countFieldData($storage_definition, $as_bool = FALSE) {
    //@TODO return the truth
    return $as_bool ? FALSE : 0;
  }

  /**
   * {@inheritdoc}
   */
#  public function hasData() {
#    return FALSE;
#  }  
}