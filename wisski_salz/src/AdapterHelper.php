<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\AdapterHelper.
 */

namespace Drupal\wisski_salz;

use \Drupal\Component\Utility\UrlHelper;

class AdapterHelper {

  /**
   * For some uri-id mapping functions to work correctly, we need an Adapter-Dummy name to be correlated with Drupal Entity IDs
   * this function here should be called by all adapters implementing the setSameUris and getSameUris functions
   * @returns a generic name as adapapter-name-like array key, representing Entity IDs
   */
  public static function getDrupalAdapterNameAlias() {
  
    return 'drupal_id';
  }
  
  /**
   * saves a set of URI mappings with an optional drupal entity id. This method saves the info in the Drupal database and writes it through to the preferred local adapter
   * so that in case of a DB breakdown we can re-establish the data from the local store
   * @param $uris an associative array where the keys are adapter_ids and the values are uris which all mean the same individuum
   * the mapping denotes that the very adapter is holding information about that very URI
   * @param $entity_id the drupal ID for the entity that all the uris from $uris identify. If NULL we just save the uri identification without drupal ID matching.
   * If no entity ID is provided AND none can be found in the data, we will create one for later use
   * @return TRUE on success, FALSE otherwise
   */
  public static function setSameUris($uris,$entity_id=NULL) {
    
    $drupal_aid = self::getDrupalAdapterNameAlias();
    if (array_key_exists($drupal_aid, $uris)) {
      //if we know the eid from the array, set it here
      if (empty($entity_id)) $entity_id = $uris[$drupal_aid];
      //do not save the URI-ified EID to the database
      unset($uris[$drupal_aid]);
    }
    $cached = db_select('wisski_salz_id2uri','m')->fields('m')->condition('uri',$uris,'IN')->execute();
    if (is_null($entity_id)) {
      $set_ids = $cached->fetchAllAssoc('eid');
      if (count($set_ids) === 1) {
        $entity_id = key($set_ids);
      } else {
        if (count($set_ids) > 1) {
          drupal_set_message('There are multiple entities connected with those uris','error');
        }
        return FALSE;
      }
    }
    $set_uris = $cached->fetchAllAssoc('uri');
    foreach ($uris as $aid => $uri) {
      if (empty($aid)) throw new \Exception('Empty adapter id in '.serialize($uris));
      if (isset($set_uris[$uri]) && $row = $set_uris[$uri]) {
        if ($row->adapter_id !== $aid || $row->eid !== $entity_id) {
          db_update('wisski_salz_id2uri')
            ->fields(array('uri'=>$uri,'eid'=>$entity_id,'adapter_id'=>$aid))
            ->condition('rid',$row->rid)
            ->execute();
        }
      } else {
        db_insert('wisski_salz_id2uri')
          ->fields(array('uri'=>$uri,'eid'=>$entity_id,'adapter_id'=>$aid))
          ->execute();
      }
    }
    
    self::getPreferredLocalStore(TRUE)->setSameUris($uris,$entity_id);
    return TRUE;
  }
  
  /**
   * retrieves a set of URI mappings
   * @param $uri an entity uri (not a Drupal entity ID)
   * @param $input_adapter_id if set this will be used as a hint where to look for the input URI
   * @return an associative array where the keys are adapter_ids and the values are uris which all mean the same individuum
   * the mapping denotes that the very adapter is holding information about that very URI
   */
  public static function getSameUris($uri,$input_adapter_id=NULL) {

    $eid = db_select('wisski_salz_id2uri','m')
      ->fields('m',array('eid'))
      ->condition('uri',$uri);
    if (isset($input_adapter_id)) $eid->condition('adapter_id',$input_adapter_id);
    $query = db_select('wisski_salz_id2uri','m')
      ->fields('m',array('adapter_id','uri'))
      ->condition('eid',$eid,'IN')
      ->execute();
    $out = $query->fetchAllKeyed();
    if (!empty($out)) return $out;
    $same_uris = self::getPreferredLocalStore(TRUE)->getSameUris($uri);
    self::setSameUris($same_uris);
    return $same_uris;
  }
  
