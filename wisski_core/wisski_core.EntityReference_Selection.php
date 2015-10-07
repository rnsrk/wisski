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

    $this->field = $field;
    $this->instance = $instance;
    $this->entity_type = $entity_type;
    $this->entity = $entity;
  }

  /**
   * Implements EntityReferenceHandler::settingsForm().
   */
  public static function settingsForm($field, $instance) {
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
/*  public function getReferencableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    dpm(array(__FUNCTION__ => func_get_args()));
    $entity_type = $this->field['settings']['target_type'];
    if ($entity_type !== 'wisski_individual') return parent::getReferencableEntities($match,$match_operator,$limit);
    $options = FALSE;
    if ($limit >= 0) {
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
              $paths[$elem['id']]['required'] = FALSE;
              $ids[] = $elem['id'];
            }
            dpm($paths);
            $matches = array();
            if (is_string($match)) $matches = explode(' ',$match);
            $info = wisski_salz_pb_query_multi_path($bundle->uri,$paths,$limit,0,FALSE,TRUE,'STR',$matches);
            if ($info !== FALSE) {
              dpm($info);
              foreach ($info as $entity_uri => $path_data) {
                $field_info = array();
                for ($i = 0; $i < count($path_data); $i++) {
                  $field_info[$ids[$i]] = $path_data[$i];
                }
                $options[$bundle->type][$entity_uri] = wisski_core_make_short_title($field_info,$title_pattern);
              }
            }
          } else {
            $ents = wisski_salz_pb_get_bundle_info($bundle->uri);
            //watch out DIRTY FALLBACK, only for testing
            $paths['actor'] = array(
              'path_array' => array('ecrm:P131_is_identified_by','ecrm:E82_Actor_Appellation'),
              'datatype_property' => 'ecrm:P3_has_note',
              'required' => FALSE,
            );
            $paths['all'] = array(
              'path_array' => array('ecrm:P1_is_identified_by','ecrm:E41_Appellation'),
              'datatype_property' => 'ecrm:P3_has_note',
              'required' => FALSE,
            );
            $info = wisski_salz_pb_query_multi_path($bundle->uri,$paths,$limit,0,FALSE,TRUE,'STR');
            foreach($ents as $uri) {
              $entity = entity_load_single('wisski_individual',$uri);
              if (isset($info[$uri])) {
                $options[$bundle->type][$uri] = current(current($info[$uri]));
              } else $options[$bundle->type][$uri] = $uri;
            }
          }
        }  
      }
    }
    
//    if (!empty($results[$entity_type])) {
//      $entities = entity_load($entity_type, array_keys($results[$entity_type]));
//      foreach ($entities as $entity_id => $entity) {
//        list(,, $bundle) = entity_extract_ids($entity_type, $entity);
//        $options[$bundle][$entity_id] = check_plain($this->getLabel($entity));
//      }
    }

    dpm($options);
    return $options;
  }
*/
/**
   * Implements EntityReferenceHandler::getReferencableEntities().
   */
  public function getReferencableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {

//    dpm(func_get_args(),__FUNCTION__);

    // get the entity type for this query
    $entity_type = $this->field['settings']['target_type'];
#    drupal_set_message(serialize($entity_type));

    // in any case which does not refer to us - let the parent do the work
    if ($entity_type !== 'wisski_individual') return parent::getReferencableEntities($match,$match_operator,$limit);

    $options = FALSE;

    // if there is a limit ... this seems to be useless, rewrite? what is with negative limits?
    if ($limit >= 0) {
    
      //WATCH OUT: dirty hard-coded limit, use dynamic threshold later
      if ($limit == 0 || $limit > 32) $limit = 32;
      
      // do something
      $options = array();
      
      $target_bundle_names = $this->field['settings']['handler_settings']['target_bundles'];
      
      // if there are target bundles
      if (!empty($target_bundle_names)) {

        // load the pathbuilder
        module_load_include('inc','wisski_core','wisski_core.pathbuilder');
        
        // load the bundle info to grab URIs and short title patterns later
        $bundles = entity_load('wisski_core_bundle',array_values($target_bundle_names));

        foreach($bundles as $bundle) {
          
          if ($match_operator === '=') {

            $uris = array();
            preg_match_all('/\((\w*:\w*)\)/',$match,$uris);
//  	        dpm($uris);
            if (count($uris) > 1) {
              $uris = $uris[0];
              $ents = wisski_salz_pb_get_bundle_info($bundle->uri);

              $hit = array_diff($uris,$ents);
//	            dpm(array('uris' => $uris,'ents' => $ents,'hit' => $hit));
              $options[$bundle->type] = array_fill($hit,$match);
            }
          }
          if ($match_operator === 'CONTAINS') {
            $paths = array();
            $ids = array();
#            dpm("hier");
            if (!empty($bundle->short_title_pattern)) {
#               dpm("hier2");
              $title_pattern = array_expand($bundle->short_title_pattern);
              foreach($title_pattern as $elem) {
                $path = wisski_core_make_path_array(array('connected_bundle'=>$bundle->type,'field_info' => array('instance_id' => $elem['id'])));
                while(count($path) == 1) $path = current($path);
                $paths[$elem['id']] = $path;
                $paths[$elem['id']]['required'] = FALSE;
                $ids[] = $elem['id'];
              }
//          	  dpm($paths);
              $matches = array();
              if (is_string($match)) $matches = explode(' ',$match);
              $info = wisski_salz_pb_query_multi_path($bundle->uri,$paths,$limit,0,FALSE,TRUE,'STR',$matches);
              if ($info !== FALSE) {
//      	        dpm($info);
                foreach ($info as $entity_uri => $path_data) {
                  $entity = entity_load_single('wisski_individual',$entity_uri,array('no_fields'=>TRUE));
                  $options[$bundle->type][$entity->id] = entity_label('wisski_individual',$entity);
                }
              }
            } else {
//              dpm("hier3");
              
//              dpm($bundle);
              $ents = wisski_salz_pb_get_bundle_info($bundle->uri);
//              dpm($bundle->uri);
//              dpm($ents);
              $count = 0;
              foreach($ents as $uri) {
                if ($count === $limit) break;
                $count++;
                $entity = entity_load_single('wisski_individual',$uri,array('no_fields'=>TRUE));
                
                $label = entity_label('wisski_individual',$entity);
                if (empty($label)) $label = $entity->uri;
                //we only allow the answers that match $match
                if (isset($match) && stripos($label,$match) === FALSE) continue;
                $options[$bundle->type][$entity->id] = $label;
              }
            }
          }  
        }
      // if there is no target bundle
      } else {
        #if ($match_operator == 'CONTAINS') {
          $match = preg_replace('/^(\"|\')*|(\"|\')*$/','',$match);
          $match = preg_replace('/\"/','\\"',$match);
          $match = preg_replace('/\'/','\\\'',$match);
          $match = preg_replace('/\%/','\\\%',$match);
          $match = preg_replace('/\_/','\\\_',$match);
          $or = db_or()->condition('title','%'.$match.'%','LIKE')
            ->condition('uri','"%'.$match.'%"','LIKE');
          $ents = db_select('wisski_entity_data','ent')
            ->fields('ent',array('id','uri','title','type'))
            ->range(0,$limit)
            ->condition('dirty',0)
            ->condition($or)
            ->execute();
          while($ent = $ents->fetchObject()) {
            $options[$ent->type][$ent->id] = $ent->title;
            $limit--;
          }
          if ($limit > 0) {
            $uris = wisski_salz_pb_get_matching_individuals($match,$limit);
            foreach($uris as $bundle_uri => $inds) {
              $bundle_type = db_select('wisski_entity_bundles','bund')
                ->fields('bund',array('uri','type'))
                ->condition('uri',$bundle_uri)
                ->execute()
                ->fetchObject()
                ->type;
              $added_ents = array();
              foreach ($inds as $uri) {
                $id = db_select('wisski_entity_data','e')
                      ->fields('e',array('id'))
                      ->condition('uri',$uri)
                      ->condition('type',$bundle_type)
                      ->execute();
                if ($id->rowCount() === 1) {
                  $id = $id->fetchObject()->id; 
                } else {
                  $id = db_insert('wisski_entity_data')
                      ->fields(array('uri'=>$uri,'type'=>$bundle_type))
                      ->execute();
                }
                $added_ents[$id] = $uri;
              }
//              dpm(array('inds'=>$inds,'added'=>$added_ents),'loaded');
              if (!empty($options[$bundle_type])) {
                $options[$bundle_type] = array_merge($added_ents,$options[$bundle_type]);
              } else {
                $options[$bundle_type] = $added_ents;
              }
            }
          }          
        #}
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
//    dpm(func_get_args()+array('result'=>$options),__FUNCTION__);
//    drupal_set_message(__FUNCTION__.' should have DPMed here');
    return $options;
  }


  /**
   * Implements EntityReferenceHandler::countReferencableEntities().
   */
  public function countReferencableEntities($match = NULL, $match_operator = 'CONTAINS') {
//    dpm(func_get_args(),__FUNCTION__);
  }

  /**
   * Implements EntityReferenceHandler::validateReferencableEntities().
   */
  public function validateReferencableEntities(array $ids) {
//    dpm(func_get_args(),__FUNCTION__);
    $real_ids = db_select('wisski_entity_data','e')
                ->fields('e',array('id'))
                ->condition('id',$ids,'IN')
                ->execute()
                ->fetchAllAssoc('id');
    return array_keys($real_ids);
  }

  /**
   * Implements EntityReferenceHandler::validateAutocompleteInput().
   */
  public function validateAutocompleteInput($input, &$element, &$form_state, $form) {
//      dpm(func_get_args(),__FUNCTION__);
      $entities = array();
      $sorted_entities = $this->getReferencableEntities($input, '=', 6);
      foreach($sorted_entities as $bundle_type => $bundle_entities) {
        if (!empty($bundle_entities)) {
          $entities += $bundle_entities;
        }
      }
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
//    dpm(func_get_args(),__FUNCTION__);
    
  }

  /**
   * Implements EntityReferenceHandler::entityFieldQueryAlter().
   */
  public function entityFieldQueryAlter(SelectQueryInterface $query) {
//    dpm(func_get_args(),__FUNCTION__);
  }

  /**
   * Helper method: pass a query to the alteration system again.
   *
   * This allow Entity Reference to add a tag to an existing query, to ask
   * access control mechanisms to alter it again.
   */
  protected function reAlterQuery(SelectQueryInterface $query, $tag, $base_table) {
//    dpm(func_get_args(),__FUNCTION__);
  }

  /**
   * Implements EntityReferenceHandler::getLabel().
   */
  public function getLabel($entity) {
//    dpm(func_get_args(),__FUNCTION__);
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
//    dpm(func_get_args(),__FUNCTION__);
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
