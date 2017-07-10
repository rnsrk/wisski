<?php

namespace Drupal\wisski_core;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\ContentEntityStorageBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;

use Drupal\file\Entity\File;
use Drupal\file\FileStorage;
use Drupal\image\Entity\ImageStyle;

use Drupal\wisski_core\Entity\WisskiEntity;
use Drupal\wisski_core\Query\WisskiQueryInterface;
use Drupal\wisski_core\WisskiCacheHelper;
use Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity;
use Drupal\wisski_salz\AdapterHelper;
use Drupal\wisski_salz\Entity\Adapter;



/**
 * Test Storage that returns a Singleton Entity, so we can see what the FieldItemInterface does
 */
class WisskiStorage extends ContentEntityStorageBase implements WisskiStorageInterface {

  /*
  public function create(array $values = array()) {
    $user = \Drupal::currentUser();
    
    
    dpm($values, "before");
    if(!isset($values['uid']))
      $values['uid'] = $user->id();
      
    dpm($values, "values");
    return parent::create($values);
  }
  */
  

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

    // this loads everything from the triplestore
    $values = $this->getEntityInfo($ids);

#  dpm($values,'values');    

    // add the values from the cache
    foreach ($ids as $id) {
      //@TODO combine this with getEntityInfo
      if (!empty($values[$id])) {
#ddl($values, 'values');
#if (!isset($values[$id]['bundle'])) ddl($values[$id], "adbadbad$id bad");

        // load the cache
        $cached_field_values = db_select('wisski_entity_field_properties','f')
          ->fields('f',array('fid', 'ident','delta','properties'))
          ->condition('eid',$id)
          ->condition('bid',$values[$id]['bundle'])
#          ->condition('fid',$field_name)
          ->execute()
          ->fetchAllAssoc('fid');
          
#        dpm($cached_field_values, "argh");

        foreach($cached_field_values as $field_id => $cached_field_value) {
#          dpm($cached_field_value, "sdasdf");

          // empty here might make problems
          if( isset($values[$id][$field_id]) )
            continue;

          $cached_value = unserialize($cached_field_value->properties);
          
          if(empty($cached_value))
            continue;

          // now it should be save to set this value
          $values[$id][$field_id] = $cached_value;
        }
        
#        dpm($values, "values after");
        
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
#    drupal_set_message(serialize($this));

    // get the main entity id
    // if this is NULL then we have a main-form
    // if it is not NULL we have a sub-form    
    $mainentityid = key($this->entities);

#    drupal_set_message("key is: " . serialize($mainentityid));

    $entity_info = &$this->entity_info;
    if ($cached) {
      $ids = array_diff_key($ids,$entity_info);
      if (empty($ids)) return $entity_info;
      // if there remain entities that were not in cache, $ids now only 
      // contains their ids and we load these in the remaining procedure.
    }
    
    // prepare config variables if we only want to use main bundles.    
    $topBundles = array();
    $set = \Drupal::configFactory()->getEditable('wisski_core.settings');
    $only_use_topbundles = $set->get('wisski_use_only_main_bundles');

    if($only_use_topbundles) 
      $topBundles = \Drupal\wisski_core\WisskiHelper::getTopBundleIds();

    $adapters = entity_load_multiple('wisski_salz_adapter');
    $info = array();
    // for every id
    foreach($ids as $id) {
    
      //make sure the entity knows its ID at least
      $info[$id]['eid'] = $id;
      
      //see if we got bundle information cached. Useful for entity reference and more      
      $overall_bundle_ids = array();
      $cached_bundle = WisskiCacheHelper::getCallingBundle($id);
      
      // only use that if it is a top bundle when the checkbox was set. Always use it otherwise.
      if ($cached_bundle) {
        if($only_use_topbundles && empty($mainentityid) && !in_array($cached_bundle, $topBundles))
          $cached_bundle = NULL;
        else
          $info[$id]['bundle'] = $cached_bundle;
      }
            
      // ask all adapters
      foreach($adapters as $aid => $adapter) {
        // if they know that id
#        drupal_set_message(serialize($adapter->hasEntity($id)) . " said adapter " . serialize($adapter));
        if($adapter->hasEntity($id)) {
#          drupal_set_message(serialize("argh"));
          // if so - ask for the bundles for that id
          // we assume bundles to be prioritized i.e. the first bundle in the set is the best guess for the view
          $bundle_ids = $adapter->getBundleIdsForEntityId($id);
          $overall_bundle_ids = array_merge($overall_bundle_ids, $bundle_ids);
#          drupal_set_message(serialize($bundle_ids) . " and " . serialize($cached_bundle));
          if (isset($cached_bundle)) {
            if (in_array($cached_bundle,$bundle_ids)) {
              $bundle_ids = array($cached_bundle);
            } else {
              //cached bundle is not handled by this adapter
              continue;
            }
          }

          $bundle_ids = array_slice($bundle_ids,0,1);
#          drupal_set_message(serialize($bundle_ids) . " and " . serialize($cached_bundle));
          foreach($bundle_ids as $bundleid) {
            // be more robust.
            if(empty($bundleid)) {
              drupal_set_message("Beware, there is somewhere an empty bundle id specified in your pathbuilder!", "warning");
              continue;
            }
              
            $field_definitions = $this->entityManager->getFieldDefinitions('wisski_individual',$bundleid);
            
            wpm($field_definitions, 'gei-fd');
#            $view_ids = \Drupal::entityQuery('entity_view_display')
#              ->condition('id', 'wisski_individual.' . $bundleid . '.', 'STARTS_WITH')
#              ->execute();
#            $entity_view_displays = \Drupal::entityManager()->getStorage('entity_view_display')->loadMultiple($view_ids);
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

                if (empty($new_field_values)) continue;
                $info[$id]['bundle'] = $bundleid;
                if ($field_def->getType() === 'entity_reference') {
                  $field_settings = $field_def->getSettings();
#if (!isset($field_settings['handler_settings']['target_bundles'])) dpm($field_def);
                  $target_bundles = $field_settings['handler_settings']['target_bundles'];
                  if (count($target_bundles) === 1) {
                    $target_bundle_id = current($target_bundles);
                  } else {
                    drupal_set_message($this->t('Multiple target bundles for field %field',array('%field' => $field_name)));
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
                // NOTE: this is a dirty hack that sets the text format for all long texts
                // with summary
                // TODO: make format storable and provide option for default format in case
                // no format can be retrieved from storage
                $hack_type = $field_def->getType();
                if ($hack_type == 'text_with_summary' || $hack_type == 'text_long') {
                  foreach($new_field_values as &$xid) {
                    foreach($xid as &$xfieldname) {
                      foreach ($xfieldname as &$xindex) {
#                        $xindex['format'] = 'full_html';
                      }
                    }
                  }
#                 $value['value'] = $value;
#                 $value['format'] = 'full_html';
                }

                // we integrate a file handling mechanism that must necessarily
                // also handle other file based fields e.g. "image"
                //
                // the is_file test is a hack. there doesn't seem to be an easy
                // way to determine if a field is file-based. this test tests
                // whether the field depends on the file module. NOTE: there
                // may be other reasons why a field depends on file than
                // handling files
                $is_file = in_array('file',$field_def->getFieldStorageDefinition()->getDependencies()['module']);
                $has_values = !empty($new_field_values[$id][$field_name]);
                if ($is_file && $has_values) {
                  
                  foreach ($new_field_values[$id][$field_name] as $key => &$properties_array) {
                    // we assume that $value is an image URI which is to be
                    // replaced by a File entity ID
                    // we use the special property original_target_id as the
                    // loadPropertyValuesForField()/pathToReturnValues()
                    // replaces the URI with the corresp. file entity id.
                    if (!isset($properties_array['original_target_id'])) continue;
                    $file_uri = $properties_array['original_target_id'];
                    
                    $local_uri = '';
                    $properties_array = array(
                      'target_id' => $this->getFileId($file_uri,$local_uri, $id),
                      //this is a fallback
                      //@TODO get the alternative text from the stores
                      'alt' => substr($local_uri,strrpos($local_uri,'/') + 1),
                    );
                  }
                }

                //try finding the weights and sort the values accordingly
                if (isset($new_field_values[$id][$field_name])) {
                  $cached_field_values = db_select('wisski_entity_field_properties','f')
                    ->fields('f',array('ident','delta','properties'))
                    ->condition('eid',$id)
                    ->condition('bid',$bundleid)
                    ->condition('fid',$field_name)
                    ->execute()
                    ->fetchAllAssoc('delta');
                    // this is evil because same values will be killed then... we go for weight instead.
#                    ->fetchAllAssoc('ident');
                  if (!empty($cached_field_values)) {
                    $head = array();
                    $tail = array();

                    // there is no delta as a key in this array :( 
                    foreach ($new_field_values[$id][$field_name] as $nfv) {
                      // this would be smarter, however currently the storage can't
                      // store the disamb so this is pointless...
                      //$ident = isset($nfv['wisskiDisamb']) ? $nfv['wisskiDisamb'] : $nfv[$main_property];
                      
                      // this was a good approach, however it is not correct when you have
                      // the same value several times
                      //$ident = $nfv[$main_property];
                      //if (isset($cached_field_values[$ident])) {
                      
                      // store the found item
                      $found_cached_field_value = NULL;
                      
                      // iterate through the cached values and delete
                      // anything we find from the cache to correct the weight
                      foreach($cached_field_values as $key => $cached_field_value) {
                        if($cached_field_value->ident === $nfv[$main_property]) {
                          unset($cached_field_values[$key]);
                          $found_cached_field_value = $cached_field_value;
                          break;
                        }
                      }
                      
                      // if we found something go for it...
                      if (isset($found_cached_field_value)) {
                        $head[$found_cached_field_value->delta] = $nfv + unserialize($found_cached_field_value->properties);
                      } else $tail[] = $nfv;
                    }
                    
                    // do a ksort, because array_merge will resort anyway!
                    ksort($head);
                    ksort($tail);                    

                    $new_field_values[$id][$field_name] = array_merge($head,$tail);

                  }
                  if (!isset($info[$id]) || !isset($info[$id][$field_name])) $info[$id][$field_name] = $new_field_values[$id][$field_name];
                  else $info[$id][$field_name] = array_merge($info[$id][$field_name],$new_field_values[$id][$field_name]);
                }
              }
            } catch (\Exception $e) {
              drupal_set_message('Could not load entities in adapter '.$adapter->id() . ' because ' . $e->getMessage());
              //throw $e;
            }              
          }     
          
        } else {
#          drupal_set_message("No, I don't know " . $id . " and I am " . $aid . ".");
        }
          
      } // end foreach adapter
      
      if (!isset($info[$id]['bundle'])) {
        // we got no bundle information
        // this may especially be the case if we have an instance with no fields filled out.
        // if some adapters found some bundle info, we make a best guess
        if (!empty($overall_bundle_ids)) {
          $top_bundle_ids = \Drupal\wisski_core\WisskiHelper::getTopBundleIds();
          $best_guess = array_intersect($overall_bundle_ids, $top_bundle_ids);
          if (empty($best_guess)) {
            $best_guess = $overall_bundle_ids;
          }
          // if there are multiples, tkae the first one
          // TODO: rank remaining bundles
          $info[$id]['bundle'] = current($best_guess);
        }
      }
      
    }

    $entity_info = WisskiHelper::array_merge_nonempty($entity_info,$info);

    wpm($entity_info, 'gei');
    return $entity_info;
  }

  public function getFileId($file_uri,&$local_file_uri='', $entity_id = 0) {
    #drupal_set_message('Image uri: '.$file_uri);
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

          $file = NULL;
          // if we have no managed file with that uri, we try to generate one.
          // in the if test we test whether there exists on the server a file 
          // called $local_file_uri: file_destination() with 2nd param returns
          // FALSE if there is such a file!
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
              
              // we have to encode the image url, 
              // see http://php.net/manual/en/function.file-get-contents.php
              // NOTE: although the docs say we must use urlencode(), the docs
              // for urlencode() and rawurlencode() specify that rawurlencode
              // must be used for url path part.
              // TODO: this encode hack only works properly if the file name 
              // is the last part of the URL and if only the filename contains
              // disallowed chars. 
              $tmp = explode("/", $file_uri);
              $tmp[count($tmp) - 1] = rawurlencode($tmp[count($tmp) - 1]);
              $file_uri = join('/', $tmp);

              $data = @file_get_contents($file_uri);
              if (empty($data)) { 
                drupal_set_message($this->t('Could not fetch file with uri %uri.',array('%uri'=>$file_uri,)),'error');
              }

              //dpm(array('data'=>$data,'uri'=>$file_uri,'local'=>$local_file_uri),'Trying to save image');
              $file = file_save_data($data, $local_file_uri);

              if ($file) {
                $value = $file->id();
                //dpm('replaced '.$file_uri.' with new file '.$value);
              } else {
                drupal_set_message('Error saving file','error');
                //dpm($data,$file_uri);
              }
            }
            catch (EntityStorageException $e) {
              drupal_set_message($this->t('Could not create file with uri %uri. Exception Message: %message',array('%uri'=>$file_uri,'%message'=>$e->getMessage())),'error');
            }
          }

