<?php

namespace Drupal\wisski_core\Plugin\views\query;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;



use Drupal\wisski_salz\AdapterHelper;
use Drupal\wisski_salz\Query\WisskiQueryDelegator;
use Drupal\wisski_core\WisskiCacheHelper;
use Drupal\wisski_core\Controller\WisskiEntityListBuilder;
use Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB;

/**
 * Views query plugin for an SQL query.
 *
 * @ingroup views_query_plugins
 *
 * @ViewsQuery(
 *   id = "wisski_individual_query",
 *   title = @Translation("WissKI Entity Query"),
 *   help = @Translation("Use WissKI Entities in Views backed by Drupal database API.")
 * )
 */
class WisskiIndividualQuery extends QueryPluginBase {

  /**
   * Generate a query and a countquery from all of the information supplied
   * to the object.
   *
   * @param $get_count
   *   Provide a countquery if this is true, otherwise provide a normal query.
   */
  public function query($get_count = FALSE) {
    $wisski_individual = \Drupal::entityTypeManager()->getDefinition('wisski_individual');

    $query = new WisskiQueryDelegator($wisski_individual, $this->groupOperator, array()); // TODO: EntityType object

    // iterate over the query groups stored in $this->where and
    // - create a new Condition Group Object for each of them
    // - finally add this group to the query object
    foreach ($this->where as $gid => $group) {
      $conjunction = strtolower($group["type"]);
      if ($conjunction == 'or') {
        $qgroup = $query->orConditionGroup();
      } else if($conjunction == 'and') {
        $qgroup = $query->andConditionGroup();
      } else {
        continue; // skip this condition group (should never occur)
      }

      foreach($group["conditions"] as $cid => $cond) {
        $qgroup = $qgroup->condition($cond["field"], $cond["value"], $cond["operator"]); 
      }

      $query = $query->condition($qgroup);
    }

    if ($get_count) {
      $query = $query->count();
    }

    // return it!
    return $query;
  }

  /**
   * Let modules modify the query just prior to finalizing it.
   *
   * @param view $view
   *   The view which is executed.
   */
  public function alter(ViewExecutable $view) {
    /* dpm(func_get_args(), "alter()"); */
  }

  /**
   * Builds the necessary info to execute the query.
   *
   * @param view $view
   *   The view which is executed.
   */
  public function build(ViewExecutable $view) {
    // Store the view in the object to be able to use it later.
    $this->view = $view;

    $view->initPager();

    // Let the pager modify the query to add limits.
    $view->pager->query();

    $view->build_info['query'] = $this->query();
    $view->build_info['count_query'] = $this->query(TRUE);
  }

  /**
   * Executes the query and fills the associated view object with according
   * values.
   *
   * Values to set: $view->result, $view->total_rows, $view->execute_time,
   * $view->pager['current_page'].
   *
   * $view->result should contain an array of objects. The array must use a
   * numeric index starting at 0.
   *
   * @param view $view
   *   The view which is executed.
   */
  public function execute(ViewExecutable $view) {

    // fetch the query and count query from the build_info
    $query = $view->build_info['query'];
    $count_query = $view->build_info['count_query'];

    // add meta data to both queries
    $query->addMetaData('view', $view);
    $count_query->addMetaData('view', $view);

    // for measuring the time the query took
    $start = microtime(TRUE);

    // if we don't have a query, we should bail out!
    if (!$query) {
      $view->execute_time = microtime(TRUE) - $start;
      return;
    }
    try {
      
      // execute the count query for the pager
      if ($view->pager->useCountQuery() || !empty($view->get_total_rows)) {
        // this should just be:
        // $view->pager->executeCountQuery($count_query);
        // but that expects a count_query returning a PDO.
        $this->pagerExecuteCountQueryHack($view, $count_query);
      }

      // let the pager add limits and skips
      $view->pager->preExecute($query);

      // We can't have an offset without a limit, so provide a very large limit instead.
      if (!empty($this->limit) || !empty($this->offset)) {
        $limit = intval(!empty($this->limit) ? $this->limit : 999999);
        $offset = intval(!empty($this->offset) ? $this->offset : 0);
        $query = $query->range($offset, $limit);
      }

      // find all entity ids matching the query
      // and also find involved bundles!
      $entity_ids = $query->execute();
      $bundle_ids = $query->getWissKIBundleIDs();

      // turn the returned Entity IDs and populate $view->result[]
      $values_per_row = $this->fetchEntityData($entity_ids, $bundle_ids);
      foreach ($values_per_row as $rowid => $values) {
        $row = new ResultRow($values);
        $row->index = $rowid;
        $view->result[] = $row;
      }

      // update the pager
      $view->pager->postExecute($view->result);
      $view->pager->updatePageInfo();
      $view->total_rows = $view->pager->getTotalItems();

      // Load all entities contained in the results.
      $this->loadEntities($view->result);
    }
    catch (DatabaseExceptionWrapper $e) { // something went wrong in the database
      $view->result = [];
      if (!empty($view->live_preview)) {
        $this->messenger->addError($e->getMessage());
      }
      else {
        throw new DatabaseExceptionWrapper("Exception in {$view->storage->label()}[{$view->storage->id()}]: {$e->getMessage()}");
      }
    }
    
    $view->execute_time = microtime(TRUE) - $start;
  }

