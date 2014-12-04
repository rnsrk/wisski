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
    return array('path' => 'wisski_core/' . $this->identifier());
  }
}

/**
 * Typical Entity 4 controller class.
 */
class WisskiCoreEntityController extends EntityAPIController {

  public function load($ids = array(), $conditions = array()) {
    $entities = array();
    // This method takes an array of IDs, but our webservice only supports loading one entity at a time.
    
#    drupal_set_message(serialize($ids));
#    drupal_set_message(serialize($conditions));
    if(!empty($ids)) {
      foreach ($ids as $id) {
        
#        drupal_set_message(serialize($ids));
#        drupal_set_message(serialize($conditions));
      $query = "SELECT DISTINCT ?o WHERE { GRAPH ?g { $id rdf:type ?o } }";
      
#      drupal_set_message(htmlentities($query));
      
      $store = wisski_salz_invoke_local_store();
      
      list($ok,$result) =  $store->querySPARQL($query);
      
#      drupal_set_message(serialize($result));        
      foreach($result as $key => $value) {
      
        #$entity_ids[] = $value->s->dumpValue('text');
        $entity = entity_create("wisski_core_entity", array('type' => $value->o->dumpValue('text'), 'title' => $id, 'uri' => $id, 'id' => $id));
#        drupal_set_message(serialize($entity));
        $entities[] = $entity;
      }  
   
  //  drupal_set_message("remote controller: " . serialize($id));
    // This function should contain all the code to make a request to the web service and handle any errors.
    #if ($entity = remote_web_service_load($id)) {
      // Entities must be keyed by entity ID in order for field values to be correctly attached
    #  $entities[$entity->my_remote_entity_id] = $entity;
    #}
      }
    } else {
    
      $entity_ids = array();
      // load all
 #     drupal_set_message(serialize($conditions));
      
      list($ind_uri,$bundle_uri) = wisski_core_make_uri("",$conditions["type"],FALSE);
      
       
      $query = "SELECT DISTINCT ?s WHERE { ?s a " . $bundle_uri . " }";
            
#      drupal_set_message(htmlentities($query));
            
       $store = wisski_salz_invoke_local_store();
      
      list($ok,$result) =  $store->querySPARQL($query);
#      $ind = $ind["EasyRdf_Sparql_Result"];


#      drupal_set_message(serialize($result));
      
      foreach($result as $key => $value) {
        #$entity_ids[] = $value->s->dumpValue('text');
        $entity = entity_create("wisski_core_entity", array('type' => $conditions["type"], 'title' => $value->s->dumpValue('text'), 'uri' => $value->s->dumpValue('text'), 'id' => $value->s->dumpValue('text')));
#        drupal_set_message(serialize($entity));
        $entities[] = $entity;
      }
      
#      drupal_set_message(serialize($entities));
      
    }
                                                   
    return $entities;
  }
  
  public function save($entity) {
    field_attach_presave('wisski_core_entity', $entity);
    field_attach_update('wisski_core_entity', $entity);
    
//    drupal_set_message(serialize($entity));
#    module_load_include('php', 'wisski_salz', "adapters/sparql11/SPARQL11Adapter");
#    $out = wisski_salz_pb_update("sdfsd","sdsdfsdf",array(),array(),array(),FALSE)

#    drupal_set_message(serialize($entity));

    $store = wisski_salz_invoke_local_store();

#    drupal_set_message(serialize($store));

    list($ind_uri,$bundle_uri) = wisski_core_make_uri($entity->title,$entity->type,FALSE);
//    $query = "INSERT DATA { <http://www.bla.de/" . $entity->type . "_" . rand(0,9999999999999999) . "> a <http://www.bla.de/" . $entity->type . "> }";

//    $ind = $store->updateSPARQL($query);  
    if (wisski_salz_pb_insert_individual($ind_uri,$bundle_uri)) dpm("Success");
     #   $ind = $store->createNewIndividual($entity->type);
    
#    drupal_set_message(serialize($ind));
    
  }


  /**
   * Build a structured array representing the entity's content.
   */
  public function buildContent($entity, $view_mode = 'full', $langcode = NULL, $content = array()) {
    

    $wrapper = entity_metadata_wrapper('wisski_core_entity', $entity);

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
      '#bundle' => $wrapper->type->value(),
      '#items' => array(array('value' => $wrapper->title->value())),
      '#formatter' => 'text_default',
      0 => array('#markup' => check_plain($wrapper->title->value())),
    );
    
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
/*    
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