          if (!empty($file)) {
            // we have to register the usage of this file entity otherwise 
            // Drupal will complain that it can't refer to this file when 
            // saving the WissKI individual
            // (it is unclear to me why Drupal bothers about that...)
            \Drupal::service('file.usage')->add($file, 'wisski_core', 'wisski_individual', $entity_id);
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
    return file_default_scheme().'://wisski_original/'.md5($file_uri).substr($file_uri,strrpos($file_uri,'.'));    
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
        \Drupal::service('stream_wrapper.public')->baseUrl(),	//we want DRUPALHOME/sites/default/.../filename.jpg
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
#    \Drupal::logger('WissKIsaveProcess')->debug(__METHOD__ . " with values: " . serialize(func_get_args()));
#    \Drupal::logger('WissKIsaveProcess')->debug(serialize($entity->uid->getValue())); 
#    dpm(func_get_args(),__METHOD__);
#    return;

#    dpm($entity->uid->getValue(), 'uid');

    $uid = $entity->uid;
    // override the user setting
    if(isset($uid) && empty($uid->getValue()['target_id']) ) {
      $user = \Drupal::currentUser();
#    dpm($values, "before");
      $uid->setValue(array('target_id' => (int)$user->id()));
    }
    
#    dpm($entity->uid->getValue(), 'uid');


    // gather values with property caching
    // set second param of getValues to FALSE: we must not write
    // field values to cache now as there may be no eid yet (on create)
$ts1 = microtime(true);    

    list($values,$original_values) = $entity->getValues($this,FALSE);
    $bundle_id = $values['bundle'][0]['target_id'];
    if (empty($bundle_id)) $bundle_id = $entity->bundle();
    // TODO: What shall we do if bundle_id is still empty. Can this happen?

    // we only load the pathbuilders and adapters that can handle the bundle.
    // Loading all of them would take too long and most of them don't handle
    // the bundle, assumingly.
    // We have this information cached.
    // Then we filter the writable ones
    $pbs_info = \Drupal::service('wisski_pathbuilder.manager')->getPbsUsingBundle($bundle_id);
$tsa = array('pbs' => array_keys($pbs_info));
    $adapters_ids = array();
    $pb_ids = array();
    foreach($pbs_info as $pbid => $info) {
      if ($info['writable']) {
        $aid = $info['adapter_id'];
        $pb_ids[$pbid] = $pbid;
        $adapter_ids[$aid] = $aid;
      }
      elseif ($info['preferred_local']) {
        // we warn here as the peferred local store should be writable if an 
        // entity is to be saved. Eg. the sameAs mechanism relies on that.
        drupal_set_message(t('The preferred local store %a is not writable.', array('%a' => $adapter->label())),'warning');
      } 
    }
    // if there are no adapters by now we die...
    if(empty($adapter_ids)) {
      drupal_set_message("There is no writable storage backend defined.", "error");
      return;
    }
    
    $pathbuilders = WisskiPathbuilderEntity::loadMultiple($pb_ids);
    $adapters = Adapter::loadMultiple($adapter_ids);

    
    $entity_id = $entity->id();

    // we track if this is a newly created entity, if yes, we want to write it to ALL writable adapters
    $create_new = $entity->isNew() && empty($entity_id);
    
$ts = microtime(true);
    
    // if there is no entity id yet, we register the new entity
    // at the adapters
    if (empty($entity_id)) {    
      foreach($adapters as $aid => $adapter) {
        $entity_id = $adapter->createEntity($entity);
        $create_new = FALSE;
      }
    }
    if (empty($entity_id)) {
      drupal_set_message('No local adapter could create the entity','error');
      return;
    }
    
$tsa['create'] = microtime(true) - $ts;
$ts = microtime(true);
    
    foreach($pathbuilders as $pb_id => $pb) {
      
      //get the adapter
      $aid = $pb->getAdapterId();
      $adapter = $adapters[$aid];

      $success = FALSE;
#      drupal_set_message("I ask adapter " . serialize($adapter) . " for id " . serialize($entity->id()) . " and get: " . serialize($adapter->hasEntity($id)));
      // if they know that id
      if($create_new || $adapter->hasEntity($entity_id)) {
        
        // perhaps we have to check for the field definitions - we ignore this for now.
        //   $field_definitions = $this->entityManager->getFieldDefinitions('wisski_individual',$bundle_idid);
        try {
          //we force the writable adapter to write values for newly created entities even if unknown to the adapter by now
          //@TODO return correct success code
          $adapter_info = $adapter->writeFieldValues($entity_id, $values, $pb, $bundle_id, $original_values,$create_new);

          // By Mark: perhaps it would be smarter to give the writeFieldValues the entity
          // object because it could make changes to it
          // e.g. which uris were used for reference (disamb) etc.
          // as long as it is like now you can't promote uris back to the storage.

          $success = TRUE;
        } catch (\Exception $e) {
          drupal_set_message('Could not write entity into adapter '.$adapter->id() . ' because ' . serialize($e->getMessage()));
          throw $e;
        }
      } else {
        //drupal_set_message("No, I don't know " . $id . " and I am " . $aid . ".");
      }
      
      if ($success) {
        
        // TODO: why are the next two necessary? what do they do?
        $entity->set('eid',$entity_id);
        $entity->enforceIsNew(FALSE);
        //we have successfully written to this adapter

        // write values and weights to cache table
        // we reuse the getValues function and set the second param to true
        // as we are not interested in the values we discard them
        $entity->getValues($this, TRUE);
        // TODO: eventually there should be a seperate function for the field caching
        
      }
$tsa["pb $pb_id and adapter $aid"] = microtime(true) - $ts;
$ts = microtime(true);
    }

$tsa['all'] = microtime(true) - $ts1;
$tsa['eid'] = $entity_id;
#ddl($tsa, "time for saving");
#dpm($tsa, "time for saving");

    $bundle = \Drupal\wisski_core\Entity\WisskiBundle::load($bundle_id);
    if ($bundle) $bundle->flushTitleCache($entity_id);

  }

