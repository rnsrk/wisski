<?php

/**
 * re-implementation of the SelectionHandler to ensure we get triplestore
 * data instead of SQL table data.
 */
class EntityReference_SelectionHandler_Generic_wisski_individual extends EntityReference_SelectionHandler_Generic {

  //////////////////////////////////////
  //
  // !!! functions that stay the same will be commented out at first
  //			delete later on
  //
  //////////////////////////////////////

  protected function __construct($field, $instance = NULL, $entity_type = NULL, $entity = NULL) {
    dpm(array(__FUNCTION__ => func_get_args()));
    $this->field = $field;
    $this->instance = $instance;
    $this->entity_type = $entity_type;
    $this->entity = $entity;
  }

  /**
   * Implements EntityReferenceHandler::settingsForm().
   */
  public static function settingsForm($field, $instance) {
    dpm(array(__FUNCTION__ => func_get_args()));
    return parent::settingsForm($field,$instance);
  }

  /**
   * Implements EntityReferenceHandler::getReferencableEntities().
   */
/*  public function getReferencableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    dpm(array(__FUNCTION__ => func_get_args()));
    $options = array();
    $entity_type = $this->field['settings']['target_type'];

    $query = $this->buildEntityFieldQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $results = $query->execute();

    if (!empty($results[$entity_type])) {
      $entities = entity_load($entity_type, array_keys($results[$entity_type]));
      foreach ($entities as $entity_id => $entity) {
        list(,, $bundle) = entity_extract_ids($entity_type, $entity);
        $options[$bundle][$entity_id] = check_plain($this->getLabel($entity));
      }
    }

    return $options;
  }
*/
  /**
   * Implements EntityReferenceHandler::getReferencableEntities().
   */
  public function getReferencableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    dpm(array(__FUNCTION__ => func_get_args()));
    $entity_type = $this->field['settings']['target_type'];
    if ($entity_type !== 'wisski_individual') return parent::getReferencableEntities($match,$match_operator,$limit);
    if ($limit > 0) {
      $options = array();
      module_load_include('inc','wisski_core','wisski_core.pathbuilder');
      $bundles = entity_load('wisski_core_bundle',$this->field['settings']['handler_settings']['target_bundles']);
//      dpm($bundles);
      foreach($bundles as $bundle) {
        if ($match_operator === '=') {
          dpm($match);
          $uris = array();
          preg_match_all('/\((\w*:\w*)\)/',$match,$uris);
          dpm($uris);
          if (count($uris) > 1) {
            $uris = $uris[0];
            $ents = wisski_salz_pb_get_bundle_info($bundle->uri);
            $hit = array_diff($uris,$ents);
            dpm(array('uris' => $uris,'ents' => $ents,'hit' => $hit));
            $options[$bundle->type] = array_fill($hit,$match);
          }
        }
        if ($match_operator === 'CONTAINS') {
          $paths = array();
          $ids = array();
          if (!empty($bundle->short_title_pattern)) {
            $title_pattern = array_expand($bundle->short_title_pattern);
            foreach($title_pattern as $elem) {
              $paths[$elem['id']] = wisski_core_make_path_array(array('field_info' => array('field_name' => $elem['id'])));
              $paths[$elem['id']]['optional'] = TRUE;
              $ids[] = $elem['id'];
            }
          } else {
            //watch out DIRTY FALLBACK, only for testing
            $paths[] = array(
              'path_array' => array('ecrm:P131_is_identified_by','ecrm:E82_Actor_Appellation'),
              'datatype_property' => 'ecrm:P3_has_note',
              'optional' => TRUE,
            );
            $paths[] = array(
              'path_array' => array('ecrm:P1_is_identified_by','ecrm:E41_Appellation'),
              'datatype_property' => 'ecrm:P3_has_note',
              'optional' => TRUE,
            );
          }
          $matches = array();
          if (is_string($match)) $matches = explode(' ',$match);
          $info = wisski_salz_pb_query_multi_path($bundle->uri,$paths,$limit,0,FALSE,TRUE,'STR',$matches);
          if ($info !== FALSE) {
            foreach ($info as $entity_uri => $path_data) {
              $field_info = array();
              for ($i = 0; $i < count($path_data); $i++) {
                $field_info[$ids[$i]] = $path_data[$i];
              }
              $options[$bundle->type][$entity_uri] = wisski_core_make_short_title($field_info,$title_pattern);
            }
          }
        }  
      }
    }
/*    
    if (!empty($results[$entity_type])) {
      $entities = entity_load($entity_type, array_keys($results[$entity_type]));
      foreach ($entities as $entity_id => $entity) {
        list(,, $bundle) = entity_extract_ids($entity_type, $entity);
        $options[$bundle][$entity_id] = check_plain($this->getLabel($entity));
      }
    }
*/
    dpm($options);
    return $options;
  }


  /**
   * Implements EntityReferenceHandler::countReferencableEntities().
   */
  public function countReferencableEntities($match = NULL, $match_operator = 'CONTAINS') {
    dpm(array(__FUNCTION__ => func_get_args()));
  }

  /**
   * Implements EntityReferenceHandler::validateReferencableEntities().
   */
  public function validateReferencableEntities(array $ids) {
    dpm(array(__FUNCTION__ => func_get_args()));
    return array();
  }

  /**
   * Implements EntityReferenceHandler::validateAutocompleteInput().
   */
  public function validateAutocompleteInput($input, &$element, &$form_state, $form) {
      dpm(array(__FUNCTION__ => func_get_args()));
      $entities = $this->getReferencableEntities($input, '=', 6);
      if (empty($entities)) {
        // Error if there are no entities available for a required field.
        form_error($element, t('There are no entities matching "%value"', array('%value' => $input)));
      }
      elseif (count($entities) > 5) {
        // Error if there are more than 5 matching entities.
        form_error($element, t('Many entities are called %value. Specify the one you want by appending the id in parentheses, like "@value (@id)"', array(
          '%value' => $input,
          '@value' => $input,
          '@id' => key($entities),
        )));
      }
      elseif (count($entities) > 1) {
        // More helpful error if there are only a few matching entities.
        $multiples = array();
        foreach ($entities as $id => $name) {
          $multiples[] = $name . ' (' . $id . ')';
        }
        form_error($element, t('Multiple entities match this reference; "%multiple"', array('%multiple' => implode('", "', $multiples))));
      }
      else {
        // Take the one and only matching entity.
        return key($entities);
      }
  }

  /**
   * Build an EntityFieldQuery to get referencable entities.
   */
  protected function buildEntityFieldQuery($match = NULL, $match_operator = 'CONTAINS') {
    dpm(array(__FUNCTION__ => func_get_args()));
    
  }

  /**
   * Implements EntityReferenceHandler::entityFieldQueryAlter().
   */
  public function entityFieldQueryAlter(SelectQueryInterface $query) {

  }

  /**
   * Helper method: pass a query to the alteration system again.
   *
   * This allow Entity Reference to add a tag to an existing query, to ask
   * access control mechanisms to alter it again.
   */
  protected function reAlterQuery(SelectQueryInterface $query, $tag, $base_table) {
    dpm(array(__FUNCTION__ => func_get_args()));
  }

  /**
   * Implements EntityReferenceHandler::getLabel().
   */
  public function getLabel($entity) {
    dpm(array(__FUNCTION__ => func_get_args()));
    $target_type = $this->field['settings']['target_type'];
    return entity_access('view', $target_type, $entity) ? entity_label($target_type, $entity) : t('- Restricted access -');
  }

  /**
   * Ensure a base table exists for the query.
   *
   * If we have a field-only query, we want to assure we have a base-table
   * so we can later alter the query in entityFieldQueryAlter().
   *
   * @param $query
   *   The Select query.
   *
   * @return
   *   The alias of the base-table.
   */
  public function ensureBaseTable(SelectQueryInterface $query) {
    dpm(array(__FUNCTION__ => func_get_args()));
    $tables = $query->getTables();

    // Check the current base table.
    foreach ($tables as $table) {
      if (empty($table['join'])) {
        $alias = $table['alias'];
        break;
      }
    }

    if (strpos($alias, 'field_data_') !== 0) {
      // The existing base-table is the correct one.
      return $alias;
    }

    // Join the known base-table.
    $target_type = $this->field['settings']['target_type'];
    $entity_info = entity_get_info($target_type);
    $id = $entity_info['entity keys']['id'];
    // Return the alias of the table.
    return $query->innerJoin($target_type, NULL, "%alias.$id = $alias.entity_id");
  }
}