  /** executes the count query and informs the pager about it */
  private function pagerExecuteCountQueryHack(ViewExecutable &$view, WisskiQueryDelegator &$count_query) {

    // adapted from PagerPluginBase::executeCountQuery to be compatible with WissKI Queries.
    // TODO: Figure out a clean approach to this

    $view->pager->total_items = $count_query->execute(); // ->fetchField();
    if (!empty($view->pager->options['offset'])) {
      $view->pager->total_items -= $view->pager->options['offset'];
    }

    // Prevent from being negative.
    $view->pager->total_items = max(0, $view->pager->total_items);
  }

  /**
   * Iterates through the list of requested fields and fetches data for each enitity in bundle_ids.
   */
  private function fetchEntityData($entity_ids, $bundle_ids = array()) {
    $fields = $this->fields; // fields to be filled
    $values_per_row = []; // values that are being returned

    // we always set the 'eid' field
    foreach ($entity_ids as $entity_id) {
      $values_per_row[$entity_id] = ['eid' => $entity_id];
    }
    unset($fields['eid']);

    $eid_to_uri_per_aid = [];
   
    // store here only fields that may be attached to the entity.
    // typically our "wisski-path-special-fields" for the view may
    // not be attached. 
    //
    // this is to avoid loading the entire entity (which is expensive)!
    // instead we can use this as a fake entity object.
    $pseudo_entity_fields = array();
    
    // when we request the special _entity field
    // we make a special dummy load.
    $do_dummy_load = FALSE;
    if(isset($fields['_entity'])) {
      $do_dummy_load = $fields['_entity'];
      unset($fields['_entity']);
    }
    
    $pb_cache = array();
    $path_cache = array();
   
    // iterate over all the fields
    // depending on the field we have, add the right data to the result
    while (($field = array_shift($fields)) !== NULL) {
      if ($field == 'title') {
        $bid = (!empty($bundle_ids)) ? reset($bundle_ids) : NULL; // get the first bundle

        foreach ($values_per_row as $eid => &$row) {
          [$bids, $bid] = $this->get_bids_bid_for_eid($eid, $bundle_ids);

          // fill in missing bundle id
          if(empty($row['bundle'])) {
            $row['bundle'] = $bid;
            $pseudo_entity_fields[$eid]['bundle'] = $row['bundle'];
          }
          
          // generate the title from the bundle id
          $row['title'] = wisski_core_generate_title($eid, NULL, FALSE, $row['bundle']); // previously $bid instead of $row['bundle']
          $pseudo_entity_fields[$eid]['title'] = $row['title'];
        }

        continue;
      }

      if ($field == 'preferred_uri') {
        // find the preferred local store
        $localstore = AdapterHelper::getPreferredLocalStore();

        foreach ($values_per_row as $eid => &$row) {
          if (!$localstore) {
            $row['preferred_uri'] = '';
            continue;
          }
          
          // By Mark: I am not entirely sure, if I want to create a uri here...
          $row['preferred_uri'] = AdapterHelper::getUrisForDrupalId($eid, $localstore, TRUE);
        }

        continue; 
      }

      if ($field == 'preview_image') {

        // prepare the listbuilder for external access.
        \Drupal::entityTypeManager()->getStorage('wisski_individual')->preparePreviewImages();
    
        foreach($values_per_row as $eid => &$row) {
          [$bids, $bid] = $this->get_bids_bid_for_eid($eid, $bundle_ids);
          
          // fill in missing bundle id
          if(empty($row['bundle'])) {
            $row['bundle'] = $bid;
            $pseudo_entity_fields[$eid]['bundle'] = $row['bundle'];  
          }

          // fetch the preview image
          # dpm(microtime(), "br");          
          $preview_image_uri = \Drupal::entityTypeManager()->getStorage('wisski_individual')->getPreviewImageUri($eid,$bid);
          # dpm(microtime(), "brout");          
          
          // prefix with public path
          if(strpos($preview_image_uri, "public://") !== FALSE) {
            $preview_image_uri = str_replace("public:/", \Drupal::service('stream_wrapper.public')->baseUrl(), $preview_image_uri);
          }
    
          // make html from it!
          global $base_path;
          $row['preview_image'] = '<a href="' . $base_path . 'wisski/navigate/'.$eid.'/view?wisski_bundle='.$bid.'"><img src="'. $preview_image_uri .'" /></a>';
          $pseudo_entity_fields[$eid]['preview_image'] = $row['preview_image'];
          
        }

        continue;
      }

      if ($field == 'bundle' || $field == 'bundle_label' || $field == 'bundles') {
        
        foreach ($values_per_row as $eid => &$row) {
          [$bids, $bid] = $this->get_bids_bid_for_eid($eid, $bundle_ids);

          $row['bundles'] = $bids; 
          $row['bundle'] = $bid;
          
          // find the label of the bundle
          $bundle = \Drupal::service('entity_type.manager')->getStorage('wisski_bundle')->load($bid);
          $row['bundle_label'] = $bundle->label();

          // cache
          $pseudo_entity_fields[$eid]['bundle'] = $row['bundle'];
          $pseudo_entity_fields[$eid]['bundles'] = $row['bundles'];
          $pseudo_entity_fields[$eid]['bundle_label'] = $row['bundle_label'];
        }

        continue;
      }

      // any other field must be of the wisski_path_ type.

      if (!(strpos($field, "wisski_path_") === 0 && strpos($field, "__") !== FALSE)) {
        // TODO: unsupported field => log
        continue;
      }
      

      // the if is rather a hack but currently I have no idea how to access
      // the field information wisski_field from WisskiEntityViewsData.
      $pb_and_path = explode("__", substr($field, 12), 2);
      if (count($pb_and_path) != 2) {
        $this->messenger()->addError("Bad field id for Wisski views: $field");
        continue;
      }


      // ensure that the pathbuilder module is loaded
      // TODO: Can we move this to the top of the function?
      $moduleHandler = \Drupal::service('module_handler');
      if (!$moduleHandler->moduleExists('wisski_pathbuilder')){
        return NULL;
      }

      // load the relevant pathbuilder from the cache
      // populate the cache if it doesn't exist
      $pb = $pb_cache[$pb_and_path[0]];
      if (!isset($pb)) {
        $pb = \Drupal::service('entity_type.manager')->getStorage('wisski_pathbuilder')->load($pb_and_path[0]);
        $pb_cache[$pb_and_path[0]] = $pb;
      }

      if (!$pb) { // no pathbuilder
        $this->messenger()->addError("Bad pathbuilder id for Wisski views: $pb_and_path[0]");
        continue;
      }

      // load the relevant path from the cache
      // populate the cache if it doesn't exist
      $path = $path_cache[$pb_and_path[1]];
      if (!isset($path)) {
        $path = \Drupal::service('entity_type.manager')->getStorage('wisski_path')->load($pb_and_path[1]);
        $path_cache[$pb_and_path[1]] = $path;
      }

      if (!$path) { // no path
        $this->messenger()->addError("Bad path id for Wisski views: $pb_and_path[1]");
        continue;
      }


      // get the path from the pathbuilder
      $pbp = $pb->getPbPath($path->getID());
      $field_to_check = $pbp['field'];

      // remember that we had a different field from what we expected
      if($field_to_check != $field) {
        $no_entity_field[] = $field;
      }
      

      $first_row = current($values_per_row);

      $field_def = \Drupal::service('entity_field.manager')->getFieldMap();#->getFieldDefinitions('wisski_individual',$values_per_row[$eid]['bundle']);

      $is_file = FALSE;
      $main_prop = "value";

      // get the main property name             
      if(!empty($field_def) && isset($field_def['wisski_individual']) && isset($field_def['wisski_individual'][$field_to_check]) && isset($field_def['wisski_individual'][$field_to_check]['bundles'])) {
        $fbundles = $field_def['wisski_individual'][$field_to_check]['bundles'];
#                    dpm(current($fbundles), "fb");
        $field_def = \Drupal::service('entity_field.manager')->getFieldDefinitions('wisski_individual',current($fbundles));

#              dpm(serialize($field_def[$field_to_check]->getFieldStorageDefinition()->getDependencies()), "def");

        $is_file = in_array('file',$field_def[$field_to_check]->getFieldStorageDefinition()->getDependencies()['module']);

        $main_prop = $field_def[$field_to_check]->getFieldStorageDefinition()->getMainPropertyName();
#              dpm($main_prop, "found it! for field " . $field_to_check);
        }

        $adapters = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->loadMultiple();
        foreach($adapters as $adapter) {
          if (!$adapter) {
            $this->messenger()->addError("Bad adapter id for pathbuilder $pb_and_path[0]: " . $pb->getAdapterId());
            continue;
          }
          
          $aid = $adapter->id();
          
          // find a Sparql11EngineWithPB or bail out
          $engine = $adapter->getEngine();
          if (!($engine instanceof Sparql11EngineWithPB)) {
            // lets just hope it can handle it somehow...
            // @todo - this is not funny!!!
            continue;
          }

          // we need to distinguish references and data primitives
          $is_reference = $path->getDatatypeProperty() == 'empty';
          $out_prop = 'out';
          $disamb = NULL;
          if ($is_reference) {
            $disamb = $path->getDisamb();
            if ($disamb < 2) $disamb = count($path->getPathArray());
            // NOTE: $disamb is the concept position (starting with 1)
            // but generateTriplesForPath() names vars by concept 
            // position times 2, starting with 0!
            $disamb = 'x' . (($disamb - 1) * 2);
            $out_prop = NULL;
          } else {
            $disamb = $path->getDisamb();
            if(!empty($disamb)) {
              $disamb = 'x' . (($disamb - 1) * 2);
            }
          }

#                dpm($pbp);
#                $starting_position = $pb->getRelativeStartingPosition($pbp['parent'], FALSE);
#                dpm($starting_position, "start");

          $select = "SELECT DISTINCT ?x0 ";
          if(!empty($disamb))
            $select .= '?' . $disamb . ' ';

          if(!empty($out_prop))
            $select .= '?' . $out_prop . ' ';

          $select .= " WHERE { VALUES ?x0 { ";

          $uris_to_eids = []; // keep for reverse mapping of results
          foreach ($entity_ids as $eid) {
            if (isset($eid_to_uri_per_aid[$aid]) && isset($eid_to_uri_per_aid[$aid][$eid])) {
              $uri = $eid_to_uri_per_aid[$aid][$eid];
            } 
            else {
              $uri = $engine->getUriForDrupalId($eid, FALSE);
              if ($uri) {
                if (!isset($eid_to_uri_per_aid[$aid])) {
                  $eid_to_uri_per_aid[$aid] = [];
                }
                $eid_to_uri_per_aid[$aid][$eid] = $uri;
              }
              else {
                continue;
              }
            }
            $select .= "<$uri> ";
            $uris_to_eids[$uri] = $eid;
          }
          $select .= "} ";
          // NOTE: we need to set the $relative param to FALSE. All other
          // optional params should be default values
          $select .= $engine->generateTriplesForPath($pb, $path, "", NULL, NULL, 0, 0, FALSE, '=', 'field', FALSE);
          #$select .= "}";

          // add filter criteria on this level
          // because these paths must not align with entities.
#                if(isset($filter_regex[$field])) {
#                  foreach($filter_regex[$field] as $filter_val) {
#                    $select .= "FILTER REGEX(?out, '" . $filter_val['val'] . "', 'i') . ";
#                  }
#                }

          $select .= "}";

          #dpm($select, "select " . $path->getID() .': '.$path->getDatatypeProperty() . " on " . $adapter->id() );
#                dpm(microtime(), "before");
          $result = $engine->directQuery($select);

              #dpm([$select, $result], 'select' . $path->getID());

#                dpm(microtime(), "after");
          foreach ($result as $sparql_row) {
            if (isset($uris_to_eids[$sparql_row->x0->getUri()])) {
#                    dpm($uris_to_eids[$sparql_row->x0->getUri()], $sparql_row->x0->getUri());
              $eid = $uris_to_eids[$sparql_row->x0->getUri()];

/*                    
              $pbp = $pb->getPbPath($path->getID());
              $realfield = $pbp['field'];
              dpm($values_per_row[$eid]['bundle']);
#                    $field_def = \Drupal::service('entity_field.manager')->getFieldMap();#->getFieldDefinitions('wisski_individual',$values_per_row[$eid]['bundle']);
              $fieldmap = \Drupal::service('entity_field.manager')->getFieldMap();

              $fbundles = $fieldmap['wisski_individual'][$realfield]['bundles'];
#                    dpm(current($fbundles), "fb");

              $field_def = \Drupal::service('entity_field.manager')->getFieldDefinitions('wisski_individual',current($fbundles));
              dpm($realfield, "realfield");
              dpm(\Drupal::service('entity_field.manager')->getFieldMap(), "fieldmap");
#                    $field_def = \Drupal::service('plugin.manager.field.field_type')->getDefinitions();
              dpm(serialize($field_def[$realfield]), "fdef");
              dpm($field_def[$realfield]->getFieldStorageDefinition()->getMainPropertyName(), "mp!");
*/
#                    $field_ob = \Drupal\field\Entity\FieldConfig::load($realfield);
#                    dpm($field_ob->getFieldStorageDefinition()->getMainPropertyName(), "yay!");	
#                    dpm($pbp, "realfield!");
#                    dpm($eid, "eid!!");
#                    dpm($is_reference, "is ref");
              if (!$is_reference && (!isset($sparql_row->$out_prop) || $sparql_row->$out_prop === NULL)) {
                \Drupal::logger('WissKI views')->warning("invalid reference slot {s} for path {pid}", ['s' => $out_prop, 'pid' => $path->getID()]);
              }
              elseif ($is_reference) {
#                      dpm($disamb, "yuhu!");
                $referenced_uri = $sparql_row->$disamb->getUri();
#                      dpm($referenced_uri);
                $referenced_eid = AdapterHelper::getDrupalIdForUri($referenced_uri);
#                      dpm($referenced_eid);
                $referenced_title = wisski_core_generate_title($referenced_eid);
#                      dpm($referenced_title);
                $values_per_row[$eid][$field][] = array('value' => $referenced_title, 'target_id' => $referenced_eid, 'wisskiDisamb' => $referenced_uri);
                // duplicate the information to the field for the entity-management
                $values_per_row[$eid][$field_to_check][] = array('value' => $referenced_title, 'target_id' => $referenced_eid, 'wisskiDisamb' => $referenced_uri);
                #$values_per_row[$eid][$field][] = $referenced_eid;
              }
              else {
                if(!empty($disamb)) {
                  if(!empty($is_file)) {
                    $this->messenger()->addWarning("On your image path there is a disamb set. How do you think the system now should behave? Make the image clickable or what?!");
                  }
#                          $storage = \Drupal::entityTypeManager()->getStorage('wisski_individual');
#                          $val = $storage->getFileId($sparql_row->$out_prop->getValue());
#                          // in case of files: throw the disamb away!
#                          $values_per_row[$eid][$field][] = array($main_prop => $val);
#                          $values_per_row[$eid][$field_to_check][] = array($main_prop => $val);
#                        } else {
                  $values_per_row[$eid][$field][] = array($main_prop => $sparql_row->$out_prop->getValue(), 'wisskiDisamb' => $sparql_row->$disamb->getUri());
                  $values_per_row[$eid][$field_to_check][] = array($main_prop => $sparql_row->$out_prop->getValue(), 'wisskiDisamb' => $sparql_row->$disamb->getUri());
#                        }
                } else {
#                        dpm(serialize($is_file), "is file!!");
                  if(!empty($is_file)) {
                    $storage = \Drupal::entityTypeManager()->getStorage('wisski_individual');
                    $val = $storage->getFileId($sparql_row->$out_prop->getValue());
                    $values_per_row[$eid][$field][] = array($main_prop => $val);
                    $values_per_row[$eid][$field_to_check][] = array($main_prop => $val);
                  } else {
                    $values_per_row[$eid][$field][] = array($main_prop => $sparql_row->$out_prop->getValue());
                    $values_per_row[$eid][$field_to_check][] = array($main_prop => $sparql_row->$out_prop->getValue());
                  }
                }
              }
              $pseudo_entity_fields[$eid][$field_to_check] = $values_per_row[$eid][$field_to_check];
#                    $entity_dump[$eid] = \Drupal::entityManager()->getStorage('wisski_individual')->addCacheValues(array($values_per_row[$eid]), $values_per_row);

#                    dpm($values_per_row[$eid]);
            }
          }
#if ($field == 'wisski_path_sammlungsobjekt__91') rpm([$path, $result, $values_per_row], '91');
      }
    }
   
    if ($do_dummy_load) {
      foreach ($values_per_row as $eid => &$row) {
        // if we don't have a bundle we're in danger zone!
        if(empty($row['bundle'])) {
        [$bids, $bid] = $this->get_bids_bid_for_eid($eid, $bundle_ids);

          $row['bundles'] = $bids;
          $row['bundle'] = $bid;

          $pseudo_entity_fields[$eid]['bundles'] = $values_per_row[$eid]['bundles'];
          $pseudo_entity_fields[$eid]['bundle'] = $values_per_row[$eid]['bundle'];
        }

        // compatibility for old systems like herbar...
        if(!isset($pseudo_entity_fields[$eid]['eid'])) {
          $pseudo_entity_fields[$eid]['eid'] = array('value' => $eid);
        }
        
        // store the loaded entities in the cache!
        $entities = \Drupal::service('entity_type.manager')->getStorage('wisski_individual')->addCacheValues(array($eid => $eid), $pseudo_entity_fields);

        // store the entity in the row!
        $row['_entity'] = $entities[$eid];
      }
    }
   
    return array_values($values_per_row);
  }

