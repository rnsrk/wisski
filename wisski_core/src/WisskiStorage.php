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
use Drupal\wisski_core\WisskiCacheHelper;

use Drupal\Core\Field\BaseFieldDefinition;

use Drupal\Core\Entity\EntityTypeInterface;
 
use Drupal\Core\Entity\EntityManagerInterface;
 
use Drupal\Core\Cache\CacheBackendInterface;

use Drupal\Component\Utility\NestedArray;

use Drupal\image\Entity\ImageStyle;

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
  //dpm($ids,__METHOD__);
    $entities = array();
    $values = $this->getEntityInfo($ids);
  //dpm($values,'values');    
    foreach ($ids as $id) {
      //@TODO combine this with getEntityInfo
      if (!empty($values[$id])) {
        $entity = $this->create($values[$id]);
        $entity->enforceIsNew(FALSE);
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
    
      //make sure the entity knows its ID at least
      $info[$id]['eid'] = $id;
      
      //see if we got bundle information cached. Useful for entity reference and more      
      $cached_bundle = WisskiCacheHelper::getCallingBundle($id);
      if ($cached_bundle) $info[$id]['bundle'] = $cached_bundle;
      
      // ask all adapters
      foreach($adapters as $aid => $adapter) {
        //drupal_set_message("Does $aid know $id ?");
        // if they know that id
        if($adapter->hasEntity($id)) {
          //drupal_set_message("yes it does");
          // if so - ask for the bundles for that id
          // we assume bundles to be prioritized i.e. the first bundle in the set is the best guess for the view
          $bundle_ids = $adapter->getBundleIdsForEntityId($id);
          #drupal_set_message("Yes, I know " . $id . " and I am " . $aid . ". The bundles are " . serialize($bundle_ids) . ".");
          if (isset($cached_bundle)) {
            if (in_array($cached_bundle,$bundle_ids)) {
              $bundle_ids = array($cached_bundle);
            } else {
              //cached bundle is not handled by this adapter
              continue;
            }
          }
          $bundle_ids = array_slice($bundle_ids,0,1);
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
              
                $main_property = $field_def->getFieldStorageDefinition()->getMainPropertyName();
#dpm(array($adapter->id(), $field_name,$id, $bundleid),'ge1','error');
                
                if ($field_def instanceof BaseFieldDefinition) {
                  //the bundle key will be set via the loop variable $bundleid
                  if ($field_name === 'bundle') continue;
                  //drupal_set_message("Hello i am a base field ".$field_name);
                  //this is a base field and cannot have multiple values
                  //@TODO make sure, we load the RIGHT value
                  $new_field_values = $adapter->loadPropertyValuesForField($field_name,array(),array($id),$bundleid);
#dpm(array($adapter->id(), $field_name,$id, $bundleid, $new_field_values),'gei','error');

if (empty($new_field_values)) continue;
                
                  $new_field_values = $new_field_values[$id][$field_name];
        
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
                
                #drupal_set_message(serialize($new_field_values));
                //dpm(array('field'=>$field_name,'values'=>$new_field_values),$adapter->id());
                if (empty($new_field_values)) continue;
                $info[$id]['bundle'] = $bundleid;
//                dpm($field_def->getType(),$field_name);
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
//                  dpm(array('target_ids'=>$target_ids,'target_bundles'=>$target_bundles,'field_def'=>$field_def),'references');
                  if (!is_array($target_ids)) $target_ids = array(array('target_id'=>$target_ids));
                  foreach ($target_ids as $target_id) {
                    $target_id = $target_id['target_id'];
                    $this->writeToCache($target_id,$target_bundle_id);
                  }
                  
                }
                // NOTE: this is a dirty hack that sets the text format for all long texts
                // with summary
                // TODO: make format storable and provide option for default format in case
                // no format can be retrieved from storage
                $hack_type = $field_def->getType();
                if ($hack_type == 'text_with_summary' || $hack_type == 'text_long') {
                   foreach($new_field_values as &$xid) {
                    foreach($xid as &$xfieldname) {
                      foreach ($xfieldname as &$xindex) {
                         $xindex['format'] = 'full_html';
                       }
                     }
                   }
#                  $value['value'] = $value;
#                  $value['format'] = 'full_html';
                }
                //we integrate a file handling mechanism that must necessarily also handle
                //other file based fields e.g. "image"
                if (in_array('file',$field_def->getFieldStorageDefinition()->getDependencies()['module'])) {
                  
                  $value = $new_field_values[$id][$field_name];
                  // we assume that $value is an image URI which is to be rplaced by a FileID
                  #drupal_set_message('we got an image to handle. Field name:'.$field_name);
                  //dpm($value,'image_info');dpm($entity_info[$id],'current info');
                  #foreach ($entity_view_displays as $evd) {
                  #  $component = $evd->getComponent($field_name);
                  #  dpm($component['type'],$field_name);
                  #}
                  #dpm($value, "value");
                  // $value must be the image uri
                  $file_uri = current($value);
                  
                  // temporary hack - if the file_uri is an array the data might be in the target_id                  
                  if(is_array($file_uri))
                    $file_uri = $file_uri['target_id'];
                  
                  if (empty($file_uri)) continue;
                  $local_uri = '';
                  // by mark: this seems wrong to me... however I am unsure
#                  $new_field_values[$id][$field_name][] = array(
                  $new_field_values[$id][$field_name] = array(
                    'target_id' => $this->getFileId($file_uri,$local_uri),
                    //this is a fallback
                    //@TODO get the alternative text from the stores
                    'alt' => substr($local_uri,strrpos($local_uri,'/') + 1),
                  );
                  
                }
                if (isset($new_field_values[$id][$field_name])) {
                  //try finding the weights and sort the values accordingly
                  $cached_field_values = db_select('wisski_entity_field_properties','f')
                    ->fields('f',array('ident','delta','properties'))
                    ->condition('eid',$id)
                    ->condition('bid',$bundleid)
                    ->condition('fid',$field_name)
                    ->execute()
                    ->fetchAllAssoc('ident');
                  if (!empty($cached_field_values)) {
#                    dpm($cached_field_values,'cached values');
                    $head = array();
                    $tail = array();
                    foreach ($new_field_values[$id][$field_name] as $delta => $nfv) {
                      $ident = isset($nfv['wisskiDisamb']) ? $nfv['wisskiDisamb'] : $nfv[$main_property];
                      if (isset($cached_field_values[$ident])) {
                        //dpm(array($nfv,$cached_field_values[$ident]),'merge with cache');
                        $head[$cached_field_values[$ident]->delta] = $nfv + unserialize($cached_field_values[$ident]->properties);
                      } else $tail[$delta] = $nfv;
                    }
                    $new_field_values[$id][$field_name] = array_merge($head,$tail);
                  }
                  //dpm($new_field_values[$id][$field_name],$aid.' '.$field_name);
                  if (!isset($info[$id]) || !isset($info[$id][$field_name])) $info[$id][$field_name] = $new_field_values[$id][$field_name];
                  else $info[$id][$field_name] = array_merge($info[$id][$field_name],$new_field_values[$id][$field_name]);
                }
              }
              //drupal_set_message("Das richtige: ".serialize($info));
            } catch (\Exception $e) {
              drupal_set_message('Could not load entities in adapter '.$adapter->id() . ' because ' . $e->getMessage()); #because ' . serialize($e));
              //throw $e;
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

  public function getFileId($file_uri,&$local_file_uri='') {
    #drupal_set_message('Image uri: '.$file_uri);
    #dpm($file_uri,__FUNCTION__);
    if (empty($file_uri)) return NULL;
    //first try the cache
    $cid = 'wisski_file_uri2id_'.md5($file_uri);
    if ($cache = \Drupal::cache()->get($cid)) {
      list($file_uri,$local_file_uri) = $cache->data;
      return $file_uri;
    }
    
    // another hack, make sure we have a good local name
    // @TODO do not use md5 since we cannot assume that to be consistent over time
    $local_file_uri = $this->ensureSchemedPublicFileUri($file_uri);
    
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

      //try it with a "translated" uri in the public;// scheme
      $schemed_uri = $this->getSchemedUriFromPublicUri($file_uri);
      $query = \Drupal::entityQuery('file')->condition('uri',$schemed_uri);
      $file_ids = $query->execute();
      if (!empty($file_ids)) {

        $value = current($file_ids);
        //dpm('replaced '.$file_uri.' with schemed existing file '.$value);
        $local_file_uri = $schemed_uri;
      } else {

        $query = \Drupal::entityQuery('file')->condition('uri',$local_file_uri);
        $file_ids = $query->execute();

        if (!empty($file_ids)) {
          //we have a local file with the same filename.
          //lets assume this is the file we were looking for
          $value = current($file_ids);
          //dpm('replaced '.$file_uri.' with local file '.$value);
          //@TODO find out what to do if there is more than one file with that uri
        } else {
          // if we have no managed file with that uri, we try to generate one
          if (file_destination($local_file_uri,FILE_EXISTS_ERROR) === FALSE) {
            $file = File::create([
              'uri' => $local_file_uri,
              'uid' => \Drupal::currentUser()->id(),
              'status' => FILE_STATUS_PERMANENT,
            ]);

            $file->setFileName(drupal_basename($local_file_uri));
            $mime_type = \Drupal::service('file.mime_type.guesser')->guess($local_file_uri);

            $file->setMimeType($mime_type);

            $file->save();
            $value = $file->id();
          } else {
            try {

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
      }
    }
    //dpm($value,'image fid');
    //set cache
    \Drupal::cache()->set($cid,array($value,$local_file_uri));
    return $value;
  }

  /**
   * returns a file URI starting with public://
   * if the input URI already looks like this we return unchanged, a full file path
   + to the file directory will be renamed accordingly
   * every other uri will be renamed by a hash function
   */
  public function ensureSchemedPublicFileUri($file_uri) {
    if (strpos($file_uri,'public:/') === 0) return $file_uri;
    if (strpos($file_uri,\Drupal::service('stream_wrapper.public')->baseUrl()) === 0) {
      return $this->getSchemedUriFromPublicUri($file_uri);
    }

    $original_path = file_default_scheme() . '://wisski_original/';

    file_prepare_directory($original_path, FILE_CREATE_DIRECTORY);

    // this is evil in case it is not .tif or .jpeg but something with . in the name...
#    return file_default_scheme().'://'.md5($file_uri).substr($file_uri,strrpos($file_uri,'.'));    
    // this is also evil, because many modules can't handle public:// :/
    // to make it work we added a directory.
    return file_default_scheme().'://wisski_original/'.md5($file_uri);    
    // external uri doesn't work either
    // this is just a documentation of what I've tried...
#    return \Drupal::service('stream_wrapper.public')->baseUrl() . '/' . md5($file_uri);
#    return \Drupal::service('file_system')->realpath( file_default_scheme().'://'.md5($file_uri) );
#    return \Drupal::service('stream_wrapper.public')->getExternalUrl() . '/' . md5($file_uri);
#    return str_replace('/foko2014/', '', file_url_transform_relative(file_create_url(file_default_scheme().'://'.md5($file_uri))));

  }
  
  public function getPublicUrlFromFileId($file_id) {
    
    if ($file_object = File::load($file_id)) {
      return str_replace(
        'public:/',																						//standard file uri is public://.../filename.jpg
        \Drupal::service('stream_wrapper.pub')->baseUrl(),	//we want DRUPALHOME/sites/default/.../filename.jpg
        $file_object->getFileUri()
      );
    }
    return NULL;
  }
  
  public function getSchemedUriFromPublicUri($file_uri) {
  
    return str_replace(
      \Drupal::service('stream_wrapper.public')->baseUrl(),
      'public:/',
      $file_uri
    );
  }

  /**
   * This function is called by the Views module.
   */
  public function getTableMapping(array $storage_definitions = NULL) {

    $definitions = $storage_definitions ? : \Drupal::getContainer()->get('entity.manager')->getFieldStorageDefinitions($this->entityTypeId);
    if (!empty($definitions)) {
      if (\Drupal::moduleHandler()->moduleExists('devel')) {
        #dpm($definitions,__METHOD__);
      } else drupal_set_message('Non-empty call to '.__METHOD__);
    }
    return NULL;
  }

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
/*
  public function save(EntityInterface $entity) {
#    drupal_set_message("I am saving, yay!" . serialize($entity->id()));
    return parent::save($entity);
  }
*/
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
#    dpm(func_get_args(),__METHOD__);
#    return;
    #dpm($entity->id(), "entitydata1");
    //gather values with property caching
    // set second param of getValues to FALSE: we must not write
    // field values to cache now as there may be no eid yet (on create)
    list($values,$original_values) = $entity->getValues($this,FALSE);
    $bundle_id = $values['bundle'][0]['target_id'];
    if (empty($bundle_id)) $bundle_id = $entity->bundle();
    #dpm($entity->id(), "entitydata2");
//    dpm(func_get_args()+array('values'=>$values,'bundle'=>$bundle_id),__METHOD__);
    //echo implode(', ',array_keys((array) $entity));
    $local_adapters = array();
    $writeable_adapters = array();

    $adapters = entity_load_multiple('wisski_salz_adapter');
#    dpm(serialize($adapters));
#    drupal_set_message("hallo welt");

    // ask all adapters and find the writable ones
    foreach($adapters as $aid => $adapter) {
      // we locate all writeable stores
      // then we locate all local stores in these writeable stores

      if($adapter->getEngine()->isWritable())
        $writeable_adapters[$aid] = $adapter;
             
      if($adapter->getEngine()->isPreferredLocalStore())
        $local_adapters[$aid] = $adapter;
      
    }

    // if there are no adapters by now we die...
    if(empty($writeable_adapters)) {
      drupal_set_message("There is no writable storage backend defined.", "error");
      return;
    }
    
    if($diff = array_diff_key($local_adapters,$writeable_adapters)) {
      if (count($diff) === 1)
        drupal_set_message('The preferred local store '.key($diff).' is not writeable','warning');
      else drupal_set_message('The preferred local stores '.implode(', ',array_keys($diff)).' are not writeable','warning');
    }
    
    //we load all pathbuilders, check if they know the fields and have writeable adapters
    $pathbuilders = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::loadMultiple();
    #dpm($entity->id(), "entitydata");
    $entity_id = $entity->id();
    // we track if this is a newly created entity, if yes, we want to write it to ALL writeable adapters
    $create_new = $entity->isNew() && empty($entity_id);
    
    if (empty($entity_id)) {    
      //dpm(array($writeable_adapters,$pathbuilders),'Empty ID');
      foreach($pathbuilders as $pb_id => $pb) {
      
        //get the adapter
        $aid = $pb->getAdapterId();

        //check, if it's writeable, if not we can stop here
        if (isset($writeable_adapters[$aid])) $adapter = $writeable_adapters[$aid];
        else continue;

        $entity_id = $adapter->createEntity($entity);
      }
      //dpm($entity_id,$aid);
    }
  
    if (empty($entity_id)) {
      drupal_set_message('No local adapter could create the entity','error');
      return;
    }
    
    dpm($original_values,'old values');
    dpm($values,'new values');
    $real_new_values = array_diff_key($values,$original_values);
    //dpm($real_new_values,'Really new values');
    
    if (!$create_new) $create_new = !empty($real_new_values);
    unset($real_new_values);
#    drupal_set_message("lwa: " . serialize($local_writeable_adapters));
#    drupal_set_message("wa: " . serialize($writeable_adapters));
    //dpm($create_new ? 'Gotta create' : 'don\'t create');
//    dpm(count($local_adapters),'how many');
    foreach($pathbuilders as $pb_id => $pb) {
      
      //get the adapter
      $aid = $pb->getAdapterId();

      //dpm($writeable_adapters,'Check '.$aid.' from '.$pb_id);
      //check, if it's writeable, if not we can stop here
      if (isset($writeable_adapters[$aid])) $adapter = $writeable_adapters[$aid];
      else continue;

      $success = FALSE;
#      drupal_set_message("I ask adapter " . serialize($adapter) . " for id " . serialize($entity->id()) . " and get: " . serialize($adapter->hasEntity($id)));
      // if they know that id
      if($create_new || $adapter->hasEntity($entity_id)) {
        
        // perhaps we have to check for the field definitions - we ignore this for now.
        //   $field_definitions = $this->entityManager->getFieldDefinitions('wisski_individual',$bundle_idid);
        try {
          //dpm('Try writing to '.$aid);
          //drupal_set_message(" I ask adapter: " . serialize($adapter));
          //we force the writable adapter to write values for newly created entities even if unknown to the adapter by now
          //@TODO return correct success code
          $adapter_info = $adapter->writeFieldValues($entity_id, $values, $pb, $bundle_id, $original_values,$create_new);
#          dpm('Success',$aid);
          $success = TRUE;
        } catch (\Exception $e) {
          drupal_set_message('Could not write entity into adapter '.$adapter->id() . ' because ' . serialize($e->getMessage()));
          throw $e;
        }
      } else {
        //drupal_set_message("No, I don't know " . $id . " and I am " . $aid . ".");
      }
      
      if ($success) {
        $entity->set('eid',$entity_id);
        $entity->enforceIsNew(FALSE);
        //we have successfully written to this adapter

        // write values and weights to cache table
        // we reuse the getValues function and set the second param to true
        // as we are not interested in the values we discard them
        $entity->getValues($this, TRUE);
        // TODO: eventually there should be a seperate function for the field caching


        
        $bundle = \Drupal\wisski_core\Entity\WisskiBundle::load($bundle_id);
        //dpm($bundle,'Wrote '.$entity_id.' to '.$bundle_id);
        if ($bundle) $bundle->flushTitleCache($entity_id);
      }
    }
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
