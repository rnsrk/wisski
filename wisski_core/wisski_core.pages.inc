<?php

/**
 * @file
 * Callbacks for viewing entities.
 */

function wisski_core_list_bundles() {
  
  module_load_include('inc','wisski_core','wisski_core.controller');
  wisski_core_tick(__FUNCTION__);
  $items = array();
  $counts = wisski_salz_pb_get_bundles_with_count();
  wisski_core_tick(__FUNCTION__.' counts');
  dpm($counts);
  foreach (wisski_core_bundle_load_multiple() as $entity_type_key => $entity_type) {
    $bundle = entity_load_single('wisski_core_bundle',$entity_type_key);
    $tag = isset($counts[$bundle->uri])?$counts[$bundle->uri]:FALSE;
//    if (!is_int($tag)) dpm($tag);
    if ($tag) {
      $items[] = l(entity_label('wisski_core_bundle', $entity_type).' ('.$tag.')', 'wisski/navigate/' . $entity_type_key);
    }
  }
  wisski_core_tick(__FUNCTION__.' names');
  natsort($items);
  wisski_core_tick(__FUNCTION__.' sort');
  dpm(wisski_core_tick(NULL));
  return array(
    'list' => array(
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => t('Choose type of entity to view.'),
    ),
  );
}


/**
 * Show list of existing entities.
 */
/*
function wisski_core_list($type) {
  $now = time();
  $output = '<br/>';
  $output .= theme_item_list(
    array(
      'items' => _wisski_core_get_items($type),
      'title' => t('List of existing entities.'),
      'type' => 'ul',
      'attributes' => array(),
    )
  );
  $limit = variable_get('wisski_max_entities_per_page',20);
  dpm($limit);
  $num = _wisski_core_get_items($type,TRUE);
  dpm($num);
  $options = array('default' => '- choose -');
  $i = 0;
  while ($i * $limit < $num - $limit) {
    $options['wisski/navigate/'.$type.'/'.($i)] = ($i*$limit + 1).' - '.(($i+1)*$limit);
    $i++;
  }
  $options['wisski/navigate/'.$type.'/'.($i)] = ($i*$limit + 1). ' - '.$num;
  $output .= theme_select(
    array(
      'element' => array(
        '#name' => 'choice',
        '#type' => 'select',
        '#options' => $options,
        '#title' => t('View entities'),
        '#default_value' => 'default',
      ),
    )
  );
  $output .= theme_button(
    array(
      'element' => array(
        '#button_type' => 'submit',
        '#submit' => 'wisski_core_list_submit',
        '#value' => t('Go'),
      ),
    )
  );
  $time = time() - $now;
  dpm("Loading took $time seconds");
  return $output;
}

function wisski_core_list_submit($form,&$form_state) {
  dpm($form_state['values']);
  $form_state['redirect'] = $form_state['values']['choice'];
}

*/
function wisski_core_list($type) {

  module_load_include('inc','wisski_core','wisski_core.controller');
  $links = array();
  $page_count = 0;
  $limit = variable_get('wisski_max_entities_per_page',20);
  $bundle = entity_load_single('wisski_core_bundle',$type);
  $count = wisski_salz_pb_get_bundle_info($bundle->uri,TRUE);
  $offset = pager_default_initialize($count, $limit);
  wisski_core_tick('start');
  //$chunk = _wisski_core_get_chunk($type,$page_count);
  $chunk = _wisski_core_get_sorted_chunk($type,$limit,$offset);
  wisski_core_tick('sorting');
  $short_titles = _wisski_core_check_titles($chunk,$type);
  wisski_core_tick('titles');
  if ($short_titles !== 'fail') {
    foreach ($short_titles as $uri => $title) {
      $links[] = l($title,'wisski_core/'.$type.'/'.$uri);
    }
  } else {
    foreach ($chunk as $uri) {
      $links[] = l($uri,'wisski_core/'.$type.'/'.$uri);
    }
  }
  wisski_core_tick('links');
  $output = theme('pager',array('quantity' => min(51,max(floor($page_count/17),9))));
  $output .= theme_item_list(
    array(
      'items' => $links,
      'title' => t('List of existing entities.'),
      'type' => 'ul',
      'attributes' => array(),
    )
  );
  
  wisski_core_tick('theme');
  $times = wisski_core_tick(NULL);
  dpm($times);
//  dpm('query time: '.$times['end_query'].' secs');
  return $output;
}

