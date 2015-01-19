<?php

/**
 * @file
 * Callbacks for viewing entities.
 */

function wisski_core_list_bundles() {

  $items = array();
  foreach (wisski_core_bundle_load_multiple() as $entity_type_key => $entity_type) {
    $tag = _wisski_core_get_items($entity_type->type,TRUE);
    if (!is_int($tag)) dpm($tag);
    if ($tag) {
      $items[] = l(entity_label('wisski_core_bundle', $entity_type).' ('.$tag.')', 'wisski/navigate/' . $entity_type_key);
    }
  }
  natsort($items);
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
  $time = time() - $now;
  dpm("Loading took $time seconds");
  return $output;
}

function _wisski_core_get_items($type,$count_only = FALSE) {
 
  $offset = arg(3)?:0;
//  dpm($offset); 
  $cache_name = 'wisski_cache_'.$type.'_'.$offset;
  if ($cache = cache_get($cache_name)) {
    list($items,$count) = $cache->data;
    if ($count_only) return $count;
  }
  if (!isset($items)) {
    $items = array();
    $bundle = entity_load_single('wisski_core_bundle',$type);
    $now = time();
    $inds = wisski_salz_pb_get_bundle_info($bundle->uri);
    $num_inds = count($inds);
    if ($inds !== FALSE) {
      if ($count_only) {
        cache_set($cache_name,array(NULL,$num_inds));
        return $num_inds;
      }
      dpm("Query took ".(time() - $now)." seconds");
      $limit = variable_get('wisski_max_entities_per_page',20);
      if ($offset == 1) $items['<<< '.t('first').' '.$limit] = 'wisski/navigate/'.$type.'/'.($offset - 1);
      elseif ($offset > 1) $items['<<< '.t('previous').' '.$limit] = 'wisski/navigate/'.$type.'/'.($offset - 1);
      $chunks = array_chunk($inds,$limit,TRUE);
      foreach($chunks[$offset] as $ind_uri => $comments) {
          
        $entity_title = $ind_uri;
        if ($comments) {
          foreach ($comments as $comment) {
            $split = explode(':',preg_replace('/(^\")|(\"$)/','',$comment));
            if ($split[0] == 'wisski_title' && isset($split[1])) $entity_title = $split[1];
          }
        }
        $items[$entity_title] = 'wisski_core/'.$type.'/'.$ind_uri;
      }
      $offset++;
      if (isset($chunks[$offset])) {
        if (count($chunks[$offset]) == $limit) $items[t('next').' '.$limit.' >>>'] = 'wisski/navigate/'.$type.'/'.($offset);
        else $items[t('last').' '.count($chunks[$offset]).' >>>'] = 'wisski/navigate/'.$type.'/'.($offset);
      }
    }
    cache_set($cache_name,array($items,$num_inds));
    dpm("There are ".$num_inds." entities in bundle $bundle->label");
  }
  if ($count_only) {
    return $items ? count($items) : 0;
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
