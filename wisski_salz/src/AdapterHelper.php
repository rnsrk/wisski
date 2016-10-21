<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\AdapterHelper.
 */

namespace Drupal\wisski_salz;

class AdapterHelper {

  /**
   * saves a set of URI mappings with an optional drupal entity id
   * @param $uris an associative array where the keys are adapter_ids and the values are uris which all mean the same individuum
   * the mapping denotes that the very adapter is holding information about that very URI
   * @param $entity_id the drupal ID for the entity that all the uris from $uris identify. If NULL we just save the uri identification without drupal ID matching
   */
  public static function setSameUris($uris,$entity_id=NULL) {
    
  }
  
  /**
   * retrieves a set of URI mappings
   * @param $uri an entity uri (not a Drupal entity ID)
   * @param $input_adapter_id if set this will be used as a hint where to look for the input URI
   * @return an associative array where the keys are adapter_ids and the values are uris which all mean the same individuum
   * the mapping denotes that the very adapter is holding information about that very URI
   */
  public static function getSameUris($uri,$input_adapter_id=NULL) {
  
  }
  
  /**
   * returns the URI that the given adapter uses to talk about the individual with the input URI i.e. that has that given URI in another adapter
   * @param $uri the input URI as used in the input adapter
   * @param $output_adapter_id the ID of the adapter that we want to know the output URI from
   * @param $input_adapter_id if set this will be used as a hint where to look for the input URI
   * @return the same-as URI from the output adapter
   */
  public static function getSameUri($uri,$output_adapter_id,$input_adapter_id=NULL) {
  
  }
  
  /**
   * returns the Drupal ID for a given URI
   * @param $uri the input URI
   * @param $create_on_fail if there is no drupal ID for this entity, make one, will only be done if $input_adapter_id is set
   * @param $input_adapter_id the ID of the adapter that talks about the given URI, will be used as a hint for the standard search
   * or as the mapped adapter for the URI when a Drupal entity ID is created
   * @return the entity's Drupal ID
   */
  public static function getDrupalIdForUri($uri,$create_on_fail=TRUE,$input_adapter_id=NULL) {
  
  }
  
  /**
   * returns a set of URIs that are associated with the given Drupal entity ID
   * @param $eid the entity's Drupal ID
   * @param $adapter_id if set the function will return at most one uri namelythe one used in the adapter with this ID
   * @return an assocative array keyed by adapter ID with the associated URIs as values or | the URI associated with the input adapter
   */
  public static function getUrisForDrupalId($eid,$adapter_id=NULL) {
  
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