function _wisski_core_get_chunk($type,&$page_count) {

  $limit = variable_get('wisski_max_entities_per_page',20);
  if ($cache = cache_get('wisski_entity_list_'.$type.'_count')) {
    $count = $cache->data;
    $page_count = floor($count/$limit);
    $offset = pager_default_initialize($count, $limit);
  } else {
    dpm(__FILE__.' ('.__LINE__.'): make new');
    $bundle = entity_load_single('wisski_core_bundle',$type);
    $inds = wisski_salz_pb_get_bundle_info($bundle->uri);
//    $inds = _wisski_core_sort_titles_stupid($type,$inds);
    $count = count($inds);
    $page_count = floor($count/$limit);
    cache_set('wisski_entity_list_'.$type.'_count',$count);
    $offset = pager_default_initialize($count, $limit);
    $chunks = array_chunk($inds,$limit);
    foreach ($chunks as $pos => $chunk) {
      cache_set('wisski_entity_list_'.$type.'_'.$pos,$chunk);
    }
  }
/*  $checked = _wisski_core_check_title_stubs($count,$type,$offset,$limit);
  if ($checked !== 'fail') return $checked;
  else*/if (isset($chunks)) return $chunks[$offset];
  else {
    return cache_get('wisski_entity_list_'.$type.'_'.$offset)->data;
  }
}

function _wisski_core_get_sorted_chunk($type,$limit,$offset) {

/*  if ($position !== 0) {
    dpm(func_get_args());
    throw new Exception('MUHAHAHAHAHAHA');
    return $uris;
  }*/
  module_load_include('inc','wisski_core','wisski_core.pathbuilder');
  $bundle = entity_load_single('wisski_core_bundle',$type);
  if (!empty($bundle) && !empty($bundle->short_title_pattern)) {
    $pattern = $bundle->short_title_pattern;
    $pattern = array_expand($pattern);
    if (!empty($pattern)) {
      $elem = current($pattern);
      $paths = array();
      while (!empty($elem)) {
        $instance_id = $elem['id'];
        list($starting_concept,$path_array,$datatype_property) = wisski_core_make_path_array($instance_id);
        $paths[] = array('path_array' => $path_array,'datatype_property' => $datatype_property,'optional' => !$elem['obligatory']);
        if ($elem['obligatory']) break;
        else $elem = next($pattern);
      }
      if (!empty($paths)) {
        dpm($paths);
        wisski_core_tick('start sorting');
        //pager offset is page number while SPARQL offset is first element to show
        $inds = array_keys(wisski_salz_pb_query_multi_path($starting_concept,$paths,$limit,$limit*$offset,TRUE,TRUE,'STR'));
        wisski_core_tick('end sorting');
        if (empty($inds)) {
          throw new Exception('');
        }
        return $inds;
      }
    }
  }
  $inds = wisski_salz_pb_query_all($result['uri'],NULL,NULL,$limit,$limit*$offset,TRUE,TRUE,'STR');
  return $inds;
}

