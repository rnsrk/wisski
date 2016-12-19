<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\AdapterHelper.
 */

namespace Drupal\wisski_salz;

use \Drupal\Component\Utility\UrlHelper;
use \Drupal\wisski_salz\Entity\Adapter;

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

    if (empty($uris)) return TRUE;
    //dpm($uris,__FUNCTION__.' '.$entity_id);
    $drupal_aid = self::getDrupalAdapterNameAlias();
    if (array_key_exists($drupal_aid, $uris)) {
      //if we know the eid from the array, set it here
      if (empty($entity_id)) $entity_id = $uris[$drupal_aid];
      //do not save the URI-ified EID to the database
      unset($uris[$drupal_aid]);
    }
    $set_ids = db_select('wisski_salz_id2uri','m')
      ->fields('m',array('rid','uri','eid','adapter_id'))
      ->condition('uri',$uris,'IN')
      ->execute()
      ->fetchCol(2);
    //fetch the 'eid' column into $set_ids
    //dpm($set_ids,'set IDs');
    if (is_null($entity_id)) {  
      if (count($set_ids) === 1) {
        $entity_id = key($set_ids);
      } else {
        if (count($set_ids) > 1) {
          drupal_set_message('There are multiple entities connected with those uris','error');
          //dpm($set_ids,'multiple IDs');
        }
        return FALSE;
      }
    } elseif (!empty($set_ids) && !in_array($entity_id,$set_ids)) {
      drupal_set_message('There are already entities connected with those uris','error');
      //dpm($set_ids+array('new'=>$entity_id),'IDs');
      return FALSE;
    }
    $rows = db_select('wisski_salz_id2uri','m')
      ->fields('m',array('rid','uri','eid','adapter_id'))
      ->condition('uri',$uris,'IN')
      ->execute()
      ->fetchAllAssoc('adapter_id');
    //dpm($rows,'matchings from DB');
    foreach ($uris as $aid => $uri) {
      if (isset($rows[$aid]) && $row = $rows[$aid]) {
        //in this case we have info from this adapter
        //is it for the given URI?
        if ($row->uri === $uri) {
          if ($row->eid !== $entity_id) {
            //we consider this an EID update for this matching
            db_update('wisski_salz_id2uri')
              ->fields(array('eid'=>$entity_id))
              ->condition('rid',$row->rid)
              ->execute();
          }
        } elseif ($row->eid === $entity_id) {
          //this is a URI update for this matching
          db_update('wisski_salz_id2uri')
            ->fields(array('uri'=>$uri))
            ->condition('rid',$row->rid)
            ->execute();
        } else {
          //this is a completely new matching for this adapter
          db_insert('wisski_salz_id2uri')
            ->fields(array('uri'=>$uri,'eid'=>$entity_id,'adapter_id'=>$aid))
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
  
  public static function getDrupalIdForUri($uri,$create_on_fail=TRUE,$input_adapter_id=NULL) {
    // this should not happen! 
    if(is_null($uri)) {
      drupal_set_message("URI may not be empty in getDrupalIdForUri.", "error");
#      ddebug_backtrace();
      return;
    }
    $id = self::doGetDrupalIdForUri($uri,$create_on_fail,$input_adapter_id);
    //dpm(array_combine(array('$uri','$create_on_fail','$input_adapter_id'),func_get_args())+array('result'=>$id),__FUNCTION__);
    return $id;
  }
  
  /**
   * returns the Drupal ID for a given URI
   * @param $uri the input URI
   * @param $create_on_fail if there is no drupal ID for this entity, make one
   * @param $input_adapter_id the ID of the adapter that talks about the given URI, will be used as a hint for the standard search
   * or as the mapped adapter for the URI when a Drupal entity ID is created, for entity creation the preferred local store will be used when no adapter is set
   * @return the entity's Drupal ID
   */
  public static function doGetDrupalIdForUri($uri,$create_on_fail=TRUE,$input_adapter_id=NULL) {
   
    #drupal_set_message($uri);
#    dpm(func_get_args(),__FUNCTION__);
    $query = db_select('wisski_salz_id2uri','m')
      ->fields('m')
      ->condition('uri',$uri);
    if (isset($input_adapter_id)) $query->condition('adapter_id',$input_adapter_id);
    $ids = $query->execute()->fetchAllAssoc('eid');
    
    //if we have exactly one result for the eid return it
    if (count($ids) === 1) {
      //dpm(key($ids),'from DB');
      //drupal_set_message(key($ids));
      return key($ids);
    }
    
    //if we have multiple results, we don't know exactly what to do, for now we return the first
    //@TODO try something more sophisticated
    if (count($ids) > 1) {
      //dpm($ids,'from DB, multiple');
      drupal_set_message('there are multiple eids for this uri','error');
      return key($ids);
    }
    
    $local_adapter = self::getPreferredLocalStore();
    if (empty($input_adapter_id)) $adapter_id = $local_adapter->id();
    else $adapter_id = $input_adapter_id;
    
    //if we have nothing cached, ask the store for backup
    $id = $local_adapter->getEngine()->getDrupalIdForUri($uri,$adapter_id);
    
    //if the store knows the answer, return it
    if (!is_null($id)) {
      //dpm($id,'from local store');
      self::setSameUris(array($adapter_id=>$uri),$id);
      return $id;
    }
    
    //possibly another adapter knows this uri already, then the EID MUST be the same
    //this will only help, if an input adapter was set
    if (!empty($input_adapter_id)) {
      $id = self::getDrupalIdForUri($uri,FALSE);
      if (!is_null($id)) {
        //we know the correct ID now and must connect it with the given adapter
        //dpm($id,'From another store');
        self::setSameUris(array($input_adapter_id=>$uri),$id);
        return $id;
      }
    }
    
    //we have not been successfull by now
    //shall we try to create an eid?
    if (!$create_on_fail) {
      //dpm('fail','don\'t create');
      return NULL;
    }
    
    //eid creation works by inserting data and retrieving the newly set line number as eid
    $id = db_insert('wisski_salz_id2uri')
      ->fields(array('uri'=>$uri,'adapter_id'=>$input_adapter_id))
      ->execute();
    db_update('wisski_salz_id2uri')
      ->fields(array('eid'=>$id))
      ->condition('rid',$id)
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
  
  public static function getUrisForDrupalId($eid,$adapter_id=NULL) {
    
    
    if (!is_numeric($eid)) {
      //we probably got a URI as input, check that and return the input if it's valid
      //otherwise we cant do anything
      //use this block in other functions, too, if there is the probability of getting wrong inputs
      if (isset($adapter_id)) {
        $adapter = Adapter::load($adapter_id);      
        if ($adapter && $adapter->getEngine()->isValidUri($eid)) return $eid;
      }
      return FALSE;
    }
    $result = self::doGetUrisForDrupalId($eid,$adapter_id);
    //dpm(array('$eid'=>$eid,'$adapter_id'=>isset($adapter_id)? $adapter_id : 'NULL')+array('return'=>$result),__FUNCTION__);
    return $result;
  }
  

  /**
   * returns a set of URIs that are associated with the given Drupal entity ID
   * if there is no URI set for the given adapter, we will always try to create one.
   * @param $eid the entity's Drupal ID
   * @param $adapter_id if set the function will return at most one uri namely the one used in the adapter with this ID
   * @return an assocative array keyed by adapter ID with the associated URIs as values or | the URI associated with the input adapter
   */
  public static function doGetUrisForDrupalId($eid,$adapter_id=NULL) {

    //dpm($eid,__FUNCTION__.' '.$adapter_id);
    //first try the DB
    $query = db_select('wisski_salz_id2uri','m')
      ->fields('m',array('adapter_id','uri'))
      ->condition('eid',$eid);
    if (isset($adapter_id)) $query->condition('adapter_id',$adapter_id);
    $out = $query->execute();
    //dpm($out,'From DB');
    //if we get an answer from DB return it
    if (isset($adapter_id)) {
      //with adapter given, we only want the URI field returned
      $return = $out->fetchField(1);
      //var_dump($return);
      //dpm($return ? $return : 'FALSE','Single adapter');
      if ($return !== FALSE) return $return;
    } else {
      //with unspecified adapter, we want an associative array keyed by adapter with URIs as values
      $return = $out->fetchAllKeyed();
      //dpm($return,'Multiple adapters');
      if (!empty($return)) return $return;
    }
    
    //if we had no info from the DB
    if (isset($adapter_id)) {
      //try the local store backup
      //first we gather the matchings for other adapters, we can possibly find URIs there that fit our input adapter
      $old_uris = self::getUrisForDrupalId($eid);
      
      $same_uri = self::getPreferredLocalStore(TRUE)->findUriForDrupalId($eid,$adapter_id);
      //dpm($same_uri,'From Store with adapter '.$adapter_id);
      
      if (empty($same_uri)) {
        //if there was none, we try to find out whether the adapter knows any of the other URIs assocaited with
        //the EID
        $adapter = Adapter::load($adapter_id);
        foreach ($old_uris as $old_uri) {
          if ($adapter->checkUriExists($old_uri)) $same_uri = $old_uri;
        }
        if (empty($same_uri)) {
          //create on fail
          $same_uri = Adapter::load($adapter_id)->getEngine()->generateFreshIndividualUri();
        }
      }
      if (!empty($same_uri)) {
        self::setSameUris($old_uris + array($adapter_id=>$same_uri),$eid);
      }
      return $same_uri;
    } else {
      $same_uris = self::getPreferredLocalStore(TRUE)->getUrisForDrupalId($eid);
      //dpm($same_uris,'From Store, no adapter');
      self::setSameUris($same_uris,$eid);
      return $same_uris;
    }
  }

  public static function getFreshDrupalId() {
    
    $res = db_select('wisski_salz_id2uri','u')
      ->fields('u',array('eid'))
      ->orderBy('eid','DESC')
      ->execute()->fetch();
    if (empty($res)) return 1;
    else return $res->eid + 1;
  }

  
  public static function getPreferredLocalStore($retrieve_engine=FALSE,$ignore_errors=FALSE,$ignore_cache=FALSE) {

    $cid = 'wisski_salz_preferred_local_store';
    if (!$ignore_cache) {
      if ($cache = \Drupal::cache()->get($cid)) {
        $adapter_id =  $cache->data;
        $adapter = Adapter::load($adapter_id);
        if ($retrieve_engine) return $adapter->getEngine();
        else return $adapter;
      }
    }
    //since there is (or at least should be) only one preferred local store, we can stop on first sight
    //TODO: decide what to do if there is none (e.g. return NULL or return any from the list)
    $adapters = Adapter::loadMultiple();
    foreach ($adapters as $adapter) {
      $engine = $adapter->getEngine();
      if ($engine->isPreferredLocalStore()) {
        \Drupal::cache()->set($cid,$adapter->id());
        if ($retrieve_engine) return $engine;
        else return $adapter;
      }
    }
    
    //if we reach here, there is no preferred local store
    if (!$ignore_errors) {
      //throw new \Exception('There is no preferred local store set');
      $link = \Drupal\Core\Link::createFromRoute(t('here',array(),array('context'=> 'There is no preferred local store set. Please specify one ')),'entity.wisski_salz_adapter.collection');
      drupal_set_message(t('There is no preferred local store set. Please specify one %here',array('%here' => $link->toString())),'error');
    }
  }
  
  public static function resetPreferredLocalStore() {
    
    \Drupal::cache()->delete('wisski_salz_preferred_local_store');
    self::getPreferredLocalStore(FALSE,TRUE,TRUE);
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
    if (UrlHelper::isValid($url, TRUE)) {
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