    /** return an array containing [$bids, $bid] containing the bundle ids and bundle id for a particular entity */
    private function get_bids_bid_for_eid($entity_id, $bundle_ids) {
      // attempt to find all involved bundle ids
      // if there are none, use Adapter Helper to find them!
      $bids = $bundle_ids;
      if(empty($bids)) {
        $bids = AdapterHelper::getBundleIdsForEntityId($entity_id, TRUE);
      }
  
      // pick the first bundle id for the entity
      // TODO: Should we do something smarter than the first one here?
      $bid = reset($bids);
  
      return [$bids, $bid];
    }

  /**
   * Loads all entities contained in the passed-in $results.
   *.
   * If the entity belongs to the base table, then it gets stored in
   * $result->_entity. Otherwise, it gets stored in
   * $result->_relationship_entities[$relationship_id];
   *
   * Query plugins that don't support entities can leave the method empty.
   */
  public function loadEntities(&$results) {
    // we're already loading entities in ->fetchEntityData
    // so we don't need to do anything here.
  }

  /**
   * Ensure a table exists in the queue; if it already exists it won't
   * do anything, but if it doesn't it will add the table queue. It will ensure
   * a path leads back to the relationship table.
   *
   * @param $table
   *   The unaliased name of the table to ensure.
   * @param $relationship
   *   The relationship to ensure the table links to. Each relationship will
   *   get a unique instance of the table being added. If not specified,
   *   will be the primary table.
   * @param \Drupal\views\Plugin\views\join\JoinPluginBase $join
   *   A Join object (or derived object) to join the alias in.
   *
   * @return
   *   The alias used to refer to this specific table, or NULL if the table
   *   cannot be ensured.
   */
  public function ensureTable($table, $relationship = NULL, JoinPluginBase $join = NULL) {
    // not implemented: sql only
  }

