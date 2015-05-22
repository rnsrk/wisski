<?php

function wisski_core_lod_make_internal_uri($entity_title,$bundle_name,$ind_only = TRUE) {
  
  // first we check if there is a better function for this
  $callback = variable_get('wisski_core_lod_internal_uri_callback', '');
  if ($callback && function_exists($callback)) {
    $result = call_user_func($callback, $entity_title, $bundle_name);
    return $ind_only ? $result[0] : $result;
  }

  if (!$ind_only) {
    $bundle_uri = db_select('wisski_entity_bundles','b')
                  ->fields('b',array('type','uri'))
                  ->condition('type',$bundle_name)
                  ->execute();
    if ($bundle_uri->rowCount() > 0) {
      $bundle_uri = $bundle_uri->fetchObject()->uri;
    } else {
      $bundle_uri = 'owl:Thing';
    }
  }
  
	$ind_uri = wisski_salz_pb_make_uri($bundle_name, array('bundle' => $bundle_name, 'title' => $entity_title));
	watchdog('wisski sushi mako uri', 'input: '.htmlentities(implode(', ',func_get_args())).'<br>output: '.htmlentities($ind_uri));

  if ($ind_only)
    return $ind_uri;
  else return array($ind_uri,$bundle_uri);

}


function wisski_core_lod_uri_strip_chars($str) {
  return preg_replace('/[^-a-zA-Z0-9_]+/u', '_', $str);
}


function wisski_core_lod_resolve_uri($bundle = NULL) {
  
//  dpm(func_get_args(),__METHOD__);

  $uri = '';

  if (!empty($_GET['uri'])) {
    $uri = trim($_GET['uri']);
  } elseif (!empty($_POST['uri'])) {
    $uri = trim($_POST['uri']);
  }

  if (empty($uri)) {
    return t('No URI specified');
  }
  
  // we want the canonical long uri without ns prefix
  $uri = wisski_salz_ensure_long_namespace($uri);
  
  $missing_bundle = FALSE;
  if ($bundle === NULL) {
    if (!empty($_GET['bundle_name'])) {
      $bundle_name = trim($_GET['bundle_name']);
    } elseif (!empty($_POST['bundle_name'])) {
      $bundle_name = trim($_POST['bundle_name']);
    } else {
      $missing_bundle = TRUE;
    }
    if (!$missing_bundle) $bundle = entity_load_single('wisski_core_bundle',$bundle_name);
  } else {
    $bundle_name = $bundle->type;
  }
  
  $accept_line = variable_get('wisski_core_lod_default_accept_line', 'text/html');
  if (!empty($_GET['accept'])) {
    $accept_line = trim($_GET['accept']);
  } elseif (!empty($_POST['accept'])) {
    $accept_line = trim($_POST['accept']);
  } elseif (!empty($_SERVER['HTTP_ACCEPT'])) {
    $accept_line = trim($_SERVER['HTTP_ACCEPT']);
  }
  $accept = wisski_core_lod_parse_accept_header($accept_line, FALSE);
//dpm(array($accept_line, $accept), 'acceptasa');
  // walks through the set of sets of accepted mime types with descending priority
  // for each set we check if for one of the mime types a callback exists and call it,
  // otherwise we try the next type
  foreach($accept as $mime_types) {
    list($callback, $mime_type) = wisski_core_lod_callback_for_mime_type($mime_types);
    if ($callback) {
      return call_user_func($callback,$uri,$mime_type,$bundle);
    }
  }

  // If there is no handle for the mime types, we default
  // to html and issue a warning
  if (!empty($accept)) drupal_set_message(t('The requested mime types are not supported. Defaulting to HTML.'), 'warning', FALSE);
#  wisski_core_lod_accept_rdf_serialize($uri, 'application/rdf+xml', $bundle);
  wisski_core_lod_accept_text_html($uri, 'text/html', $bundle);

}

function wisski_core_lod_callback_for_mime_type($mime_types) {
  
  // we have a list of mimetypes and callbacks,
  // sorted by preference
  $types = variable_get('wisski_core_lod_accepted_mime_types',array(
    'text/html' => 'wisski_core_lod_accept_text_html',
    'application/rdf+xml' => 'wisski_core_lod_accept_rdf_serialise',
    'text/turtle' => 'wisski_core_lod_accept_rdf_serialise',
    'application/n-triples' => 'wisski_core_lod_accept_rdf_serialise',
  ));
  
  if (count($mime_types) == 1 && $mime_types[0] == '*/*') return array($types[key($types)], key($types));

  foreach ($types as $type => $callback) {
    if (in_array($type, $mime_types)) {
      return array($callback, $type);
    }
  }
  $requested = variable_get('wisski_core_lod_requested_mime_types',array());
  $requested = array_merge($requested,$mime_types);
  variable_set('wisski_core_lod_requested_mime_types',$requested);
  return array(FALSE, FALSE);
  
}

/**
 * implements wisski_core_lod_accept_MIME_TYPE
 */
