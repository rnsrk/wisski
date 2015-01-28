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

  
  $chunk = _wisski_core_get_chunk($type);
  $short_titles = db_select('wisski_entity_title_cache','tit')
                    ->fields('tit')
                    ->execute()
                    ->fetchAllAssoc('uri');
  $links = array();
  foreach ($chunk as $uri) {
    if (isset($short_titles[$uri])) {
      $title = $short_titles[$uri]->short_title ?:$uri;
    } else {
      $title = $uri;
    }
    $links[] = l($title,'wisski_core/'.$type.'/'.$uri);
  }
  $output = theme_item_list(
    array(
      'items' => $links,
      'title' => t('List of existing entities.'),
      'type' => 'ul',
      'attributes' => array(),
    )
  );
  $output .= theme('pager',array('quantity' => 21));
  return $output;
}

function _wisski_core_get_chunk($type) {

  $count_cache_name = 'wisski_entity_list_'.$type.'_count';
  $limit = variable_get('wisski_max_entities_per_page',20);
  if ($cache = cache_get($count_cache_name)) {
    $count = $cache->data;
    $offset = pager_default_initialize($count, $limit);
    if ($cache = cache_get('wisski_entity_list_'.$type.'_'.$offset)) {
      return $cache->data;
    }
  } 
  dpm("make new");
  $bundle = entity_load_single('wisski_core_bundle',$type);
  $inds = wisski_salz_pb_get_bundle_info($bundle->uri);
  cache_set('wisski_entity_list_'.$type.'_count',count($inds));
  $offset = pager_default_initialize(count($inds), $limit);
  $chunks = array_chunk($inds,$limit);
  foreach ($chunks as $pos => $chunk) {
    cache_set('wisski_entity_list_'.$type.'_'.$pos,$chunk);
  }
  return $chunks[$offset];
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
