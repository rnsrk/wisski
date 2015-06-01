<?php

/**
 * @file
 * Callbacks for viewing entities.
 */

function wisski_core_list_bundles() {
  
  module_load_include('inc','wisski_core','wisski_core.controller');
  wisski_core_tick(__FUNCTION__.' start');
  $known_classes = $unknown_classes = array();
  $counts = wisski_salz_pb_get_bundles_with_count();
  wisski_core_tick('counts');
  foreach (wisski_core_bundle_load_multiple() as $entity_type_key => $entity_type) {
    if (isset($counts[$entity_type->uri])) {
      $tag = $counts[$entity_type->uri];
      unset($counts[$entity_type->uri]);
      $label = entity_label('wisski_core_bundle', $entity_type);
      $known_classes[$entity_type_key] = l($label . ' ('.$tag.')', 'wisski/navigate/' . $entity_type_key);
    }
  }
  foreach ($counts as $uri => $count) {
    $unknown_classes[$uri] = l($uri . ' ('.$count.')', 'wisski/get',array('query'=>array('bundle_uri'=> $uri)));
  }
  wisski_core_tick('names');
//  dpm(wisski_core_tick(NULL),__FUNCTION__.' time measures');
//  dpm(array('used' => $known_classes,'unused' => $unknown_classes),__METHOD__);
  $output = array();
  if (!empty($known_classes)) {
    $output += array(
      'used_list' => array(
        '#theme' => 'item_list',
        '#items' => $known_classes,
        '#title' => t('WissKI Bundles'),
      ),
    );
  }
  if (!empty($unknown_classes)) {
    $output += array(
      'unused_list' => array(
        '#theme' => 'item_list',
        '#items' => $unknown_classes,
        '#title' => t('Unused Classes'),
      ),
    );
  }
  if (empty($output)) $output = array(
    'markup'=> array('#markup' => t('There are no individuals in this store')),
  );
  return $output +array('adder' => array('#markup' => l('Add an individual','wisski/create')));
}

function wisski_core_list_individuals($type) {

  module_load_include('inc','wisski_core','wisski_core.controller');
  $links = array();
  $limit = variable_get('wisski_max_entities_per_page',20);
  $bundle = entity_load_single('wisski_core_bundle',$type);
  $count = wisski_salz_pb_get_bundle_info($bundle->uri,TRUE);
  $page_count = ceil($count/$limit);
  $offset = pager_default_initialize($count, $limit);
  wisski_core_tick('start');
  //$chunk = _wisski_core_get_chunk($type,$page_count);
  $chunk = _wisski_core_get_sorted_chunk($type,$limit,$offset);

  wisski_core_tick('sorting');
  $short_titles = _wisski_core_check_titles($chunk,$type);
  wisski_core_tick('titles');
  if ($short_titles !== 'fail') {
    foreach ($short_titles as $uri => $title) {
      $links[] = l($title,'wisski/get/'.$type,array('query'=>array('uri'=>wisski_salz_ensure_long_namespace($uri))));
    }
  } else {
    foreach ($chunk as $uri) {
      $links[] = l($uri,'wisski/get/'.$type,array('query'=>array('uri'=>wisski_salz_ensure_long_namespace($uri))));
    }
  }
  wisski_core_tick('links');
  $output = theme('pager',array('quantity' => min(51,max(floor($page_count/7),9))));
  $output .= theme_textfield(
    array(
      'element' => array(
        '#title' => t('Find a ').$bundle->type, 
        '#default_value' => '',
        '#description' => 'autocomplete field looking for entities with given title elements',
        '#size' => 60,
        '#maxlength' => 32,
        '#required' => FALSE,
        '#attributes' => array('id' => $bundle->type),
        '#autocomplete_path' => 'wisski/autocomplete/'.$bundle->type,
      ),
    )
  );
  $output .= theme_item_list(
    array(
      'items' => $links,
      'title' => t('List of existing entities.'),
      'type' => 'ul',
      'attributes' => array(),
    )
  );
  $output .= theme('pager',array('quantity' => min(51,max(floor($page_count/17),9))));
  wisski_core_tick('theme');
  $times = wisski_core_tick(NULL);
  dpm($times,__FUNCTION__.' time measures');
//  dpm('query time: '.$times['end_query'].' secs');
  return $output;
}