  /**
   * returns the URI that the given adapter uses to talk about the individual with the input URI i.e. that has that given URI in another adapter
   * @param $uri the input URI as used in the input adapter
   * @param $output_adapter_id the ID of the adapter that we want to know the output URI from
   * @param $input_adapter_id if set this will be used as a hint where to look for the input URI
   * @return the same-as URI from the output adapter
   */
  public static function getSameUri($uri,$output_adapter_id,$input_adapter_id=NULL) {
  
    $eid = db_select('wisski_salz_id2uri','m')
      ->fields('m',array('eid'))
      ->condition('uri',$uri);
    if (isset($input_adapter_id)) $eid->condition('adapter_id',$input_adapter_id);
    $query = db_select('wisski_salz_id2uri','m')
      ->fields('m',array('uri'))
      ->condition('eid',$eid,'IN')
      ->condition('adapter_id',$output_adapter_id)
      ->execute();
    $out = $query->fetchField();
    if (!empty($out)) return $out;
    $same_uri = self::getPreferredLocalStore(TRUE)->getSameUri();
    if (isset($input_adapter_id)) self::setSameUris(array($input_adapter_id=>$uri,$output_adapter_id=>$same_uri));
    return $same_uri;
  }
  
  /**
   * returns the Drupal ID for a given URI
   * @param $uri the input URI
   * @param $create_on_fail if there is no drupal ID for this entity, make one
   * @param $input_adapter_id the ID of the adapter that talks about the given URI, will be used as a hint for the standard search
   * or as the mapped adapter for the URI when a Drupal entity ID is created, for entity creation the preferred local store will be used when no adapter is set
   * @return the entity's Drupal ID
   */
  public static function getDrupalIdForUri($uri,$create_on_fail=TRUE,$input_adapter_id=NULL) {

    //dpm(func_get_args(),__FUNCTION__);
    $query = db_select('wisski_salz_id2uri','m')
      ->fields('m')
      ->condition('uri',$uri);
    if (isset($input_adapter_id)) $query->condition('adapter_id',$input_adapter_id);
    $ids = $query->execute()->fetchAllAssoc('eid');
    
    //if we have exactly one result for the eid return it
    if (count($ids) === 1) {
      //dpm(key($ids),'from DB');
      return key($ids);
    }
    
    //if we have multiple results, we don't know exactly what to do, for now we return the first
    //@TODO try something more sophisticated
    if (count($ids) > 1) {
      //dpm($ids,'from DB, multiple');
      drupal_set_message('there are multiple eids for this uri','error');
      return key($ids);
    }
    
    //if we have nothing cached, ask the store for backup
    $id = self::getPreferredLocalStore(TRUE)->getDrupalIdForUri($uri,$input_adapter_id);
    
    //if the store knows the answer, return it
    if (!is_null($id)) {
      //dpm($id,'from local store');
      self::setSameUris(array($input_adapter_id=>$uri),$id);
      return $id;
    }
    
    //we have not been successfull by now
    //shall we try to create an eid?
    if (!$create_on_fail) {
      //dpm('fail','don\'t create');
      return NULL;
    }
    
    if (empty($input_adapter_id)) $input_adapter_id = self::getPreferredLocalStore()->id();
    
    //eid creation works by inserting data and retrieving the newly set line number as eid
    $id = db_insert('wisski_salz_id2uri')
      ->fields(array('uri'=>$uri,'adapter_id'=>$input_adapter_id))
      ->execute();
    
    //don't forget to inform the services about the new id
    if (self::setSameUris(array($input_adapter_id=>$uri),$id)) {
      //dpm($id,'set anew');
      return $id;
    }
    //dpm('fail','creation failed');
    //if we end up here we, can't do any more
    return NULL;
  }
  
  /**
   * returns a set of URIs that are associated with the given Drupal entity ID
   * @param $eid the entity's Drupal ID
   * @param $adapter_id if set the function will return at most one uri namely the one used in the adapter with this ID
   * @return an assocative array keyed by adapter ID with the associated URIs as values or | the URI associated with the input adapter
   */
  public static function getUrisForDrupalId($eid,$adapter_id=NULL) {
    
    $query = db_select('wisski_salz_id2uri','m')
      ->fields('m',array('adapter_id','uri'))
      ->condition('eid',$eid);
    if (isset($adapter_id)) $query->condition('adapter_id',$adapter_id);
    $out = $query->execute();
    if (!empty($out)) {
      if (isset($adapter_id)) return $out->fetchField(1);
      return $out->fetchAllKeyed();
    }
    if (isset($adapter_id)) {
      $same_uri = self::getPreferredLocalStore(TRUE)->findUriForDrupalId($eid,$adapter_id);
      self::setSameuris(array($adapter_id=>$same_uri),$eid);
      return $same_uri;
    } else {
      $same_uris = self::getPreferredLocalStore(TRUE)->getUrisForDrupalId($eid);
      self::setSameUris($same_uris,$eid);
      return $same_uris;
    }
  }