  /**
   * Add a field to the query table, possibly with an alias. This will
   * automatically call ensureTable to make sure the required table
   * exists, *unless* $table is unset.
   *
   * @param $table
   *   The table this field is attached to. If NULL, it is assumed this will
   *   be a formula; otherwise, ensureTable is used to make sure the
   *   table exists.
   * @param $field
   *   The name of the field to add. This may be a real field or a formula.
   * @param $alias
   *   The alias to create. If not specified, the alias will be $table_$field
   *   unless $table is NULL. When adding formulae, it is recommended that an
   *   alias be used.
   * @param $params
   *   An array of parameters additional to the field that will control items
   *   such as aggregation functions and DISTINCT. Some values that are
   *   recognized:
   *   - function: An aggregation function to apply, such as SUM.
   *   - aggregate: Set to TRUE to indicate that this value should be
   *     aggregated in a GROUP BY.
   *
   * @return string
   *   The name that this field can be referred to as. Usually this is the alias.
   */
  public function addField($table, $field, $alias = '', $params = []) {
    $this->fields[$field] = $field;
    if (strpos($field, "wisski_path_") === 0) {
      // we always load the whole entity if the field is a path.
      // TODO: this is very slow when retrieving many entities; find a way to
      // get the field values without loading the entity.
      $this->fields['_entity'] = '_entity';
    }
    return $field;
  }