function _wisski_core_get_sorted_chunk($type,$limit,$offset) {

//  dpm(array(__FUNCTION__=>func_get_args()));
  module_load_include('inc','wisski_core','wisski_core.pathbuilder');
  $bundle = entity_load_single('wisski_core_bundle',$type);
  if (!empty($bundle) && !empty($bundle->short_title_pattern)) {
    $pattern = $bundle->short_title_pattern;
    $pattern = array_expand($pattern);
    if (!empty($pattern)) {
      $elem = current($pattern);
      $paths = array();
      while (!empty($elem)) {
        $path_info = wisski_core_make_path_array(array('field_info' => array('field_name' => $elem['name'])));
        foreach ($path_info as &$field_info) {
          foreach($field_info as &$mapping_path) {
            $mapping_path['required'] = $elem['obligatory'];
            $mapping_path['maximum'] = 1;
          }
          $field_info = $mapping_path;
        }
        $paths += $path_info;
        if ($elem['obligatory']) break;
        else $elem = next($pattern);
      }
      if (!empty($paths)) {
        wisski_core_tick('start sorting');
        //pager offset is page number while SPARQL offset is first element to show
        $inds = array_keys(wisski_salz_pb_query_multi_path($bundle->uri,$paths,$limit,$limit*$offset,TRUE,TRUE,'STR'));
        wisski_core_tick('end sorting');
        if (empty($inds)) {
          throw new Exception('there are no entites matching your request');
        }
        return $inds;
      }
    }
  }
  if (!empty($bundle) && property_exists($bundle,'uri')) {
    trigger_error("Bundle $type has no short title pattern defined",E_USER_WARNING);
    return array_keys(wisski_salz_pb_query_all($bundle->uri,NULL,NULL,$limit,$limit*$offset,TRUE,TRUE,'STR'));
  }
}

function _wisski_core_check_titles($uris,$type) {
  
  if (empty($uris)) return array();
  $titles = array();
  $cache = db_select('wisski_entity_data','ent')
            ->fields('ent')
            ->condition('type',$type)
            ->condition('uri',$uris,'IN')
            ->execute()
            ->fetchAllAssoc('uri');
  $rem = array();
  $siru = array_flip($uris);
  foreach($cache as $uri => $obj) {
    if (!$obj->dirty) {
      $titles[$uri] = $obj->title;
      $rem[] = $uri;
      unset($cache[$uri]);
      unset($siru[$uri]);
    }
  }
  if (empty($siru)) return $titles;
  $uris = array_flip($siru);
  module_load_include('inc','wisski_core','wisski_core.pathbuilder');
  $bundle = entity_load_single('wisski_core_bundle',$type);
  if (!empty($bundle) && !empty($bundle->short_title_pattern)) {
    $pattern = array_expand($bundle->short_title_pattern);  
    if (!empty($pattern)) {
      $paths = array();
      $field_info = array_fill_keys($uris,array());
//dpm($pattern,__METHOD__.' Pattern');
      foreach ($pattern as $elem) {
        $path_array = wisski_core_make_path_array(array('field_info' => array('field_name' => $elem['name'])));
        foreach ($path_array as $field_array) {
          foreach($field_array as $mapping_path) {
            $mapping_path += array('id' => $elem['id'], 'required' => $elem['obligatory'], 'maximum' => $elem['number']);
            $paths[$elem['name']] = $mapping_path;
            if (!isset($starting_concept)) $starting_concept = $mapping_path['starting_concept'];
          }
          //$field_array = $mapping_path;
        }
      }
      if (!isset($starting_concept)) $starting_concept = $bundle->uri;
      $field_info = wisski_salz_pb_title_query($uris,$starting_concept,$paths);
      $insert_query = db_insert('wisski_entity_data')->fields(array('uri','type','title'));
      $invalid_title = variable_get('wisski_titles_master_fallback','-!-');
      foreach ($field_info as $uri => $info) {
        $title = wisski_core_make_short_title($info[0],$pattern);
        $title = $title?:$invalid_title.' ('.$uri.')';
        $titles[$uri] = $title;
        if (isset($cache[$uri])) {
          db_update('wisski_entity_data')
            ->fields(array('title' => $title, 'dirty' => 0))
            ->condition('uri',$uri)
            ->condition('type',$type)
            ->execute();
        } else {
          $insert_query->values(array(
            'uri' => $uri,
            'type' => $type,
            'title' => $title,
          ));
        }
      }
      try {
        $num = $insert_query->execute();
      } catch (Exception $e) {
        trigger_error('Problems when inserting entities '.$e->getClass().' '.$e->getMessage(),E_USER_WARNING);
      }
      return $titles;
    }
  }
  return 'fail';
}

