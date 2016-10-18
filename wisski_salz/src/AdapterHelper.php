<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\AdapterHelper.
 */

namespace Drupal\wisski_salz;

class AdapterHelper {
  
  /**
   * Get the Drupal-usable Id for a given URI.
   * @param uri the URI
   * @param create_if_not_exists if there is no mapping for the URI generate a
   *        new Id
   * @return The ID or NULL if there is no mapping and $create_if_not_exists is
   *        false.
   */
  public static function getDrupalIdForUri($uri, $adapter_id=NULL,$create_if_not_exists = TRUE) {
    $query = db_select('wisski_salz_id2uri', 'm')
              ->fields('m', array('eid'))
              ->condition('uri', $uri);
    if (isset($adapter_id)) $query->condition('adapter_id',$adapter_id);
    $row = $query->execute()->fetchObject();
#dpm(array($row, $uri), "getids");
    if (!empty($row)) return $row->eid;
    // create an ID if we are told to do so
    if ($create_if_not_exists) return self::setDrupalIdForUri($uri, NULL, $adapter_id);
    return NULL;
  }
  
  /** 
   * Updates/inserts/deletes a mapping between a URI and a Drupal-usable, 
   * numeric Id.
   * @param uri the URI. A string and must not be NULL.
   * @param eid the entity ID. An entity is always numeric! If NULL and there 
   *        exists a mapping, the mapping is deleted. If NULL and there is no
   *        mapping yet, a mapping with a generated entity ID is created.
   * @param check_exists perform a check first, whether there already exists a
   *        mapping
   * @param exists specify whether the function should behave as if there
   *        already exists a mapping. This parameter is only considered if
   *        check_exists is FALSE.
   * @return the set/generated entity ID or NULL if the mapping was deleted
   */
  public static function setDrupalIdForUri($uri, $eid = NULL, $adapter_id=NULL) {
    //dpm(func_get_args(),__FUNCTION__);
    
    //first we check if there is any entry for this URI
    //we don't use self::getDrupalIdForUri to avoid infinite loops
    $existing_eids = db_select('wisski_salz_id2uri','m')->fields('m')->condition('uri',$uri)->execute()->fetchAllAssoc('adapter_id');
    if (!empty($existing_eids)) {
      // update existing mapping
      if (isset($adapter_id)) {
        
        if (isset($existing_eids[$adapter_id])) {
          //if we know the uri-adapter combination
          //we must either update its eid or delete this mapping
          if ($eid === NULL) {
            //delete
            db_delete('wisski_salz_id2uri')
              ->condition('uri',$uri)
              ->condition('adapter_id',$adapter_id)
              ->execute();
            return NULL;
          } else {
            //update
            db_update('wisski_salz_id2uri')
              ->fields(array('eid'=>$eid))
              ->condition('uri',$uri)
              ->condition('adapter_id',$adapter_id)
              ->execute();
            return $eid;
          }
        } else {
          //if the adapter has no entry AND the eid is not set, we believe we shall guess one
          //this then is the existsing eid from another adapter for that same uri
          if ($eid === NULL) $eid = current($existing_eids)->eid;
          db_insert('wisski_salz_id2uri')
            ->fields(array('eid'=>$eid,'uri'=>$uri,'adapter_id'=>$adapter_id))
            ->execute();
          return $eid;
        }
      } else {
        //there is no adapter set
        if ($eid === NULL) {
          // delete all mappings for this uri
          db_delete('wisski_salz_id2uri')->condition('uri', $uri)->execute();
          return NULL;
        } else {
          //update eid for this URI in all existing adapters
          db_update('wisski_salz_id2uri')->fields(array('eid' => $eid))->condition('uri', $uri)->execute();
          return $eid;
        }
      }
    } else {
      //no entry for this uri exists
      if ($eid === NULL) {
        // create a mapping and generate a new eid
        $eid = db_insert('wisski_salz_id2uri')->fields(array('uri' => $uri,'adapter_id'=>$adapter_id))->execute();
        //immediately update the newly inserted row so that the eid is saved correctly
        db_update('wisski_salz_id2uri')->fields(array('eid'=>$eid))->condition('rid',$eid)->execute();
        return $eid;
      } else {
        // create a mapping and with a given eid
        db_insert('wisski_salz_id2uri')->fields(array('uri' => $uri, 'eid' => $eid, 'adapter_id'=>$adapter_id))->execute();
        return $eid;
      }
    }
  }
  
  /**
   * Get all URIs for the given Drupal-usable id
   * @param eid the entity ID
   * @return an array of URIs. If there is no mapping, returns an empty array.
   */
  public static function getUrisForDrupalId($eid,$adapter_id=NULL) {
    // we preprocess the entity id
    // one may pass an object with field eid for convienience
    if (is_object($eid) && isset($eid->eid)) {
      $eid = $eid->eid;
    }
    if (is_integer($eid) || (is_string($eid) && preg_match('/^\d+$/', $eid))) {
      $query = db_select('wisski_salz_id2uri', 'm')
        ->fields('m', array('uri'))
        ->condition('eid', $eid);
      if (isset($adapter_id)) $query->condition('adapter_id',$adapter_id);
      $uris = $query->execute()->fetchCol();
#dpm(array($eid, $uris, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)), "geturis");
      if (empty($uris)) return array();
      return $uris;
    } else {
      throw new \InvalidArgumentException("bad entity id: $eid");
    }
  }
  

  public static function createCanonicalWisskiUri($options) {
    

  }
  
  public static function getPreferredLocalStore() {

    $cid = 'wisski_salz_preferred_local_store';
    if ($cache = \Drupal::cache()->get($cid)) {
      return $cache->data;
    }
    //since there is (or at least should be) only one preferred local store, we can stop on first sight
    //TODO: decide what to do if there is none (e.g. return NULL or return any from the list)
    foreach (\Drupal::entityManager()->getStorage('wisski_salz_adapter')->loadMultiple() as $adapter) {
      if ($adapter->getEngine()->isPreferredLocalStore()) {
        \Drupal::cache()->set($cid,$adapter);
        return $adapter;
      }
    }
  }
}
