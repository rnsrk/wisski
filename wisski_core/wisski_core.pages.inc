<?php

/**
 * @file
 * Callbacks for viewing entities.
 */

function wisski_core_list_bundles() {

  if ($cache = cache_get('wisski_core_list')) {
    dpm("Loaded from cache");
    $items = $cache->data;
  } else {
    $items = array();
    foreach (wisski_core_bundle_load_multiple() as $entity_type_key => $entity_type) {
      $tag = _wisski_core_get_items($entity_type->type,TRUE);
      if (!is_int($tag)) dpm($tag);
      if ($tag) {
        $items[] = l(entity_label('wisski_core_bundle', $entity_type).' ('.$tag.')', 'wisski/navigate/' . $entity_type_key);
      }
    }
    cache_set('wisski_core_list',$items);
  }
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
  
  $cache_name = 'wisski_cache_'.$type;
  if ($cache = cache_get($cache_name)) {
    $items = $cache->data;
  } else {
    $items = array();
    $bundle = entity_load_single('wisski_core_bundle',$type);
    $now = time();
    $inds = wisski_salz_pb_get_bundle_info($bundle->uri);
    if ($inds !== FALSE) {
      if ($count_only) {
        return count($inds);
      }
      dpm("Query took ".(time() - $now)." seconds");
      foreach($inds as $ind_uri => $comments) {
        $entity_title = $ind_uri;
        if ($comments) {
          foreach ($comments as $comment) {
            $split = explode(':',preg_replace('/(^\")|(\"$)/','',$comment));
            if ($split[0] == 'wisski_title' && isset($split[1])) $entity_title = $split[1];
          }
        }
        $items[$entity_title] = 'wisski_core/'.$type.'/'.$ind_uri;
      }
    }
    cache_set($cache_name,$items,CACHE_TEMPORARY);
    dpm("There are ".count($inds)." entities in bundle $bundle->label");
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