function _wisski_core_check_title_stubs($count,$type,$offset,$limit) {

  module_load_include('inc','wisski_core','wisski_core.pathbuilder');
  $result = db_select('wisski_entity_bundles','bund')
            ->fields('bund',array('type','short_title_pattern'))
            ->condition('type',$type)
            ->execute()
            ->fetchAssoc();
  if (!empty($result) && $result['short_title_pattern']) {
    $pattern = unserialize($result['short_title_pattern']);
    if ($pattern) {
      if ($cache = cache_get('wisski_entity_list_'.$type.'_index')) {
        list($index,$positions) = $cache->data;
      } else {
        $index = array();
        $positions = array();
      }
      $right = current($index);
      while ($right !== FALSE && $right < $offset*$limit+$limit-1) {
        $right = next($index);
      }
      if ($right === FALSE) $right = $count;
      if (!isset($positions[$right])) $positions[$right] = 1;
      $last_chunk = floor($right / $limit);
      $left = end($index);
      while ($left !== FALSE && $left > $offset*$limit) {
        $left = prev($index);
      }
      if ($left === FALSE) $left = 0;
      if (!isset($positions[$left])) $positions[$left] = 1;
      $first_chunk = floor($left / $limit);
      if ($positions[$left] === -1) $position = $positions[$right];
      elseif ($positions[$right] === -1) $position = $positions[$left];
      else $position = min($positions[$left],$positions[$right]);
      if (!in_array($left,$index)) $index[] = $left;
      if (!in_array($right,$index)) $index[] = $right;
      dpm(array(
        'offset' => $offset,
        'index' => $index,
        'positions' => $positions,
        'left' => $left,
        'right' => $right,
        'first_chunk' => $first_chunk,
        'last_chunk' => $last_chunk,
        'position' => $position,
      ));
      if ($position === -1) {
        //the area is fully sorted
        if ($cache = cache_get('wisski_entity_list_'.$type.'_'.$offset)) return $cache->data;
        else return 'fail';
      }
      trigger_error('position != -1',E_USER_NOTICE);
      $pattern = array_expand($pattern);
      //dpm(array('pattern' => $pattern));
      $pos = 0;
      while ($pos < $position) {
        $elem = array_shift($pattern);
        $pos++;
      }
      //dpm(array('elem' => $elem));
      if (empty($elem)) {
        $position = -1;
      } else {
        $position++;
      }
      $positions[$left] = $position;
      $positions[$right] = $position;
      if ($position === -1) {
        //the area is fully sorted, but this is not known in the index
        for($i = $left; $i <= $right; $i++) {
          if (isset($positions[$i])) $positions[$i] = -1;
        }
        cache_set('wisski_entity_list_'.$type.'_index',array($index,$positions));
        if ($cache = cache_get('wisski_entity_list_'.$type.'_'.$offset)) return $cache->data;
        else return 'fail';
      }
      trigger_error('still position != -1',E_USER_NOTICE);
      $instance_id = $elem['id'];
      $field_path = db_select('wisski_pb_fielddata','con')
                      ->fields('con')
                      ->condition('instance_id',$instance_id)
                      ->execute()
                      ->fetchAssoc('instance_id');
      if (!empty($field_path)) {
        trigger_error('path not empty',E_USER_NOTICE);
        $path_id = $field_path['path_id'];
        $path_info = db_select('wisski_pb_pathdata','path')
                      ->fields('path')
                      ->condition('pending',0)
                      ->execute()
                      ->fetchAllAssoc('uuid');
        if (!empty($path_info[$path_id])) {
          trigger_error('path still not empty',E_USER_NOTICE);
          $path = $path_info[$path_id];
          $starting_concept = $path->starting_concept;
          $full_path = array();
          do {
            $full_path = array_merge($full_path,unserialize($path->path_array));
            $data_prop = $path->datatype_property;
          } while(isset($path_info[$path->external_path]) && $path = $path_info[$path->external_path]);
//          $uris = array();
//          $head = array();
//          $tail = array();
          list($uris,$head,$tail) = _wisski_core_load_all_chunks($type,$limit,$left,$right,$first_chunk,$last_chunk);
          $info = wisski_salz_pb_query($uris,$starting_concept,$full_path,$data_prop,TRUE);
          /*dpm(array(
            'info' => $info,
            'head' => $head,
            'tail' => $tail,
          ));*/
          if ($info === FALSE) {
            dpm(array($uris,$starting_concept,$full_path,$data_prop,TRUE));
            throw new Exception();
          }
          uasort($info,'strnatcasecmp');
          $ind = $left;
          $current_data = NULL;
          foreach ($info as $uri => $data) {
            if (empty($current_data)) $current_data = $data;
            if ($current_data !== $data) {
              $index[] = $ind;
              $positions[$ind] = $position;
              $current_data = $data;
            }
            $ind++;
          }
          $index = array_unique($index);
          sort($index);
          dpm(array(
            'head' => $head,
            'info' => $info,
            'tail' => $tail,
            'index' => $index,
          ));
          cache_set('wisski_entity_list_'.$type.'_index',array($index,$positions));
          $field_info = array_merge($head,array_keys($info),$tail);
          $chunks = array_chunk($field_info,$limit);
          foreach ($chunks as $pos => $chunk) {
            cache_set('wisski_entity_list_'.$type.'_'.($first_chunk + $pos),$chunk);
          }
          return _wisski_core_check_title_stubs($count,$type,$offset,$limit);
        }
      }
    }
  }
  return 'fail';
}