    /**
   * Add a simple WHERE clause to the query. The caller is responsible for
   * ensuring that all fields are fully qualified (TABLE.FIELD) and that
   * the table already exists in the query.
   *
   * The $field, $value and $operator arguments can also be passed in with a
   * single DatabaseCondition object, like this:
   * @code
   * $this->query->addWhere(
   *   $this->options['group'],
   *   ($this->query->getConnection()->condition('OR'))
   *     ->condition($field, $value, 'NOT IN')
   *     ->condition($field, $value, 'IS NULL')
   * );
   * @endcode
   *
   * @param $group
   *   The WHERE group to add these to; groups are used to create AND/OR
   *   sections. Groups cannot be nested. Use 0 as the default group.
   *   If the group does not yet exist it will be created as an AND group.
   * @param $field
   *   The name of the field to check.
   * @param $value
   *   The value to test the field against. In most cases, this is a scalar. For more
   *   complex options, it is an array. The meaning of each element in the array is
   *   dependent on the $operator.
   * @param $operator
   *   The comparison operator, such as =, <, or >=. It also accepts more
   *   complex options such as IN, LIKE, LIKE BINARY, or BETWEEN. Defaults to =.
   *   If $field is a string you have to use 'formula' here.
   *
   * @see \Drupal\Core\Database\Query\ConditionInterface::condition()
   * @see \Drupal\Core\Database\Query\Condition
   */
  public function addWhere($group, $field, $value = NULL, $operator = NULL) {
    // Ensure all variants of 0 are actually 0. Thus '', 0 and NULL are all
    // the default group.
    if (empty($group)) {
      $group = 0;
    }

    // Check for a group.
    if (!isset($this->where[$group])) {
      $this->setWhereGroup('AND', $group);
    }

    $this->where[$group]['conditions'][] = [
      'field' => $field,
      'value' => $value,
      'operator' => $operator,
    ];
  }
}
