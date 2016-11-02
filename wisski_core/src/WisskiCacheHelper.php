<?php

namespace Drupal\wisski_core;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Cache;

class WisskiCacheHelper {

  static function putCacheData($cid,$data,$tags=NULL) {
    if (is_null($tags)) {
      \Drupal::cache()->set($cid, $data);
    } else {
      \Drupal::cache()->set($cid,$data,CacheBackendInterface::CACHE_PERMANENT,$tags);
    }
  }
  
  static function getCacheData($cid) {
    if ($cache = \Drupal::cache()->get($cid)) return $cache->data;
    return NULL;
  }
  
  static function flushCacheData($cid) {
    \Drupal::cache()->delete($cid);
  }

  static function putEntityTitle($entity_id,$entity_title,$bundle_id=NULL) {
    
    $tags[] = 'wisski_bundled_titles.default';
    $cid = 'wisski_title.'.$entity_id.'.default';
    self::putCacheData($cid,$entity_title,$tags);
    if (!is_null($bundle_id)) {
      $tags[] = 'wisski_bundled_titles.'.$bundle_id;
      $cid = 'wisski_title.'.$entity_id.'.'.$bundle_id;
      self::putCacheData($cid,$entity_title,$tags);
    }

    // store the title in n-grams table
    db_delete('wisski_title_n_grams')->condition('ent_num', $entity_id)->condition('bundle', empty($bundle_id) ? "default" : $bundle_id)->execute();
    db_insert('wisski_title_n_grams')->fields(array(
        'ent_num' => $entity_id,
        'bundle' => empty($bundle_id) ? "default" : $bundle_id,
        'ngram' => $entity_title,
        'n' => mb_strlen($entity_title),
      ))->execute();

  }
  
  static function getEntityTitle($entity_id,$bundle_id=NULL) {
    
    if (is_null($bundle_id)) $bundle_id = 'default';
    $cid = 'wisski_title.'.$entity_id.'.'.$bundle_id;
    return self::getCacheData($cid);
  }
  
  static function flushEntityTitle($entity_id,$bundle_id=NULL) {
  
    if (is_null($bundle_id)) $bundle_id = 'default';
    $cid = 'wisski_title.'.$entity_id.'.'.$bundle_id;
    self::flushCacheData($cid);

    db_delete('wisski_title_n_grams')->condition('ent_num', $entity_id)->condition('bundle', empty($bundle_id) ? "default" : $bundle_id)->execute();
  
  }
  
  static function flushAllEntityTitles($bundle_id=NULL) {
    
    if (is_null($bundle_id)) $tags[] = 'wisski_bundled_titles.default';
    else $tags[] = 'wisski_bundled_titles.'.$bundle_id;
    Cache::invalidateTags($tags);

    db_delete('wisski_title_n_grams')->condition('bundle', empty($bundle_id) ? "default" : $bundle_id)->execute();

  }
  
  static function putCallingBundle($entity_id,$bundle_id) {
  
    // DEBUG, change $entity_id and open up in case you get 'Could not load entities in adapter sparql_1_1_with_pathbuilder because ...'-error
    // if ($entity_id === 'Leo') ddebug_backtrace();
    $db = \Drupal::service('database');
    $query = $db->select('wisski_calling_bundles','c')->fields('c')->condition('eid',$entity_id)->execute();
    if ($result = $query->fetch()) {
      if ($result->bid !== $bundle_id) {
        $db->update('wisski_calling_bundles')->fields(array('bid' => $bundle_id))->condition('eid',$entity_id)->execute();
      }
    } else {
      $db->insert('wisski_calling_bundles')->fields(array('eid' => $entity_id,'bid' => $bundle_id))->execute();
    }
  }
  
  static function getCallingBundle($entity_id) {
  
    if ($record = \Drupal::service('database')->select('wisski_calling_bundles','c')->fields('c')->condition('eid',$entity_id)->execute()->fetch())
      return $record->bid;
    else return NULL;
  }
  
  static function flushCallingBundle($entity_id) {
    
    $cid = 'wisski_individual.'.$entity_id.'.bundle';
    self::flushCacheData($cid);
  }
  
  static $gathered_preview_images;
  
  static function preparePreviewImages(array $entity_ids) {
    
    $query = db_select('wisski_preview_images','p')
              ->fields('p',array('eid','image_uri'))
              ->condition('eid',$entity_ids,'IN')
              ->execute();
    self::$gathered_preview_images = $query->fetchAllKeyed(0);
    //dpm(array($entity_ids,self::$gathered_preview_images),__FUNCTION__);
  }
  
  static function putPreviewImageUri($entity_id,$preview_image_uri) {

    //dpm($preview_image_uri,__FUNCTION__.' '.$entity_id);
    self::flushPreviewImageUri($entity_id);
    db_insert('wisski_preview_images')->fields(array('eid'=>$entity_id,'image_uri'=>$preview_image_uri))->execute();
  }
  
  static function getPreviewImageUri($entity_id) {
    //dpm(self::$gathered_preview_images,'GPI '.$entity_id);

    if (isset(self::$gathered_preview_images)) {

      if (isset(self::$gathered_preview_images[$entity_id])) {
        $return = self::$gathered_preview_images[$entity_id];
        return $return;
      }
    } else {
      $query = db_select('wisski_preview_images','p')->fields('p',array('image_uri'))->condition('eid',$entity_id)->execute();
      $result = $query->fetchCol();
      if (!empty($result)) return current($result);
    }
    return NULL;
  }
  
  /**
   * if $entity_id is NULL, we will truncate the table
   */
  static function flushPreviewImageUri($entity_id) {
    
    if (is_null($entity_id)) db_truncate('wisski_preview_images');
    else db_delete('wisski_preview_images')->condition('eid',$entity_id)->execute();
  }

}