function wisski_core_lod_accept_text_html($uri,$mimetype, $bundle=NULL) {

//dpm(func_get_args(),__METHOD__);
  if ($bundle === NULL) {
    
    $individual_info = wisski_salz_pb_get_entity_info($uri);
//    dpm($individual_info,'info');
    $items = array();
    $db_bundles = db_select('wisski_entity_bundles','b')
      ->fields('b',array('type','label'))
      ->condition('uri',$individual_info,'IN')
      ->execute();
    if ($db_bundles->rowCount() === 0) {
      return 'no matches found';
    }
    $last_type = NULL;
    while ($db_bundle = $db_bundles->fetchObject()) {
      $last_type = $db_bundle->type;
      $items[] = l($db_bundle->label,'wisski/get/'.$db_bundle->type,array('query'=>array('uri'=>$uri)));
    }
//    dpm($items,'items');
    
    // if there is only one bundle we redirect to that
    // otherwise we display a disambiguation page
    if ($last_type === NULL) {
      return t('No bundles found for %uri.',array('%uri'=>$uri));
    } elseif (count($items) == 1) {
      drupal_goto('wisski/get/'.$last_type,array('query'=>array('uri'=>$uri)));
    } else {
      return theme_item_list(
        array(
          'items'=>$items,
          'title'=>t('Bundles which contain %uri',array('%uri'=>$uri)),
          'type' => 'ul',
          'attributes' => array(),
        )
      );
    }

  } else {
    
    $id = wisski_core_get_id_for_uri($uri, $bundle);
    if ($id === FALSE) {
      $cache = db_select('wisski_entity_data','e')
        ->fields('e')
        ->condition('uri',$uri);
      $cache = $cache->condition('type',$bundle->type);
      $cache = $cache->execute();
//dpm($cache, 'the query');
      if ($cache->rowCount() === 0) {
//dpm($cache, '... gets no rows');
        $individual_info = wisski_salz_pb_get_entity_info($uri);
        if (!in_array($bundle->uri,$individual_info)) {
          trigger_error('The URI '.$uri.' does not exist in bundle '.$bundle->type,E_USER_WARNING);
          drupal_goto('wisski/navigate/'.$bundle->type.'/list');
        }
        $id = db_insert('wisski_entity_data')->fields(array('type'=>$bundle->type,'uri'=>$uri))->execute();
      } else {
        $id = $cache->fetchObject()->id;
      }
      $local_store_id = wisski_salz_get_local_store()->sid;
      $insertion = array(
        'entity_id' => $id,
        'bundle_name' => $bundle->type,
        'store_id' => $local_store_id,
        'uri' => $uri,
      );
      db_insert('wisski_uris')->fields($insertion)->execute();
    }
    drupal_goto('wisski/individual/'.$id);

  }
}

function wisski_core_lod_register_redirects($uris) {
  
  global $base_url;

  $uris = (array) $uris;

  foreach ((array) $uris as $uri) {

    $uri = trim(wisski_salz_ensure_long_namespace($uri), '<>');
    $suffix = str_replace($base_url . '/', '', $uri);

    
    if (empty(redirect_load_by_source($suffix))) {

      // register a redirect for the URI
      // to the generic URI lookup mechanism
      $redirect = array(
        'type' => 'redirect',
        'source' => $suffix,
        'source_options' => array(),
        'redirect' => $base_url . '/wisski/get',
        'redirect_options' => array(
          'query' => array(
            'uri' => $uri,
          ),
        ),
        'language' => 'und',
        'status_code' => 303,
      );
      
      redirect_save((object) $redirect);
//dpm(array($uris, $redirect), __METHOD__);

    }

  }

}



function wisski_core_lod_parse_accept_header($line, $keyed_by_mime = TRUE) {
  
  $mimes = array();
  $prios = array();
  $all_star = 0;

  foreach (explode("\n", $line) as $i2) {
    foreach (explode(",", $i2) as $item) {
      $fields = explode(";", $item);
      $mime = trim(array_shift($fields));
      if ($mime == '*.*') $mime = '*/*';
      $q = 1.0;
      foreach ($fields as $f) {
        list($k, $v) = explode('=', $f);
        $k = trim($k);
        if ($k = 'q') {
          $q = (float) trim($v);
        }
      }
      if ($q > $all_star && (!isset($mimes[$mime]) || $mimes[$mime] < $q)) {
        $mimes[$mime] = $q;
        if ($mime == '*/*') {
          $prios["$q"] = array('*/*');
        } else {
          if (!isset($prios["$q"])) $prios["$q"] = array();
          $prios["$q"][] = $mime;
        }
      }
    }
  }

  if ($keyed_by_mime) {
    // return the mime types sorted by priority, highest first
    arsort($mimes);
    return $mimes;
  } else {
    // return the mime types sorted by priority, highest first
//dpm($prios,'prios');
    krsort($prios);
//dpm($prios,'prios a');
    return $prios;
  }

}

/**
 * implements wisski_core_lod_accept_MIME_TYPE
 */
function wisski_core_lod_accept_rdf_serialise ($uri, $mimetype, $bundle=NULL) {
  
  // TODO: get alias names/sameAs for uri and store them into $uris
  $uris = array($uri);

  $graph = wisski_salz_all_triples($uris, FALSE);

  drupal_add_http_header('Content-Type', $mimetype . '; utf-8');
  if ($graph == FALSE) {
    drupal_add_http_header('Status', '500 Internal Server Error');
  } else {
    echo $graph->serialise($mimetype);
  }
  
  return NULL;

}

