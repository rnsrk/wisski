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
    
    $exists = db_select('wisski_entity_map','m')->fields('m')->condition('eid',$entity_id)->execute()->fetchAll;
    if ($exists) $ent_num = end($exists)->num;
    else $ent_num = db_insert('wisski_entity_map')->fields(array('eid' => $entity_id))->execute();
    foreach(WisskiHelper::str_n_grams($entity_title) as $n => $ngrams) {
      foreach ($ngrams as $ngram) {
        db_insert('wisski_title_n_grams')->fields(array(
          'ent_num' => $ent_num,
          'ngram' => $ngram,
          'bundle' => $bundle_id ? : 'default',
          'n' => $n,
        ))->execute();
      }
    }
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
  
    $cid = 'wisski_individual.'.$entity_id.'.bundle';
    self::putCacheData($cid, $bundle_id);
  }
  
  static function getCallingBundle($entity_id) {
  
    $cid = 'wisski_individual.'.$entity_id.'.bundle';
    return self::getCacheData($cid);
  }
  
  static function flushCallingBundle($entity_id) {
    
    $cid = 'wisski_individual.'.$entity_id.'.bundle';
    self::flushCacheData($cid);
  }
  
  static function putPreviewImage($entity_id,$preview_image_id) {
  
    $cid = 'wisski_preview_image.'.$entity_id;
    self::putCacheData($cid,$preview_image_id);
  }
  
  static function getPreviewImage($entity_id) {
    
    $cid = 'wisski_preview_image.'.$entity_id;
    return self::getCacheData($cid);
  }
  
  static function flushPreviewImage($entity_id) {
    
    $cid = 'wisski_preview_image.'.$entity_id;
    self::flushCacheData($cid);
  }
}