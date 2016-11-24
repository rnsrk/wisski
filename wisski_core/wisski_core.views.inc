<?php

function wisski_core_views_data() {

/*
  $data = array();
  $entity_info = entity_get_info();
#  drupal_set_message("ei: " . serialize($entity_info));

  foreach (entity_views_data() as $key => $entity_data) {
#    drupal_set_message("key: " . serialize($key));
    if ($key == 'entity__global') {
      continue;
    }
    
    
    if (_wisski_core_check_individual($data, $entity_type, $key, $entity_data)) {
#      drupal_set_message("et: " . serialize($entity_type));
      
      $metadata = entity_get_property_info($entity_type);
#      drupal_set_message("md: " . serialize($metadata));

      $fields = array();
      // Collect the fields.
      foreach (field_info_instances($entity_type) as $field_data) {
        $fields += array_flip(array_keys($field_data));
      }
      
#      drupal_set_message("fields: " . serialize($fields));
      
      $info = $entity_info[$entity_type];
      foreach ($data['wki_' . $entity_type] as $index => &$views_data) {
     #   unset($views_data['relationship']);
        if (isset($fields[$index]) || isset($views_data['real field'])) {
#          _efq_views_get_field_handlers($views_data, $index);
        }
        else {
#          $move = _efq_views_get_property_handlers($views_data, $index, $metadata, $info);
          if ($move) {
#            $data['wki_' . $entity_type][$move] = $views_data;
#            unset($data['wki_' . $entity_type][$index]);
          }
        }
      }
      
#      drupal_set_mes
      
      $data['wki_' . $entity_type]['table']['entity type'] = $entity_type;
      $data['wki_' . $entity_type]['table']['base'] = array(
        'title' => 'WissKIFieldQuery: ' . $info['label'],
        'help' => t('Uses EntityFieldQuery for querying the !type entity type', array('!type' => $entity_type)),
        'query class' => 'wki_query',
        'field' => $info['entity keys']['id'],
      );
#      $data['wki_' . $entity_type] += _wisski_core_views_get_entity_data($entity_type, $info);
    
    }
  }

#  drupal_set_message("resulting in: " . serialize($data));
  
  return $data;
*/
}

/**
 * Check if it is a wisski_individual.
 *
 * @author: Mark Fichtner
 * @param array $data
 * @param string $entity_type
 * @param string $key
 * @param array $entity_data
 *
 * @return bool
 */
function _wisski_core_check_individual(&$data, &$entity_type, $key, $entity_data) {
  $return = FALSE;

  // is it an entity beginning with wisski-something?  
  if(substr($key,0,13) == 'entity_wisski') {
    $return = TRUE;
    // return the entity type which includes the wisski
    $entity_type = substr($key, 7);
  }
  elseif (substr($key, 0,19) == 'views_entity_wisski') {
    $entity_type = substr($key, 13);
  }
  else { // if we didn't find anything, we don't have to do something with it.
    return $return;
  }
  
  if(!isset($data['wki_' . $entity_type])) {
    $data['wki_' . $entity_type] = array();
  }
  
  $data['wki_' . $entity_type] += $entity_data;
  
  return $return;
  
}

/**
 * Helper like efq_views_views_data()
 * Returns all possible entity metadata variants
 * (entity_id, revision_id, entity_type, bundle).
 *
 * @param string $entity_type The selected entity type, if any.
 * @param array $info Entity info array.
 */
function _wisski_core_views_get_entity_data($entity_type, $info) {
  $data['entity_id'] = array(
    'group' => t('Entity'),
    'title' => t('Entity ID'),
    'help' => t('The entity ID of the entity.'),
    'field' => array(
      'click sortable' => TRUE,
      'handler' => 'efq_views_handler_field_entity',
    ),
    'filter' => array(
      'handler' => 'efq_views_handler_filter_entity_integer',
    ),
    'sort' => array(
      'handler' => 'efq_views_handler_sort_entity',
    ),
    'argument' => array(
     'handler' => 'efq_views_handler_argument_entity_integer',
    ),
  );
  $data['revision_id'] = array(
    'group' => t('Entity'),
    'title' => t('Revision ID'),
    'help' => t('The revision ID of the entity revision.'),
    'field' => array(
      'click sortable' => TRUE,
      'handler' => 'efq_views_handler_field_entity',
    ),
    'filter' => array(
      'handler' => 'efq_views_handler_filter_entity_integer',
    ),
    'sort' => array(
      'handler' => 'efq_views_handler_sort_entity',
    ),
    'argument' => array(
     'handler' => 'efq_views_handler_argument_entity_integer',
    ),
  );
  if ($entity_type != 'comment' && $entity_type != 'taxonomy_term') {
    $data['bundle'] = array(
      'group' => t('Entity'),
      'title' => t('Bundle'),
      'help' => t('The entity bundle (for example, "article", "page", etc for nodes).'),
      'field' => array(
        'click sortable' => TRUE,
        'handler' => 'efq_views_handler_field_entity',
      ),
      'filter' => array(
        'entity_type' => $entity_type,
        'handler' => 'efq_views_handler_filter_entity_bundle',
      ),
      'sort' => array(
        'handler' => 'efq_views_handler_sort_entity',
      ),
      'argument' => array(
       'handler' => 'efq_views_handler_argument_entity_bundle',
      ),
    );
  }

  $data['bundle_label'] = array(
    'group' => t('Entity'),
    'title' => t('Bundle label'),
    'help' => t('The entity bundle label (for example, "Article", "Basic page", etc for nodes).'),
    'field' => array(
      'click sortable' => FALSE,
      'handler' => 'efq_views_handler_field_entity',
    ),
  );

  $data['label'] = array(
    'group' => t('Entity'),
    'title' => t('Label'),
    'help' => t('The entity label (for example, node title for nodes).'),
    'field' => array(
      'click sortable' => FALSE,
      'handler' => 'efq_views_handler_field_entity_label',
    ),
  );
  // We can only do click sorting and filters/sorts/arguments when the label is
  // stored in the database, instead of being generated in a callback. As
  // labels are not supported by entityCondition/entityOrderBy, treat them as
  // property for filtering and sorting.
  if (isset($info['entity keys']['label']) && !isset($info['label callback'])) {
    $data['label']['field'] = array(
      'click sortable' => TRUE,
      'handler' => 'efq_views_handler_field_entity_label',
      'label column' => $info['entity keys']['label'],
    );
    $data['label']['filter'] = array(
      'handler' => 'efq_views_handler_filter_property_string',
      'field' => $info['entity keys']['label'],
    );
    $data['label']['sort'] = array(
      'handler' => 'efq_views_handler_sort_property',
      'field' => $info['entity keys']['label'],
    );
    $data['label']['argument'] = array(
      'handler' => 'efq_views_handler_argument_property_string',
      'field' => $info['entity keys']['label'],
    );
  }

  return $data;
}
