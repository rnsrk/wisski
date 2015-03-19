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
  foreach (wisski_core_bundle_load_multiple() as $entity_type_key => $entity_type) {
    $tag = isset($counts[$entity_type->uri]) ? $counts[$entity_type->uri] : FALSE;
    if ($tag) {
      $items[$entity_type_key] = l(entity_label('wisski_core_bundle', $entity_type).' ('.$tag.')', 'wisski/navigate/' . $entity_type_key);
    }
  }
  wisski_core_tick(__FUNCTION__.' names');
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
      $links[] = l($title,'wisski_core/'.$type.'/'.$uri);
    }
  } else {
    foreach ($chunk as $uri) {
      $links[] = l($uri,'wisski_core/'.$type.'/'.$uri);
    }
  }
  wisski_core_tick('links');
  $output = theme('pager',array('quantity' => min(51,max(floor($page_count/7),9))));
  $output .= theme_textfield(
    array(
      'element' => array(
        '#title' => t('Find a ').$bundle->label, 
        '#value' => '',
        '#description' => 'autocomplete field looking for entities with given title elements',
        '#size' => 60,
        '#maxlength' => 32,
        '#required' => FALSE,
        '#attributes' => NULL,
        '#autocomplete_path' => FALSE,
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
  if (isset($chunks)) return $chunks[$offset];
  else {
    return cache_get('wisski_entity_list_'.$type.'_'.$offset)->data;
  }
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
      foreach ($pattern as $elem) {
        $path_array = wisski_core_make_path_array(array('field_info' => array('field_name' => $elem['name'])));
        foreach ($path_array as &$field_array) {
          foreach($field_array as &$mapping_path) {
            $mapping_path += array('id' => $elem['id'], 'required' => $elem['obligatory'], 'maximum' => $elem['number']);
            if (!isset($starting_concept)) $starting_concept = $mapping_path['starting_concept'];
          }
          $field_array = $mapping_path;
        }
      }
      if (!isset($starting_concept)) $starting_concept = $bundle->uri;
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
  
  $count = &drupal_static(__FUNCTION__);;
  if (!isset($count)) $count = 0;
  $count++;
  $my_count = $count;
  watchdog('wisski_view_start',$my_count);
  if ($count <= 1) {
    if (is_array($entity)) {
      $array = $entity;
      $entity = current($entity);
    } else {
      $array = array(entity_id('wisski_individual', $entity) => $entity);
    }
    drupal_set_title(entity_label('wisski_individual', $entity));
    $entity_view = entity_view('wisski_individual', $array);
    return $entity_view;
  }
  watchdog('wisski_view_end',$my_count);
}
