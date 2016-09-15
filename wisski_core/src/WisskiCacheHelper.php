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
  }
  
  static function flushAllEntityTitles($bundle_id=NULL) {
    
    if (is_null($bundle_id)) $tags[] = 'wisski_bundled_titles.default';
    else $tags[] = 'wisski_bundled_titles.'.$bundle_id;
    Cache::invalidateTags($tags);
  }
  
  static function putCallingBundle($entity_id,$bundle_id) {
  
    // DEBUG, change $entity_id and open up in case you get 'Could not load entities in adapter sparql_1_1_with_pathbuilder because ...'-error
    // if ($entity_id === 'Albrecht Dürer') ddebug_backtrace();
    $db = \Drupal::service('database');
    $query = $db->select('wisski_calling_bundles','c')->fields('c')->condition('eid',$entity_id)->execute();
    if ($result = $query->fetch()) {
      if ($result->bid !== $bundle_id) $db->update('wisski_calling_bundles')->fields(array('bid' => $bundle_id))->condition('eid',$entity_id)->execute();
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
  
  static function putPreviewImage($entity_id,$preview_image_id,$preview_image_uri=NULL) {
  
    $cid = 'wisski_preview_image.'.$entity_id;
    self::putCacheData($cid,array($preview_image_id,$preview_image_uri));
  }
  
  static function getPreviewImage($entity_id,$id_only=TRUE) {
    
    $cid = 'wisski_preview_image.'.$entity_id;
    $list = self::getCacheData($cid);
    if ($id_inly) return $list[0];
    return $list;
  }
  
  static function flushPreviewImage($entity_id) {
    
    $cid = 'wisski_preview_image.'.$entity_id;
    self::flushCacheData($cid);
  }
}