function _wisski_core_get_items($type,$count_only = FALSE) {
 
  $offset = arg(3)?:0;
//  dpm($offset); 
  if ($count_only) {
    $bundle = entity_load_single('wisski_core_bundle',$type);
    return wisski_salz_pb_get_bundle_info($bundle->uri,TRUE);
  }
/*  $cache_name = 'wisski_cache_'.$type.'_'.$offset;
  if ($cache = cache_get($cache_name)) {
    $items = $cache->data;
  }	*/
  if (!isset($items)) {
    $items = array();
    $now = time();
    $bundle = entity_load_single('wisski_core_bundle',$type);
    $inds = wisski_salz_pb_get_bundle_info($bundle->uri);
    $num_inds = count($inds);
    if ($inds !== FALSE) {
      dpm("Query took ".(time() - $now)." seconds");
      $limit = variable_get('wisski_max_entities_per_page',20);
      if ($offset == 1) $items['<<< '.t('first').' '.$limit] = 'wisski/navigate/'.$type.'/'.($offset - 1);
      elseif ($offset > 1) $items['<<< '.t('previous').' '.$limit] = 'wisski/navigate/'.$type.'/'.($offset - 1);
      $chunks = array_chunk($inds,$limit,TRUE);
      $short_titles = db_select('wisski_entity_title_cache','tit')
                        ->fields('tit')
                        ->execute()
                        ->fetchAllAssoc('uri');
      foreach($chunks[$offset] as $ind_uri) {
        $entity_title = isset($short_titles[$ind_uri]) ? $short_titles[$ind_uri]->short_title.' ('.$ind_uri.')' : $ind_uri;
        $items[$entity_title] = 'wisski_core/'.$type.'/'.$ind_uri;
      }
      $offset++;
      if (isset($chunks[$offset])) {
        if (count($chunks[$offset]) == $limit) $items[t('next').' '.$limit.' >>>'] = 'wisski/navigate/'.$type.'/'.($offset);
        else $items[t('last').' '.count($chunks[$offset]).' >>>'] = 'wisski/navigate/'.$type.'/'.($offset);
      }
      
    }
//    cache_set($cache_name,$items);
    dpm("There are ".$num_inds." entities in bundle $bundle->label");
  }
  $links = array();
  foreach ($items as $label => $uri) {
    $links[] = l($label,$uri);
  }
  return $links;
}

/**
 * Entity view callback.
 * 
 * @param object $entity
 *   Entity to view.
 * 
 * @return array
 *   Renderable array.
 */

function wisski_core_individual_view($entity) {
//dpm($entity,__METHOD__);
if (empty($entity)) ddebug_backtrace($entity);
//  watchdog('wisski_view_start',$my_count);
    if (is_array($entity)) {
      $array = $entity;
      $entity = current($entity);
    } else {
      $array = array(entity_id('wisski_individual', $entity) => $entity);
    }

  include_once('wisski_core.lod.inc');
  wisski_core_lod_register_redirects($entity->uri);

    $accept = $_SERVER['HTTP_ACCEPT'];
//dpm(array($entity->uri, $accept), 'accept the truth, honk');

//    dpm(entity_get_controller('wisski_individual'));
    entity_get_controller('wisski_individual')->resetCache(array($entity->id));

    drupal_set_title(entity_label('wisski_individual', $entity));
    $entity_view = entity_view('wisski_individual', $array);
    return $entity_view;
//  }
//  watchdog('wisski_view_end',$my_count);

}

function wisski_core_individual_resolve($entity) {
  
//  drupal_set_message(t('You\'ve been redirected from wisski/individual/@id',array('id'=>$entity->id)));
  drupal_goto('wisski/individual/'.$entity->id.'/view');
}

function _wisski_core_autocomplete($bundle,$search_string) {

  dpm(func_get_args(),__FUNCTION__);
  $matches = array();
  $search_string = preg_replace('/^(\"|\')*|(\"|\')*$/','',$search_string);
  $search_string = preg_replace('/\"/','\\"',$search_string);
  $search_string = preg_replace('/\'/','\\\'',$search_string);
  $search_string = preg_replace('/\%/','\\\%',$search_string);
  $search_string = preg_replace('/\_/','\\\_',$search_string);
  $or = db_or()->condition('title','%'.$search_string.'%','LIKE')
            ->condition('uri','"%'.$search_string.'%"','LIKE');
  $result = db_select('wisski_entity_data','w')
              ->fields('w',array('id','title'))
              ->condition('type',$bundle->type)
              ->condition($or)
              ->condition('dirty',0)
              ->range(0,10)
              ->execute();
  while ($obj = $result->fetchObject()) {
    $matches[$obj->id] = $obj->title;
  }
  dpm($matches,__FUNCTION__.' intermediate result');
  if (count($matches) < 10) {
    $ents = wisski_salz_pb_get_matching_individuals($search_string,10-count($matches),$bundle->uri);
    foreach($ents[$bundle->uri] as $uri) {
      $entity = entity_load_single('wisski_individual',$uri);
      $matches[$entity->id] = entity_label('wisski_individual',$entity);
    }
    dpm($matches,__FUNCTION__.' full result');
  }
  drupal_json_output($matches);
}
//</htdocs/dev7/sites/all/modules/wisski/wisski_core>

function wisski_core_bundle_sort($bundle1,$bundle2) {

  $b1 = explode(':',$bundle1,2);
  $b2 = explode(':',$bundle2,2);
  if (count($b1) > count($b2)) {
    return 1;
  }
  if (count($b1) < count($b2)) {
    return -1;
  }
  $comp = strnatcasecmp($b1[0],$b2[0]);
//dpm(array($b1,$b2,$comp),__FUNCTION__);
  if (count($b1) === 1 || $comp !== 0) {
    return $comp;
  }
  return strnatcasecmp($b1[1],$b2[1]);
}