function array_append($array1, $array2) {

  return array_combine(array_merge(array_keys($array1),array_keys($array2)),array_merge(array_values($array1),array_values($array2)));
}

function _wisski_core_load_all_chunks($type,$limit,$left,$right,$first_chunk,$last_chunk) {

  $uris = array();
  $act_chunk = $first_chunk;
  while ($act_chunk <= $last_chunk) {
    if ($cache = cache_get('wisski_entity_list_'.$type.'_'.$act_chunk)) {
      $added_uris = $cache->data;
      if ($act_chunk == $first_chunk) {
        $head = array_slice($added_uris,0,($left % $limit));
        $added_uris = array_slice($added_uris,($left % $limit));
      } 
      if ($act_chunk == $last_chunk) {
        $added_uris = array_slice($added_uris,0,($right % $limit));
        $tail = array_slice($added_uris,($right % $limit));
      }
    }
    $uris = array_merge($uris,$added_uris);
    $act_chunk++;
  }
  if (!isset($tail)) {
    dpm($act_chunk);
    dpm(func_get_args());
    return array($uris,$head,array());
  }
  return array($uris,$head,$tail);
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
  foreach($cache as $uri => $obj) {
    if (!$obj->dirty) {
      $titles[$uri] = $obj->title;
      $rem[] = $uri;
      unset($cache[$uri]);
    }
  }
  $uris = array_diff($uris,$rem);
  if (empty($uris)) return $titles;
  module_load_include('inc','wisski_core','wisski_core.pathbuilder');
  $bundle = entity_load_single('wisski_core_bundle',$type);
  if (!empty($bundle) && !empty($bundle->short_title_pattern)) {
    $pattern = array_expand($bundle->short_title_pattern);  
    if (!empty($pattern)) {
      $paths = array();
      $field_info = array_fill_keys($uris,array());
      foreach ($pattern as $elem) {
        $instance_id = $elem['id'];
        list($starting_concept,$full_path,$data_prop) = wisski_core_make_path_array($instance_id);
        $paths[] = array('instance_id' => $instance_id, 'path_array' => $full_path, 'datatype_property' => $data_prop, 'optional' => !$elem['obligatory']);
      }
      $field_info = wisski_salz_pb_title_query($uris,$starting_concept,$paths);
      $insert_query = db_insert('wisski_entity_data')->fields(array('uri','type','title'));
      $invalid_title = variable_get('wisski_titles_master_fallback','-!-');
      foreach ($field_info as $uri => $info) {
        $title = wisski_core_make_short_title($info,$pattern);
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
function wisski_core_entity_view($entity) {

  $wisski_semaphore = &drupal_static(__FUNCTION__);
  if (isset($wisski_semaphore) && $wisski_semaphore) {
    dpm(__FILE__.'('.__LINE__.')'."tick");
    return;
  }
  $wisski_semaphore = TRUE;
  if (is_array($entity)) {
    $array = $entity;
    $entity = current($entity);
  } else {
    $array = array(entity_id('wisski_core_entity', $entity) => $entity);
  }
  drupal_set_title(entity_label('wisski_core_entity', $entity));
  $entity_view = entity_view('wisski_core_entity', $array);
  $wisski_semaphore = FALSE;
  return $entity_view;
}
