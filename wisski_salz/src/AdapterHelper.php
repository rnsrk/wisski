<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\AdapterHelper.
 */

namespace Drupal\wisski_salz;


class AdapterHelper {

  public static function getDrupalIdForUri($uri, $create_if_not_exists = TRUE) {
    $row = db_select('wisski_salz_id2uri', 'm')
              ->fields('m', array('eid'))
              ->condition('uri', $uri)
              ->execute()
              ->fetchObject();
    if (!empty($row)) return $row->eid;
    // create an ID if we are told to do so
    if ($create_if_not_exists) return self::setDrupalIdForUri($uri, NULL, FALSE, FALSE);
    return NULL;
  }
  
  /** Updates/inserts/deletes a mapping.
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
  public static function setDrupalIdForUri(string $uri, int $eid = NULL, $check_exists = TRUE, $exists = FALSE) {
    // looks as if we cannot use db_upsert
    if ($check_exists) {
      $exists = self::getDrupalIdForUri($uri);
    }
    if ($exists && $eid === NULL) {
      // delete mapping
      db_delete('wisski_salz_id2uri')->condition('uri', $uri)->execute();
      return NULL;
    } elseif ($exists) {
      // update existing mapping
      db_update('wisski_salz_id2uri')->fields(array('eid' => $eid))->condition('uri', $uri)->execute();
      return $eid;
    } elseif ($eid === NULL) {
      // create a mapping and generate a new eid
      $eid = db_insert('wisski_salz_id2uri')->fields(array('uri' => $uri))->execute();
      self::setDrupalIdForUri($uri, $eid, FALSE, TRUE);
      return $eid;
    } else {
      // create a mapping and with a given eid
      db_insert('wisski_salz_id2uri')->fields(array('uri' => $uri, 'eid' => $eid))->execute();
      return $eid;
    }
  }
  
  /**
   * Get all URIs for the 
   */
  public static function getUrisForDrupalId($eid) {
    $uris = db_select('wisski_salz_id2uri', 'm')
              ->fields('m', array('uri'))
              ->condition('eid', $eid)
              ->execute()
              ->fetchCol();
    if (empty($uris)) return array();
    return $uris;
  }
  
  


}
