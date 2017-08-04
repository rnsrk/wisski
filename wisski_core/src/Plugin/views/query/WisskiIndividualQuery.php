<?php

namespace Drupal\wisski_core\Plugin\views\query;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\wisski_salz\AdapterHelper;
use Drupal\wisski_core\WisskiCacheHelper;
use Drupal\wisski_core\Controller\WisskiEntityListBuilder;
use Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->query = \Drupal::entityTypeManager()->getStorage('wisski_individual')->getQuery();
    $this->tables = array();
    $this->pager = $view->pager;
  }

  function option_definition() {
    $options = parent::option_definition();
    $options['field_language'] = array(
      'default' => '***CURRENT_LANGUAGE***',
    );
    $options['query_tags'] = array(
      'default' => array(),
    );
    return $options;
  }

  /**
   * Show field language settings if the entity type we are querying has
   * field translation enabled.
   * If we are querying multiple entity types, then the settings are shown
   * if at least one entity type has field translation enabled.
   */
  function options_form(&$form, &$form_state) {
    if (isset($this->entity_type)) {
      $entities = array();
      $entities[$this->entity_type] = entity_get_info($this->entity_type);
    }
    else {
      $entities = entity_get_info();
    }

    $has_translation_handlers = FALSE;
    foreach ($entities as $type => $entity_info) {
      if (!empty($entity_info['translation'])) {
        $has_translation_handlers = TRUE;
      }
    }

    if ($has_translation_handlers) {
      $languages = array(
        '***CURRENT_LANGUAGE***' => t("Current user's language"),
        '***DEFAULT_LANGUAGE***' => t("Default site language"),
        LANGUAGE_NONE => t('No language')
      );
      $languages = array_merge($languages, locale_language_list());

      $form['field_language'] = array(
        '#type' => 'select',
        '#title' => t('Field Language'),
        '#description' => t('All fields which support translations will be displayed in the selected language.'),
        '#options' => $languages,
        '#default_value' => $this->options['field_language'],
      );
    }

    $form['query_tags'] = array(
      '#type' => 'textfield',
      '#title' => t('Query Tags'),
      '#description' => t('If set, these tags will be appended to the query and can be used to identify the query in a module. This can be helpful for altering queries.'),
      '#default_value' => implode(', ', $this->options['query_tags']),
      '#element_validate' => array('views_element_validate_tags'),
    );

    // The function views_element_validate_tags() is defined here.
    form_load_include($form_state, 'inc', 'views', 'plugins/views_plugin_query_default');
  }

  /**
   * Special submit handling.
   */
  function options_submit(&$form, &$form_state) {
    $element = array('#parents' => array('query', 'options', 'query_tags'));
    $value = explode(',', drupal_array_get_nested_value($form_state['values'], $element['#parents']));
    $value = array_filter(array_map('trim', $value));
    form_set_value($element, $value, $form_state);
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
   * This is used by the style row plugins for node view and comment view.
   */
  function addField($base_table, $base_field) {
#    dpm($this->fields, "fields in addfield");
    $this->fields[$base_field] = $base_field;
    if (strpos($base_field, "wisski_path_") === 0) {
      $this->fields['_entity'] = '_entity';
    }
#    dpm($this->fields, "fields in addfield2");
    return $base_field;
  }


  /** This function is called by Drupal\views\Plugin\views\HandlerBase
  * maybe we should eventually break up the inheritance from there/QueryPluginBase if possible.
  */
  public function ensureTable($t, $r) {
    // do nothing
  }


  /**
   * Executes the query and fills the associated view object with according
   * values.
   *
   * Values to set: $view->result, $view->total_rows, $view->execute_time,
   * $view->pager['current_page'].
   */
  function execute(ViewExecutable $view) {
#    dpm($view->field);
wisski_tick();
wisski_tick("begin exec views");
    $query = $view->build_info['wisski_query'];
    $count_query = $view->build_info['wisski_count_query'];
    $args = $view->build_info['query_args'];

    $query->addMetaData('view', $view);
    $count_query->addMetaData('view', $view);

    // Add the query tags.
    if (!empty($this->options['query_tags'])) {
      foreach ($this->options['query_tags'] as $tag) {
        $query->addTag($tag);
        $count_query->addTag($tag);
      }
    }

    $start = microtime(true);

    // Determine if the query entity type is local or remote.
    $remote = FALSE;
    

    // if we are using the pager, calculate the total number of results
    if ($this->pager && $this->pager->usePager()) {
      try {

        //  Fetch number of pager items differently based on data locality.
        // Execute the local count query.
        $this->pager->total_items = $count_query->execute();

        if (!empty($this->pager->options['offset'])) {
          $this->pager->total_items -= $this->pager->options['offset'];
        }

        $this->pager->updatePageInfo();
      }
      catch (Exception $e) {
        if (!empty($view->simpletest)) {
          throw($e);
        }
        // Show the full exception message in Views admin.
        if (!empty($view->live_preview)) {
          drupal_set_message($e->getMessage(), 'error');
        }
        else {
          vpr('Exception in @human_name[@view_name]: @message', array('@human_name' => $view->human_name, '@view_name' => $view->name, '@message' => $e->getMessage()));
        }
        return;
      }
    }

    // Let the pager set limit and offset.
    if ($this->pager) {
      $this->pager->preExecute($query);
    }

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

      // Execute the local query.
      $entity_ids = $query->execute();

      if (empty($entity_ids)) {
        $view->result = [];
      }
      else {
        // Get the fields for each entity, give it its ID, and then add to the result array.
        // This is later used for field rendering
        $values_per_row = $this->fillResultValues($entity_ids);
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
    catch (Exception $e) {
      // Show the full exception message in Views admin.
      if (!empty($view->preview)) {
        drupal_set_message($e->getMessage(), 'error');
      }
      else {
        vpr('Exception in @human_name[@view_name]: @message', array('@human_name' => $view->human_name, '@view_name' => $view->name, '@message' => $e->getMessage()));
      }
      return;
    }

    $view->execute_time = microtime(true) - $start;
wisski_tick("end exec views");
  }

  
  protected function fillResultValues($entity_ids) {
    // we must not load the whole entity unless explicitly wished. this is way too costly!
    
    $values_per_row = [];
    // we always return the entity id
    foreach ($entity_ids as $entity_id) {
      $values_per_row[$entity_id] = ['eid' => $entity_id];
    }

    $fields = $this->fields;

    
#    dpm($this, "fields");

    if (isset($fields['_entity'])) {
      foreach ($values_per_row as &$row) {
        $row['_entity'] = entity_load('wisski_individual', $row['eid']);
      }
    }
    
    unset($fields['eid']);
    unset($fields['_entity']);

    while (($field = array_shift($fields)) !== NULL) {

      if ($field == 'title') {
        foreach ($values_per_row as $eid => &$row) {
          $row['title'] = wisski_core_generate_title($eid);
        }
      }
      elseif ($field == 'preview_image') {
#        dpm("prew");
        foreach($values_per_row as $eid => &$row) {
          #$preview_image = WisskiCacheHelper::getPreviewImageUri($eid);
          $bundle_ids = AdapterHelper::getBundleIdsForEntityId($row['eid'], TRUE);
          $bid = reset($bundle_ids);
          
          $preview_image_uri = WisskiEntityListBuilder::getPreviewImageUri($eid,$bid);

          if(strpos($preview_image_uri, "public://") !== FALSE)
            $preview_image_uri = str_replace("public:/", \Drupal::service('stream_wrapper.public')->baseUrl(), $preview_image_uri);

          $row['preview_image'] = '<a href="http://va.gnm.de/posse/wisski/navigate/'.$eid.'/view"><img src="'. $preview_image_uri .'" /></a>';
        }
      }
      elseif ($field == 'bundle' || $field == 'bundle_label' || $field == 'bundles') {
#        dpm($values_per_row, "vpr");
        foreach ($values_per_row as $eid => &$row) {
          $bundle_ids = AdapterHelper::getBundleIdsForEntityId($row['eid'], TRUE);
          $row['bundles'] = $bundle_ids;
          $bid = reset($bundle_ids);  // TODO: make a more sophisticated choice rather than the first one
          $row['bundle'] = $bid;
          $bundle = entity_load('wisski_bundle', $bid);
          $row['bundle_label'] = $bundle->label();
        }

      }
      elseif (strpos($field, "wisski_path_") === 0 && strpos($field, "__") !== FALSE) {
        // the if is rather a hack but currently I have no idea how to access
        // the field information wisski_field from WisskiEntityViewsData.
        
        $pb_and_path = explode("__", substr($field, 12), 2);
        if (count($pb_and_path) != 2) {
          drupal_set_message("Bad field id for Wisski views: $field", 'error');
        }
        else {
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
            if (!$adapter) {
              drupal_set_message("Bad adapter id for pathbuilder $pb_and_path[0]: " . $pb->getAdapterId(), 'error');
            }
            else {
              $engine = $adapter->getEngine();
              if (!($engine instanceof Sparql11EngineWithPB)) {
                drupal_set_message("Adapter cannot be queried by path in WissKI views for path " . $path->getName() . " in pathbuilder " . $pb->getName(), 'error');
              }
              else {
                $select = "SELECT ?x0 ?out WHERE { VALUES ?x0 { ";
                $uris_to_eids = []; // keep for reverse mapping of results
                foreach ($entity_ids as $eid) {
                  $uri = $engine->getUriForDrupalId($eid);
                  if ($uri) {
                    $select .= "<$uri> ";
                    $uris_to_eids[$uri] = $eid;
                  }
                }
                $select .= "} ";
                // NOTE: we need to set the $relative param to FALSE. All other
                // optional params should be default values
                $select .= $engine->generateTriplesForPath($pb, $path, "", NULL, NULL, 0, 0, FALSE, '=', 'field', FALSE);
                $select .= "}";
                $result = $engine->directQuery($select);
                foreach ($result as $sparql_row) {
                  if (isset($uris_to_eids[$sparql_row->x0->getUri()])) {
                    $eid = $uris_to_eids[$sparql_row->x0->getUri()];
                    $values_per_row[$eid][$field][] = $sparql_row->out->getValue();
                  }
                }
              }
            }
          }
        }
      }

    }
    
    return array_values($values_per_row);

  }
}