  public static function createCanonicalWisskiUri($options) {
    
  }
  
  public static function getPreferredLocalStore($retrieve_engine=FALSE) {

    $cid = 'wisski_salz_preferred_local_store';
    if ($cache = \Drupal::cache()->get($cid)) {
      $adapter =  $cache->data;
      if ($retrieve_engine) return $adapter->getEngine();
      else return $adapter;
    }
    //since there is (or at least should be) only one preferred local store, we can stop on first sight
    //TODO: decide what to do if there is none (e.g. return NULL or return any from the list)
    foreach (\Drupal::entityManager()->getStorage('wisski_salz_adapter')->loadMultiple() as $adapter) {
      $engine = $adapter->getEngine();
      if ($engine->isPreferredLocalStore()) {
        \Drupal::cache()->set($cid,$adapter);
        if ($retrieve_engine) return $engine;
        else return $adapter;
      }
    }
    
    //if we reach here, there is no preferred local store, this is bad so we can
    throw new \Exception('There is no preferred local store set');
  }
  
  /**
   * generates a URI from a given Drupal Entity ID
   * to be saved in the Triple Store
   * the reverse function of self::extractIdFromWisskiUri
   * @param $eid the entity ID
   * @return a WissKI-specific URI (without < >) representing an individual with the given $eid
   */
  public static function generateWisskiUriFromId($eid) {
    
    $url = \Drupal\Core\Url::fromRoute('entity.wisski_individual.canonical',array('wisski_individual'=>$eid));
    global $base_url;
    return $base_url.'/'.$url->getInternalPath();
  }
  
  /**
   * extracts a Drupal Entity ID from a given URI
   * the reverse function of self::generateWisskiUriFromId
   * @param $uri a WissKI-specific URI
   * @return a Drupal ID representing an entity with the given $uri
   */
  public static function extractIdFromWisskiUri($uri) {
    
    list($eid) = self::extractEntityInfoFromRouteUrl($uri);
    return $eid;
  }

  public static function extractEntityInfoFromRouteUrl($url,$route_name='entity.wisski_individual.canonical') {
  
    //strip whitespaces
    $url = preg_replace("/(^\s+)|(\s+$)/us", "", $url);

    global $base_root, $base_path;
    $br_len = strlen($base_root);
    $bp_len = strlen($base_path);
    
    // otherwise, we try to match the url against a route.
    // note that it still can begin with a schema if the adapters
    // didn't match

    // strip off fragment and query parts
    // keep parts to guess the bundle
    $parts = UrlHelper::parse($url);
    $url = $parts['path'];

    // check if it has a schema and remove it if so
    if (!$check || UrlHelper::isValid($url, TRUE)) {
      if (substr($url, 0, $br_len) == $base_root) {
        $url = substr($url, $br_len);
      }
    }
    
    // check if it has the site's prefix and remove it
    if (UrlHelper::isValid($url, FALSE)) {
    #} elseif (UrlHelper::isValid($url, FALSE)) {
    
      if (substr($url, 0, $bp_len) == $base_path) {
        // strip base_path
        $url = substr($url, $bp_len);
    
        // but let path begin with an '/' as the route matcher requires so.
        if (substr($url, 0, 1) !== '/') $url = '/' . $url;

        try {
          $route = \Drupal::service('router')->match($url);
          if ($route['_route'] == $route_name) {
            $bundle = isset($parts['query']['wisski_bundle']) ? $parts['query']['wisski_bundle'] : NULL;
            return array($route['wisski_individual'], $bundle, $route['_route']);
          }
        } catch (\Exception $e) {}
      }
    }
  
    return array(NULL, NULL, NULL);
  }
}