  /**
   * {@inheritdoc}
   * @TODO must be implemented
   */
  protected function doDeleteFieldItems($entities) {

    $local_adapters = array();
    $writable_adapters = array();

    $adapters = entity_load_multiple('wisski_salz_adapter');

    foreach($adapters as $aid => $adapter) {
      // we locate all writable stores
      // then we locate all local stores in these writable stores

      if($adapter->getEngine()->isWritable())
        $writable_adapters[$aid] = $adapter;
             
      if($adapter->getEngine()->isPreferredLocalStore())
        $local_adapters[$aid] = $adapter;
      
    }
    // if there are no adapters by now we die...
    if(empty($writable_adapters)) {
      drupal_set_message("There is no writable storage backend defined.", "error");
      return;
    }
    
    if($diff = array_diff_key($local_adapters,$writable_adapters)) {
      if (count($diff) === 1)
        drupal_set_message('The preferred local store '.key($diff).' is not writable','warning');
      else drupal_set_message('The preferred local stores '.implode(', ',array_keys($diff)).' are not writable','warning');
    }
    
    //we load all pathbuilders, check if they know the fields and have writable adapters
    $pathbuilders = WisskiPathbuilderEntity::loadMultiple();

    foreach($pathbuilders as $pb_id => $pb) {
    
      //get the adapter
      $aid = $pb->getAdapterId();

      //check, if it's writable, if not we can stop here
      if (isset($writable_adapters[$aid])) $adapter = $writable_adapters[$aid];
      else continue;

      foreach($entities as $entity)
        $return = $adapter->deleteEntity($entity);
    }
    
    foreach($entities as $entity) {
      AdapterHelper::deleteUrisForDrupalId($entity->id());
    }

    if (empty($return)) {
      drupal_set_message('No local adapter could delete the entity','error');
      return;
    }
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
