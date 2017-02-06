<?php

namespace Drupal\wisski_core\Plugin\views\query;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
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
dpm(func_get_args(),__METHOD__);
    $this->fields[$base_field] = $base_field;
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
wisski_tick("pre exec pager");
        $this->pager->total_items = $count_query->execute();
wisski_tick("post exec pager");

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


wisski_tick("pre exec views");
      // Execute the local query.
      $entity_ids = $query->execute();
wisski_tick("post exec views");

      // Load each entity, give it its ID, and then add to the result array.
      // This is later used for field rendering
      $i = 0;
      foreach (entity_load_multiple("wisski_individual", $entity_ids) as $entity_id => $entity) {
        // TODO: we must not load the whole entity. this is way too costly!
        #$entity->entity_id = $entity_id;
        #$entity->entity_type = $entity_type;
        $values = ['eid' => $entity_id];
        if (isset($this->fields['title'])) {
          $values['title'] = wisski_core_generate_title($entity);
        }
        $row = new ResultRow($values);
        $row->index = $i++;
        $row->_entity = $entity;
        $view->result[] = $row;
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
dpm([microtime(true) - $start, $view->result],'result '.__METHOD__);    
wisski_tick("end exec views");
  }

  function get_result_entities($results, $relationship = NULL) {
    $entity = reset($results);
    return array($entity->entity_type, $results);
  }
  function add_selector_orderby($selector, $order = 'ASC') {
    $views_data = views_fetch_data($this->base_table);
    $sort_data = $views_data[$selector]['sort'];
    switch ($sort_data['handler']) {
      case 'efq_views_handler_sort_entity':
        $this->query->entityOrderBy($selector, $order);
        break;
      case 'efq_views_handler_sort_property':
        $this->query->propertyOrderBy($selector, $order);
        break;
      case 'efq_views_handler_sort_field':
        $this->query->fieldOrderBy($sort_data['field_name'], $sort_data['field'], $order);
        break;
    }
  }
}
