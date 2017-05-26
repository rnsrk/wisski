<?php

namespace Drupal\wisski_pathbuilder;



class PathbuilderManager {
   
  private static $pbsForAdapter = NULL;
  
  private static $pbsUsingBundle = NULL;

  
  /** Reset the cached mappings.
   */
  public function reset() {
    self::$pbsForAdapter = NULL;
    self::$pbsUsingBundle = NULL;
    \Drupal::cache()->delete('wisski_pathbuilder_manager_pbs_for_adapter');
    \Drupal::cache()->delete('wisski_pathbuilder_manager_pbs_using_bundle');
  }
  
  
  /** Get the pathbuilders that make use of a given adapter.
   *  
   * @param adapter_id the ID of the adapter
   * @return if adapter_id is empty, returns an array where the keys are
   *          adapter IDs and the values are arrays of corresponding 
   *          pathbuilders. If adapter_id is given returns an array of 
   *          corresponding pathbuilders.
   */
  public function getPbsForAdapters($adapter_id = NULL) {
    if (self::$pbsForAdapter === NULL) {  // not yet fetched from cache?
      if ($cache = \Drupal::cache()->get('wisski_pathbuilder_manager_pbs_for_adapter')) {
        self::$pbsForAdapter = $cache->data;
      }
    }
    if (self::$pbsForAdapter === NULL) {  // was reset
      self::$pbsForAdapter = array();
      $pbs = entity_load_multiple('wisski_pathbuilder');
      foreach ($pbs as $pb) {
        $aid = $pb->getAdapterId();
        $adapter = entity_load('wisski_salz_adapter', $aid);
        if ($adapter) {
          if (!isset(self::$pbsForAdapter[$aid])) {
            self::$pbsForAdapter[$aid] = array();
          }
          self::$pbsForAdapter[$aid][$pbid] = $pbid;
        }
        else {
          drupal_set_message(t('Pathbuilder %pb refers to non-existing adapter with ID %aid.', array(
            '%pb' => $pb->getName(),
            '%aid' => $pb->getAdapterId(),
          )), 'error');
        }
      }
      \Drupal::cache()->set('wisski_pathbuilder_manager_pbs_for_adapter', self::$pbsForAdapter);
    }
    return empty($adapter_id) ? self::$pbsForAdapter : self::$pbsForAdapter[$adapter_id];
  }

  
  public function getPbsUsingBundle($bundle_id) {
    if (self::$pbsUsingBundle === NULL) {  // not yet fetched from cache?
      if ($cache = \Drupal::cache()->get('wisski_pathbuilder_manager_pbs_using_bundle')) {
        self::$pbsUsingBundle = $cache->data;
      }
    }
    if (self::$pbsUsingBundle === NULL) {  // was reset, recalculate
      self::$pbsUsingBundle = array();
      $pbs = entity_load_multiple('wisski_pathbuilder');
      foreach ($pbs as $pbid => $pb) {
        foreach ($pb->getAllGroups() as $group) {
          $bid = $pb->getPbPath($group->getID())['bundle'];
          if (!empty($bid)) {
            if (!isset(self::$pbsUsingBundle[$bid])) {
              self::$pbsUsingBundle[$bid] = array();
            }
            $adapter = entity_load('wisski_salz_adapter', $pb->getAdapterId());
            if ($adapter) {
              $engine = $adapter->getEngine();
              $info = array(
                'pb_id' => $pbid,
                'adapter_id' => $adapter->id(),
                'writable' => $engine->isWritable(),
                'preferred_local' => $engine->isPreferredLocalStore(),
                'engine_plugin_id' => $engine->getPluginId(),
              );
              self::$pbsUsingBundle[$bid][$pbid] = $info;
            }
            else {
              drupal_set_message(t('Pathbuilder %pb refers to non-existing adapter with ID %aid.', array(
                '%pb' => $pb->getName(),
                '%aid' => $pb->getAdapterId(),
              )), 'error');
            }
          }
        }
      }
      \Drupal::cache()->set('wisski_pathbuilder_manager_pbs_using_bundle', self::$pbsUsingBundle);
    }
    return isset(self::$pbsUsingBundle[$bundle_id]) ? self::$pbsUsingBundle[$bundle_id] : array();
  }

  

}
