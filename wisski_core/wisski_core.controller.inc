<?php

/**
 * @file
 * Describe entity and controller custom classes.
 */

/**
 * Typical Entity 4 class.
 */
class WisskiCoreEntity extends Entity {
  /**
   * Define the label of the entity.
   */
  protected function defaultLabel() {
    if (!isset($this->title)) {
      return '';
    }
    return $this->title;
  }

  /**
   * Specify the default uri, which is picked up by uri() by default.
   */
  protected function defaultUri() {
    dpm($this);
    return array('path' => 'wisski_core/' . $this->identifier());
  }
}

/**
 * Typical Entity 4 controller class.
 */
class WisskiCoreEntityController extends EntityAPIController {

  public function load($ids = array(), $conditions = array()) {
  
//    $query_time = 0.0;
//    $query_count = &drupal_static('wisski_request_count');
//    $query_count = 0;
//    $test_time = 0.0;
//    $now = microtime();
    $entities = array();
    $bundle_type = isset($conditions['type']) ? $conditions['type'] : arg(1);
//    watchdog('wisski_entity_controller_load',serialize($ids));
    // This method takes an array of IDs, but our webservice only supports loading one entity at a time.
    
#    drupal_set_message(serialize($ids));
#    drupal_set_message(serialize($conditions));
    wisski_core_tick('start');
    $error = FALSE;
    if(!empty($ids)) {
      foreach ($ids as $id) {
        wisski_core_tick('ids');
        $types = wisski_salz_pb_get_entity_info($id);
        if ($types !== FALSE) {
          wisski_core_tick('entities');
          $entity_title = $id;
          $bundles = db_select('wisski_entity_bundles','b')
                    ->fields('b',array('type','uri','short_title_pattern'))
                    ->condition('type',$bundle_type)
                    ->execute()
                    ->fetchAllAssoc('uri');
//          dpm($bundles);
          wisski_core_tick('bundles');
          foreach($types as $bundle_uri) {
            if (isset($bundles[$bundle_uri])) {
              $bundle = $bundles[$bundle_uri];
              //TODO make short title
              $entity = entity_create("wisski_core_entity", array('type' => $bundle->type, 'title' => $entity_title, 'uri' => $id, 'id' => $id));
              $entity->is_new = FALSE;
              $entities[$id] = $entity;
            }
          }
        } else {
//          ddebug_backtrace();
//          trigger_error("Problems when loading entity $id",E_USER_ERROR);
          $error = TRUE;
        }
      } 
    } else {
      $entity_ids = array();
      // load all
      list(,$bundle_uri) = wisski_core_make_uri("",$bundle_type,FALSE); 
      $inds = wisski_salz_pb_get_bundle_info($bundle_uri);
      if ($inds !== FALSE) {
        foreach($inds as $ind_uri) {
          $entity_title = $ind_uri;
          //TODO make short titles
          $entity = entity_create("wisski_core_entity", array('type' => $conditions["type"], 'title' => $entity_title, 'uri' => $ind_uri, 'id' => $ind_uri));
          $entity->is_new = FALSE;
          $entities[$ind_uri] = $entity;
        }
      } else {
        dpm($bundle_type);
        trigger_error("Problems when loading bundle $bundle_uri",E_USER_ERROR);
      }
    }
    wisski_core_tick('full run');
    field_attach_load('wisski_core_entity',$entities);
    wisski_core_tick('field load');
    dpm(wisski_core_tick(NULL));
    if ($error) dpm($ids);
    return $entities;
  }
  
  
    
  public function create(array $values = array()) {

    if (!isset($values['uri']) && isset($values['type'])) {
      $values['uri'] = wisski_core_make_uri('',$values['type']);
    }
    $values += array('is_new' => TRUE);
//    dpm($values);
    $cache = cache_clear_all('wisski_cache_'.$values['type'],'cache');
    return (object) $values;
  }
  
