<?php

/* old function
function wisski_core_lod_make_internal_uri($entity_title,$bundle_name,$ind_only = TRUE) {
  
  $bundle_uri = db_select('wisski_entity_bundles','b')
                  ->fields('b',array('type','uri'))
                  ->condition('type',$bundle_name)
                  ->execute();
  if ($bundle_uri->rowCount() > 0) {
    $bundle_uri = $bundle_uri->fetchObject()->uri;
  } else return FALSE;
//  dpm($bundle_uri);
  $ind_uri = wisski_salz_pb_make_uri($bundle_uri,substr($entity_title,0,8));

  $trace = debug_backtrace();
  watchdog('wisski_make_uri',$entity_title.' '.$bundle_name.' '.$ind_uri.' from '.serialize($trace[1]));
  if ($ind_only)
    return $ind_uri;
  else return array($ind_uri,$bundle_uri);
}
end old make uri function */



function wisski_core_lod_make_internal_uri($entity_title,$bundle_name,$ind_only = TRUE) {
  
  $bundle_uri = db_select('wisski_entity_bundles','b')
                  ->fields('b',array('type','uri'))
                  ->condition('type',$bundle_name)
                  ->execute();
  if ($bundle_uri->rowCount() > 0) {
    $bundle_uri = $bundle_uri->fetchObject()->uri;
    $local_store = wisski_salz_invoke_local_store();
    $ns = $local_store->getNamespaces();
#    $bundle_uri = mb_str_replace();
      
    
  } else {
    $bundle_uri = '';
  }


//  dpm($bundle_uri);
  $ind_uri = wisski_salz_pb_make_uri($bundle_uri,substr($entity_title,0,8));

  $trace = debug_backtrace();
  watchdog('wisski_make_uri',$entity_title.' '.$bundle_name.' '.$ind_uri.' from '.serialize($trace[1]));
  if ($ind_only)
    return $ind_uri;
  else return array($ind_uri,$bundle_uri);
}



