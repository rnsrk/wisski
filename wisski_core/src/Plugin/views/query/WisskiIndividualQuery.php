<?php

namespace Drupal\wisski_core\Plugin\views\query;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

use Drupal\wisski_salz\AdapterHelper;
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
   * The EntityQuery object used for the query.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface, \Drupal\wisski_salz\Query\WissKIQueryDelegator in our case
   */
  public $query;
  
  /**
   * The fields that should be returned explicitly by the query in the
   * ResultRow objects
   * 
   * @var array, keys and values are the field IDs
   */
  public $fields = [];

  /**
   * The order statements for the query
   * 
   * @var array
   */
  public $orderby;
  
  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->query = \Drupal::entityTypeManager()->getStorage('wisski_individual')->getQuery();
    $this->pager = $view->pager;  // TODO: do we need to set it here if pager is only inited in this->build()?
  }


  /**
   * Builds the necessary info to execute the query.
   */
  function build(ViewExecutable $view) {
    $view->initPager();

    // Let the pager modify the query to add limits.
    $this->pager = $view->pager;
    if ($this->pager) {
      $this->pager->query();
    }
    $count_query = clone $this->query;
    $count_query->count(true);

    $view->build_info['wisski_query'] = $this->query;
    $view->build_info['wisski_count_query'] = $count_query;
  }


  /**
   * We override this function as the standard field plugins use it.
   *
   * @param base_table not used
   * @param base_field the WisskiEntity entity query field
   *
   */
  function addField($base_table, $base_field) {
    $this->fields[$base_field] = $base_field;
    if (strpos($base_field, "wisski_path_") === 0) {
      // we always load the whole entity if the field is a path.
      // TODO: this is very slow when retrieving many entities; find a way to
      // get the field values without loading the entity.
      $this->fields['_entity'] = '_entity';
    }
    return $base_field;
  }
  
  
  /**
   * We override this function as the standard sort plugins use it
   *
   * @param table not used
   * @param field the WisskiEntity entity query field by which to sort
   * @param order sort order
   * @param alias not used
   * @param params not used
   */
  public function addOrderBy($table, $field = NULL, $order = 'ASC', $alias = '', $params = array()) {
    // $table is useless here
    if ($field) {
      $as = $this->addField($table, $field, $alias, $params);

      $this->orderby[] = array(
        'field' => $as, 
        'direction' => strtoupper($order),
      );
    }
    
  }

  /** This function is called by Drupal\views\Plugin\views\HandlerBase
  * maybe we should eventually break up the inheritance from there/QueryPluginBase if possible.
  */
  public function ensureTable($t, $r) {
    // do nothing
  }
  
  public function query($get_count = FALSE) {
    
    $query = clone $this->query;

    // Add the query tags.
    if (!empty($this->options['query_tags'])) {
      foreach ($this->options['query_tags'] as $tag) {
        $query->addTag($tag);
      }
    }
    
    if ($get_count) {
      $query->count(); 
      return $query;
    }

    
    if($this->orderby) {
      foreach($this->orderby as $elem) {
        $query->sort($elem['field'], $elem['direction']);
      }
    }

    return $query;

  }


  /**
   * Executes the query and fills the associated view object with according
   * values.
   *
   * Values to set: $view->result, $view->total_rows, $view->execute_time,
   * $view->pager['current_page'].
   */
  function execute(ViewExecutable $view) {
#  dpm($this->orderby, "orderby!");
#    dpm($view->field);
#dpm(microtime(), "first!");
wisski_tick();
wisski_tick("begin exec views");
    $query = $view->build_info['wisski_query'];
    $count_query = $view->build_info['wisski_count_query'];
    $args = $view->build_info['query_args'];

    $filter_regex = array();

    $bundle_ids = array();
    
    if(!empty($view->filter)) {
      foreach($view->filter as $key => $one_filter) {
        if($key == "bundle") {
          $bundle_ids = array_merge($bundle_ids, $one_filter->value);
        } else {
#          dpm(serialize($one_filter));
#          $filter_regex[$key][] = array('op' => $one_filter->operator, 'val' => $one_filter->value);
#          dpm($key, "key");
          $query->condition($key, $one_filter->value);
        }
      }
    }    

#    dpm($filter_regex);

    $query->addMetaData('view', $view);
    $count_query->addMetaData('view', $view);

    // Add the query tags.
    if (!empty($this->options['query_tags'])) {
      foreach ($this->options['query_tags'] as $tag) {
        $query->addTag($tag);
        $count_query->addTag($tag);
      }
    }
#    dpm($view, "view");
    $start = microtime(true);

    // if we are using the pager, calculate the total number of results
    if ($this->pager && $this->pager->usePager()) {
      try {
#        dpm(microtime(), "before count");
        //  Fetch number of pager items differently based on data locality.
        // Execute the local count query.
        $this->pager->total_items = $count_query->execute();
#        dpm(microtime(), "after count");
        if (!empty($this->pager->options['offset'])) {
          $this->pager->total_items -= $this->pager->options['offset'];
        }

        $this->pager->updatePageInfo();
      }
      catch (\Exception $e) {
        if (!empty($view->simpletest)) {
          throw($e);
        }
        // Show the full exception message in Views admin.
        if (!empty($view->live_preview)) {
          drupal_set_message($e->getMessage(), 'error');
        }
        else {
          drupal_set_message("Exception: " . $e->getMessage());
          // vpr does not exist?
          #vpr('Exception in @human_name[@view_name]: @message', array('@human_name' => $view->human_name, '@view_name' => $view->name, '@message' => $e->getMessage()));
        }
        return;
      }
    }

    // Let the pager set limit and offset.
    if ($this->pager) {
      $this->pager->preExecute($query);
    }
    
    // early opt out in case of no results
    if($this->pager->total_items == 0) {
      $view->result = [];
      $view->execute_time = microtime(true) - $start;
      return;
    }
    
    if($this->orderby) {
      foreach($this->orderby as $elem) {
        $query->sort($elem['field'], $elem['direction']);
      }
    }
 #   dpm(microtime(), "sec!");
    if (!empty($this->limit) || !empty($this->offset)) {
      // We can't have an offset without a limit, so provide a very large limit instead.
      $limit  = intval(!empty($this->limit) ? $this->limit : 999999999);
      $offset = intval(!empty($this->offset) ? $this->offset : 0);

      // Set the range for the query.
      // Set the range on the local query.
      $query->range($offset, $limit);
    }

    $view->result = array();
    try {
#      dpm(microtime(), "before ex");
 #     dpm($query, "query");
      // Execute the local query.
      $entity_ids = $query->execute();
#      dpm(microtime(), "after ex");
      
      if (empty($entity_ids)) {
        $view->result = [];
      }
      else {
#        dpm(microtime(), "before frv");
        // Get the fields for each entity, give it its ID, and then add to the result array.
        // This is later used for field rendering
        $values_per_row = $this->fillResultValues($entity_ids, $bundle_ids, $filter_regex);
#        dpm(microtime(), "after frv");
#dpm([$values_per_row, $entity_ids], __METHOD__);
        foreach ($values_per_row as $rowid => $values) {
          $row = new ResultRow($values);
          $row->index = $rowid;
          $view->result[] = $row;
        }
      }
      
      if ($this->pager) {
        $this->pager->postExecute($view->result);
        if ($this->pager->usePager()) {
          $view->total_rows = $this->pager->getTotalItems();
        }
      }
    }
    catch (\Exception $e) {
      // Show the full exception message in Views admin.
#      if (!empty($view->preview)) {
        drupal_set_message($e->getMessage(), 'error');
#      }
#      else {
#        vpr('Exception in @human_name[@view_name]: @message', array('@human_name' => $view->human_name, '@view_name' => $view->name, '@message' => $e->getMessage()));
#      }
      return;
    }
#    dpm(microtime(), "thrd!");

    $view->execute_time = microtime(true) - $start;
wisski_tick("end exec views");
  }

  
  protected function fillResultValues($entity_ids, $bundle_ids = array(), $filter_regex = array()) {
 #   dpm($bundle_ids, "this");

    $eid_to_uri_per_aid = [];


    // we must not load the whole entity unless explicitly wished. this is way too costly!
#    dpm(microtime(), "beginning of fill result values");
    $values_per_row = [];
    // we always return the entity id
    foreach ($entity_ids as $entity_id) {
      $values_per_row[$entity_id] = ['eid' => $entity_id];
    }

    $fields = $this->fields;
    #dpm($fields);
#    dpm(serialize($values_per_row));
    
#rpm($this->fields, "fields");
#    dpm(microtime(), "before load");
    $ids_to_load = array();
    if (isset($fields['_entity'])) {
      foreach ($values_per_row as &$row) {
        $ids_to_load[] = $row['eid'];
#        $row['_entity'] = entity_load('wisski_individual', $row['eid']);
      }
    }
    
    $loaded_ids = entity_load_multiple('wisski_individual', $ids_to_load);
#    dpm(serialize($ids_to_load));
#    dpm(serialize(entity_load(437)));
#    dpm(serialize($loaded_ids));
    if (isset($fields['_entity'])) {
      foreach ($values_per_row as &$row) {
        $row['_entity'] = $loaded_ids[$row['eid']];
      }
    }
#    dpm(serialize($values_per_row));

#    dpm($row, "row");
    
#    dpm(microtime(), "after load");
    
    unset($fields['eid']);
    unset($fields['_entity']);

    while (($field = array_shift($fields)) !== NULL) {
#      dpm(microtime(), "beginning one thing");
      if ($field == 'title') {
#        dpm(microtime(), "before generate");
        if(!empty($bundle_ids))
          $bid = reset($bundle_ids);
        else
          $bid = NULL;

        foreach ($values_per_row as $eid => &$row) {
          $row['title'] = wisski_core_generate_title($eid, FALSE, $bid);
        }
        
#        dpm(microtime(), "after generate title");
      }
      elseif ($field == 'preview_image') {
#        dpm("prew");
#        dpm(microtime(), "beginning image prev");        
#        dpm(\Drupal::entityTypeManager()->getStorage('wisski_individual'));
#        return;
        // prepare the listbuilder for external access.
        \Drupal::entityTypeManager()->getStorage('wisski_individual')->preparePreviewImages();
        
        foreach($values_per_row as $eid => &$row) {
#          dpm(microtime(), "ar " . serialize($bundle_ids));
          #$preview_image = WisskiCacheHelper::getPreviewImageUri($eid);
          if(empty($bundle_ids)) {
#            dpm("i have no bundle!");
            $bids = AdapterHelper::getBundleIdsForEntityId($row['eid'], TRUE);
          } else { // take the ones we have before.
            $bids = $bundle_ids;
          }
          $bid = reset($bids);
#          dpm(microtime(), "br");          
          $preview_image_uri = \Drupal::entityTypeManager()->getStorage('wisski_individual')->getPreviewImageUri($eid,$bid);
          

          if(strpos($preview_image_uri, "public://") !== FALSE) {
            $preview_image_uri = str_replace("public:/", \Drupal::service('stream_wrapper.public')->baseUrl(), $preview_image_uri);
          }

          global $base_path;
          $row['preview_image'] = '<a href="' . $base_path . 'wisski/navigate/'.$eid.'/view?wisski_bundle='.$bid.'"><img src="'. $preview_image_uri .'" /></a>';

        }
#        dpm(microtime(), "after preview image");
      }
      elseif ($field == 'bundle' || $field == 'bundle_label' || $field == 'bundles') {
#        dpm($values_per_row, "vpr");
        foreach ($values_per_row as $eid => &$row) {
          if(empty($bundle_ids))
            $bids = AdapterHelper::getBundleIdsForEntityId($row['eid'], TRUE);
          else
            $bids = $bundle_ids;
          $row['bundles'] = $bids;
          $bid = reset($bids);  // TODO: make a more sophisticated choice rather than the first one
          $row['bundle'] = $bid;
          $bundle = entity_load('wisski_bundle', $bid);
          $row['bundle_label'] = $bundle->label();
        }
#        dpm(microtime(), "after bundles");
      }
      elseif (strpos($field, "wisski_path_") === 0 && strpos($field, "__") !== FALSE) {
        // the if is rather a hack but currently I have no idea how to access
        // the field information wisski_field from WisskiEntityViewsData.
        
        $pb_and_path = explode("__", substr($field, 12), 2);
        if (count($pb_and_path) != 2) {
          drupal_set_message("Bad field id for Wisski views: $field", 'error');
        }
        else {
        
          $moduleHandler = \Drupal::service('module_handler');
          if (!$moduleHandler->moduleExists('wisski_pathbuilder')){
            return NULL;
          }
                            
        
          $pb = entity_load('wisski_pathbuilder', $pb_and_path[0]);
          $path = entity_load('wisski_path', $pb_and_path[1]);
          if (!$pb) {
            drupal_set_message("Bad pathbuilder id for Wisski views: $pb_and_path[0]", 'error');
          }
          elseif (!$path) {
            drupal_set_message("Bad path id for Wisski views: $pb_and_path[1]", 'error');
          }
          else {
            $adapter = entity_load('wisski_salz_adapter', $pb->getAdapterId());
            $aid = $adapter->id();
            if (!$adapter) {
              drupal_set_message("Bad adapter id for pathbuilder $pb_and_path[0]: " . $pb->getAdapterId(), 'error');
            }
            else {
              $engine = $adapter->getEngine();
              if (!($engine instanceof Sparql11EngineWithPB)) {
                drupal_set_message("Adapter cannot be queried by path in WissKI views for path " . $path->getName() . " in pathbuilder " . $pb->getName(), 'error');
              }
              else {
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
                    $uri = $engine->getUriForDrupalId($eid);
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

#                dpm($select, "select " . $path->getID() .': '.$path->getDatatypeProperty() );
#                dpm(microtime(), "before");
                $result = $engine->directQuery($select);

#                dpm([$select, $result], 'select' . $path->getID());

#                dpm(microtime(), "after");
                foreach ($result as $sparql_row) {
                  if (isset($uris_to_eids[$sparql_row->x0->getUri()])) {
#                    dpm($uris_to_eids[$sparql_row->x0->getUri()], $sparql_row->x0->getUri());
                    $eid = $uris_to_eids[$sparql_row->x0->getUri()];
                    if (!isset($sparql_row->$out_prop) || $sparql_row->$out_prop === NULL) {
                      \Drupal::logger('WissKI views')->warning("invalid reference slot {s} for path {pid}", ['s' => $out_prop, 'pid' => $path->getID()]);
                    }
                    elseif ($is_reference) {

                      $referenced_uri = $sparql_row->$out_prop->getUri();
                      $referenced_eid = AdapterHelper::getDrupalIdForUri($referenced_uri);
                      $referenced_title = wisski_core_generate_title($referenced_eid);
                      $values_per_row[$eid][$field][] = $referenced_title;
                      #$values_per_row[$eid][$field][] = $referenced_eid;
                    }
                    else {
                      if(!empty($disamb))
                        $values_per_row[$eid][$field][] = array('value' => $sparql_row->$out_prop->getValue(), 'wisskiDisamb' => $sparql_row->$disamb->getUri());
                      else
                        $values_per_row[$eid][$field][] = $sparql_row->$out_prop->getValue();
                    }
                  }
                }
#if ($field == 'wisski_path_sammlungsobjekt__91') rpm([$path, $result, $values_per_row], '91');
              }
            }
          }
        }
#        dpm(microtime(), "after field");
      }
    

    }
#    dpm(serialize($values_per_row[437]));
#    dpm(microtime(), "end of ...");    

    return array_values($values_per_row);

  }
}