  public function save($entity) {
  
//    field_attach_presave('wisski_core_entity', $entity);
//    field_attach_update('wisski_core_entity', $entity);
    if (!property_exists($entity,'title')) {
      dpm("\"title\" does not exist");
      if (!property_exists($entity,'uri')) {
        dpm("\"uri\" does not exist");
        list($entity_uri,$bundle_uri) = wisski_core_make_uri('',$entity->type,FALSE);
        if (property_exists($entity,'id')) $entity_uri = $entity->id;
      } else {
        $entity_uri = $entity->uri;
        list(,$bundle_uri) = wisski_core_make_uri('',$entity->type,FALSE);
      }
      $entity_title = $entity_uri;
    } else {
      $entity_title = $entity->title;
      if (!property_exists($entity,'uri')) {
        dpm("\"uri\" does not exist");
        list($entity_uri,$bundle_uri) = wisski_core_make_uri($entity_title,$entity->type,FALSE);
        if (property_exists($entity,'id')) $entity_uri = $entity->id;
      } else {
        $entity_uri = $entity->uri;
        list(,$bundle_uri) = wisski_core_make_uri($entity_title,$entity->type,FALSE);
      }
    }
    $inserted_title = "\"wisski_title:".preg_replace('/[:]/u','_',$entity_title)."\"";
    if (!wisski_salz_pb_insert_individual($entity_uri,$bundle_uri,$inserted_title)) trigger_error("Could not save ".$entity->title,E_USER_WARNING);
    field_attach_insert('wisski_core_entity', $entity);
  }


  /**
   * Build a structured array representing the entity's content.
   */
  public function buildContent($entity, $view_mode = 'full', $langcode = NULL, $content = array()) {
    

//    $wrapper = entity_metadata_wrapper('wisski_core_entity', $entity);

    // Make "title" and "description" properties themed like default fields.
    $content['title'] = array(
      '#theme' => 'field',
      '#weight' => 0,
      '#title' => t('Title'),
      '#access' => TRUE,
      '#label_display' => 'above',
      '#view_mode' => 'full',
      '#language' => LANGUAGE_NONE,
      '#field_name' => 'field_fake_title',
      '#field_type' => 'text',
      '#entity_type' => 'wisski_core_entity',
      '#bundle' => $entity->type,
      '#items' => array(array('value' => $entity->title)),
      '#formatter' => 'text_default',
      0 => array('#markup' => check_plain($entity->title)),
    );
    
    $content['uri'] = array(
      '#theme' => 'field',
      '#weight' => 0,
      '#title' => t('URI'),
      '#access' => TRUE,
      '#label_display' => 'above',
      '#view_mode' => 'full',
      '#language' => LANGUAGE_NONE,
      '#field_name' => 'field_fake_title',
      '#field_type' => 'text',
      '#entity_type' => 'wisski_core_entity',
      '#bundle' => $entity->type,
      '#items' => array(array('value' => $entity->uri)),
      '#formatter' => 'text_default',
      0 => array('#markup' => check_plain($entity->uri)),
    );
/*    
//    ddebug_backtrace();
    $content['description'] = array(
      '#theme' => 'field',
      '#weight' => 0,
      '#title' => t('Description'),
      '#access' => TRUE,
      '#label_display' => 'above',
      '#view_mode' => 'full',
      '#language' => LANGUAGE_NONE,
      '#field_name' => 'field_fake_description',
      '#field_type' => 'text',
      '#entity_type' => 'wisski_core_entity',
      '#bundle' => $entity->type,
      '#items' => array(array('value' => $entity->description)),
      
      //'#bundle' => $wrapper->type->value(),
      
      //'#items' => array(array('value' => $wrapper->description->value())),
      '#formatter' => 'text_default',
      0 => array('#markup' => check_plain($entity->description)),
      //0 => array('#markup' => check_plain($wrapper->description->value())),
    );

    $content['same_individuals'] = array(
      '#theme' => 'field',
      '#weight' => 0,
      '#title' => t('Same Individuals'),
      '#access' => TRUE,
      '#label_display' => 'above',
      '#view_mode' => 'full',
      '#language' => LANGUAGE_NONE,
      '#field_name' => 'field_fake_same_individuals',
      '#field_type' => 'text',
      '#entity_type' => 'wisski_core_entity',
      '#bundle' => $wrapper->type->value(),
      '#items' => array(array('value' => $wrapper->same_individuals->value())),
      '#formatter' => 'text_default',
    );*/
    return parent::buildContent($entity, $view_mode, $langcode, $content);
  }
/*  
  public function save($entity, DatabaseTransaction $transaction = NULL) {
    if (!property_exists($entity,'name') || $entity->name == '') $entity->name = preg_replace('/[^a-zA-Z0-9_]/','_',substr($entity->title,0,32));
    $return = parent::save($entity,$transaction);
    if ($return)
      return $return;
    else {
      trigger_error("Error when trying to save $entity",E_USER_WARNING);
      return FALSE;
    }
  }
  */
}

