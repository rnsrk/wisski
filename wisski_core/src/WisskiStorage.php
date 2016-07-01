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

use Drupal\image\Entity\ImageStyle;

use Drupal\wisski_core\Entity\WisskiEntity;
use Drupal\wisski_core\Query\WisskiQueryInterface;
//use Drupal\wisski_core\WisskiInvalidArgumentException;
use Drupal\wisski_core\WisskiCacheHelper;

use Drupal\Core\Field\BaseFieldDefinition;

use Drupal\Core\Entity\EntityTypeInterface;
 
use Drupal\Core\Entity\EntityManagerInterface;
 
use Drupal\Core\Cache\CacheBackendInterface;

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
      if (!empty($values[$id])) {
        $entity = $this->create($values[$id]);
        $entities[$id] = $entity;
      }
    }
    //dpm(array('in'=>$ids,'out'=>$entities),__METHOD__);
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
#    dpm($all_field_definitions,'field_definitions before');
    // for every id
    foreach($ids as $id) {
      //see if we got bundle information cached. Useful for entity reference and more
      $cached_bundle = WisskiCacheHelper::getCallingBundle($id);
      // ask all adapters
      foreach($adapters as $aid => $adapter) {
        // if they know that id
        if($adapter->hasEntity($id)) {
          // if so - ask for the bundles for that id
          $bundle_ids = $adapter->getBundleIdsForEntityId($id);
          //drupal_set_message("Yes, I know " . $id . " and I am " . $aid . ". The bundles are " . serialize($bundle_ids) . ".");
          if (isset($cached_bundle)) {
            if (in_array($cached_bundle,$bundle_ids)) {
              $bundle_ids = array($cached_bundle);
            } else {
              //cached bundle is not handled by this adapter
              continue;
            }
          }
          foreach($bundle_ids as $bundleid) {
#            dpm($field_definitions,'Field defs for '.$bundleid);
            $field_definitions = $this->entityManager->getFieldDefinitions('wisski_individual',$bundleid);
#            $view_ids = \Drupal::entityQuery('entity_view_display')
#              ->condition('id', 'wisski_individual.' . $bundleid . '.', 'STARTS_WITH')
#              ->execute();
#            $entity_view_displays = \Drupal::entityManager()->getStorage('entity_view_display')->loadMultiple($view_ids);
#            drupal_set_message("I am asking for " . $id . " and I am " . $aid. " and I think my bundle is: " . serialize($bundleid));
            #drupal_set_message("asking for: " . serialize(array_keys($field_definitions)));
            try {
              foreach ($field_definitions as $field_name => $field_def) {
                if ($field_def instanceof BaseFieldDefinition) {
                  //the bundle key will be set via the loop variable $bundleid
                  if ($field_name === 'bundle') continue;
                  if ($field_name === 'preview_image') {
                    $new_field_values[] = $this->getPreviewImage($id,$bundleid,$adapter);
                  } else {
                    //drupal_set_message("Hello i am a base field ".$field_name);
                    //this is a base field and cannot have multiple values
                    //@TODO make sure, we load the RIGHT value
                    $new_field_values = $adapter->loadPropertyValuesForField($field_name,array(),array($id),$bundleid);
                  
                    $new_field_values = $new_field_values[$id][$field_name];
                  }
                  if (isset($info[$id][$field_name])) {
                    $old_field_value = $info[$id][$field_name];
                    if (in_array($old_field_value,$new_field_values) && count($new_field_values) > 1) {
                      //@TODO drupal_set_message('Multiple values for base field '.$field_name,'error');
                      //FALLLBACK: do nothing, old field value stays the same
                      //WATCH OUT: if you change this remember to handle preview_image case correctly
                    } elseif (count($new_field_values) === 1) {
                      $info[$id][$field_name] = $new_field_values[0];
                    } else {
                      //@TODO drupal_set_message('Multiple values for base field '.$field_name,'error');
                      //WATCH OUT: if you change this remember to handle preview_image case correctly
                    }
                  } elseif (!empty($new_field_values)) {
                    $info[$id][$field_name] = current($new_field_values);
                  }
                  if (!isset($info[$id]['bundle'])) $info[$id]['bundle'] = $bundleid;
                  continue;                 
                }
                //here we have a "normal field" so we can assume an array of field values is OK
                $new_field_values = $adapter->loadPropertyValuesForField($field_name,array(),array($id),$bundleid);
                //drupal_set_message(serialize($new_field_values));
                if (empty($new_field_values)) continue;
                $info[$id]['bundle'] = $bundleid;
                //dpm($field_def->getType(),$field_name);
                if ($field_def->getType() === 'entity_reference') {
                  $field_settings = $field_def->getSettings();
                  $target_bundles = $field_settings['handler_settings']['target_bundles'];
                  if (count($target_bundles) === 1) {
                    $target_bundle_id = current($target_bundles);
                  } else {
                    drupal_set_message($this->t('Multiple target bundles for field %field'),array('%field' => $field_name));
                    //@TODO create a MASTER BUNDLE and choose that one here
                    $target_bundle_id = current($target_bundles);
                  }
                  $target_ids = $new_field_values[$id][$field_name];
                  if (!is_array($target_ids)) $target_ids = array(array('target_id'=>$target_ids));
                  foreach ($target_ids as $target_id) {
                    $target_id = $target_id['target_id'];
                    $this->writeToCache($target_id,$target_bundle_id);
                  }
                  
                }
                if ($field_def->getType() === 'image') {
                  $value = $new_field_values[$id][$field_name];
                  // we assume that $value is an image URI which is to be rplaced by a FileID
                  #drupal_set_message('we got an image to handle. Field name:'.$field_name);
                  //dpm($value,'image_info');dpm($entity_info[$id],'current info');
                  #foreach ($entity_view_displays as $evd) {
                  #  $component = $evd->getComponent($field_name);
                  #  dpm($component['type'],$field_name);
                  #}
                  
                  // $value must be the image uri
                  $file_uri = current($value);
                  
                  // temporary hack - if the file_uri is an array the data might be in the target_id                  
                  if(is_array($file_uri))
                    $file_uri = $file_uri['target_id'];
                  
                  $new_field_values[$id][$field_name] = $this->getFileId($file_uri);
                }
                if (isset($new_field_values[$id][$field_name])) {
                  if (!isset($info[$id]) || !isset($info[$id][$field_name])) $info[$id][$field_name] = $new_field_values[$id][$field_name];
                  else $info[$id][$field_name] = array_merge($info[$id][$field_name],$new_field_values[$id][$field_name]);
                }
              }
              //drupal_set_message("Das richtige: ".serialize($info));
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
#    dpm(func_get_args()+array('info'=>$info,'result'=>$entity_info),__METHOD__);
    return $entity_info;
  }

  protected function getFileId($file_uri,&$local_file_uri='') {

    // another hack, make sure we have a good local name
    // @TODO do not use md5 since we cannot assume that to be consistent over time
    $local_file_uri = file_default_scheme().'://'.md5($file_uri).substr($file_uri,strrpos($file_uri,'.'));
    // we now check for an existing 'file managed' with that uri
    $query = \Drupal::entityQuery('file')->condition('uri',$file_uri);
    $file_ids = $query->execute();
    if (!empty($file_ids)) {
      // if there is one, we must set the field value to the image's FID
      $value = current($file_ids);
      //dpm('replaced '.$file_uri.' with existing file '.$value);
      //@TODO find out what to do if there is more than one file with that uri
      $local_file_uri = $file_uri;
    } else {
      $query = \Drupal::entityQuery('file')->condition('uri',$local_file_uri);
      $file_ids = $query->execute();
      if (!empty($file_ids)) {
        //we have a local file with the same filename.
        //lets assume this is the file we were looking for
        $value = current($file_ids);
        //dpm('replaced '.$file_uri.' with existing local file '.$value);
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
          //dpm(array('data'=>$data,'uri'=>$file_uri,'local'=>$local_file_uri),'Trying to save image');
          $file = file_save_data($data, $local_file_uri);
          if ($file) {
            $value = $file->id();
            //dpm('replaced '.$file_uri.' with new file '.$value);
          } else {
            drupal_set_message('Error saving file','error');
            //dpm($data,$file_uri);
          }
        } catch (EntityStorageException $e) {
          drupal_set_message($this->t('Could not create file with uri %uri. Exception Message: %message',array('%uri'=>$file_uri,'%message'=>$e->getMessage())),'error');
        }
      }
    }
    //dpm($value,'image fid');
    return $value;
  }

  private function getPreviewImage($entity_id,$bundle_id,$adapter) {
    
    if ($preview = WisskiCacheHelper::getPreviewImage($entity_id)) {
      drupal_set_message('Preview image from cache');
      return $preview;
    }
    else {
      $images = $adapter->getEngine()->getImagesForEntityId($entity_id,$bundle_id);
      $input_uri = current($images);
      $output_uri = '';
      $input_id = $this->getFileId($input_uri,$output_uri);
      $image_style = $this->getPreviewStyle();
      $preview_uri = $image_style->buildUri($output_uri);
      //dpm(array('output_uri'=>$output_uri,'preview_uri'=>$preview_uri));
      if (!preg_match('/^.+?\.\w+$/',$output_uri)) {
        drupal_set_message('Invalid image uri '.$output_uri);
        if (!preg_match('/^.+?\.\w+$/',$input_uri)) {
          drupal_set_message('Invalid image uri '.$input_uri);
        
          return 1;
        }
        return $input_uri;
      }
      if ($image_style->createDerivative($output_uri,$preview_uri)) {
        drupal_set_message('Style did it');
        $preview_id = $this->getFileId($preview_uri);
        WisskiCacheHelper::putPreviewImage($entity_id,$preview_id);
        return $preview_id;
      } else return $input_id;
    }
  }
  
  private $image_style;
  
  private function getPreviewStyle() {
    
    if (isset($this->image_style)) return $this->image_style;
    $image_style_name = 'wisski_preview';

    if(! $image_style = ImageStyle::load($image_style_name)) {
      $values = array('name'=>$image_style_name,'label'=>'Wisski Preview Image Style');
      $image_style = ImageStyle::create($values);
      $settings = \Drupal::config('wisski_core.settings');
      $w = $settings->get('wisski_preview_image_max_width_pixel');
      $h = $settings->get('wisski_preview_image_max_height_pixel');
      $config = array(
        'id' => 'image_scale',
        'data' => array(
          'width' => isset($w) ? $w : 100,
          'height' => isset($h) ? $h : 100,
          'upscale' => FALSE,
        ),
      );
      $image_style->addImageEffect($config);
      $image_style->save();
    }
    $this->image_style = $image_style;
    return $image_style;
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
    
    list($values,$original_values) = $this->extractFieldData($entity);
    $bundle_id = $values['bundle'][0]['target_id'];
//    dpm(func_get_args()+array('values'=>$values,'bundle'=>$bundle_id),__METHOD__);
    //echo implode(', ',array_keys((array) $entity));
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
      $success = FALSE;
#      drupal_set_message("I ask adapter " . serialize($adapter) . " for id " . serialize($entity->id()) . " and get: " . serialize($adapter->hasEntity($id)));
      // if they know that id
      if($adapter->hasEntity($entity->id())) {
        
        // perhaps we have to check for the field definitions - we ignore this for now.
        //   $field_definitions = $this->entityManager->getFieldDefinitions('wisski_individual',$bundle_idid);
        try {
          //drupal_set_message(" I ask adapter: " . serialize($adapter));
          //@TODO return correct success code
          $adapter_info = $adapter->writeFieldValues($entity->id(), $values, $bundle_id, $original_values);
          $success = TRUE;
        } catch (\Exception $e) {
          drupal_set_message('Could not load entities in adapter '.$adapter->id() . ' because ' . serialize($e));
        }
      } else {
        //drupal_set_message("No, I don't know " . $id . " and I am " . $aid . ".");
      }
      
      if ($success) {
        //we have successfully written to this adapter
        \Drupal\wisski_core\Entity\WisskiBundle::load($bundle_id)->flushTitleCache($entity->id());
      }
    }
  }

  private function extractFieldData(ContentEntityInterface $entity) {
    
    $out = array();
    $old_values = $entity->getOriginalValues();
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
    //dpm($entity,__METHOD__);
    return array($out,$old_values);
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
    
    if ($entity->isNew()) return FALSE;
    $adapters = entity_load_multiple('wisski_salz_adapter');
    // ask all adapters
    foreach($adapters as $aid => $adapter) {
      if($adapter->getEngine()->hasEntity($id)) {
        return TRUE;
      }            
    }
    return FALSE;
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
  public function hasData() {
    //@TODO this is only for development purposes. So we can uninstall the module without having to delete data
    return FALSE;
  }  
  
  public function writeToCache($entity_id,$bundle_id) {
  
    WisskiCacheHelper::putCallingBundle($entity_id,$bundle_id);
  }
}