function wisski_core_tick($key) {
    
    static $ticks;
    static $last_tick;
    if (!isset($ticks)) {
      $ticks = &drupal_static('wisski_controller_tick');
      if (!isset($ticks)) $ticks = array();
      if ($key && !isset($ticks[$key])) $ticks[$key] = 0.0;
    }
    if ($key === NULL) {
      foreach ($ticks as &$tick) {
        $tick = round($tick,4,PHP_ROUND_HALF_UP);
      }
      return $ticks;
    }
    if (!isset($last_tick)) $last_tick = microtime();
    else {
      $new_tick = microtime();
      $ticks[$key] += wisski_core_getMicroseconds($last_tick,$new_tick);
      $last_tick = $new_tick;
    }
  }
  
function wisski_core_getMicroseconds($start,$end) {
  
    $st = explode(' ',$start);
    $nd = explode(' ',$end);
    $secs = (float) ($nd[1] - $st[1]);
    $st_mysecs = (float) $st[0];
    $nd_mysecs = (float) $nd[0];
    if ($secs) $nd_mysecs += $secs;
    return $nd_mysecs - $st_mysecs;
  }

/*
function wisski_core_entity_uri($entity) {
  
  return property_exists($entity,'uri') ? $entity->uri : '-undefined-';
}

function wisski_core_entity_label($entity) {
  
  return property_exists($entity,'title') ? $entity->title : (property_exists($entity,'type') ? t('undefined').' '.$entity->type : '-undefined-');
}
*/

class WisskiCoreBundle extends Entity {
  public $type;
  public $label;
  public $weight = 0;

  /**
   * Overridden constructor.
   */
  public function __construct($values = array()) {
    parent::__construct($values, 'wisski_core_bundle');
  }

  public function __toString() {
  
    return $this->type.": ".$this->label;
  }

  /**
   * Determine if the entity is locked.
   */
  public function isLocked() {
    return isset($this->status) && empty($this->is_new) && (($this->status & ENTITY_IN_CODE) || ($this->status & ENTITY_FIXED));
  }
}

/**
 * Typical Entity 6 Type controller class.
 */
class WisskiCoreBundleController extends EntityAPIControllerExportable {
  /**
   * Rebuild menu after editing entity bundles.
   */
  public function save($entity, DatabaseTransaction $transaction = NULL) {
    parent::save($entity, $transaction);
    // Rebuild menu registry.
    variable_set('menu_rebuild_needed', TRUE);
    field_attach_create_bundle('wisski_core_entity',$this);
  }
}

/**
 * Typical Entity 6 Type UI controller class.
 */
class WisskiCoreBundleUiController extends EntityDefaultUIController {
  /**
   * Override hook_menu() defaults.
   */
  public function hook_menu() {
    $items = parent::hook_menu();
    $items[$this->path]['description'] = 'Manage WissKI Core Bundles';
    return $items;
  }
}
