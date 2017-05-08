<?php
/**
 * @file
 * Contains \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity.
 */
    
namespace Drupal\wisski_pathbuilder\Entity;
    
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\wisski_pathbuilder\WisskiPathbuilderInterface;

/**
 * Defines a Pathbuilder configuration entity class
 * @ConfigEntityType(
 *   id = "wisski_pathbuilder",
 *   label = @Translation("WisskiPathbuilder"),
 *   fieldable = FALSE,
 *   handlers = {
 *	 "list_builder" = "Drupal\wisski_pathbuilder\Controller\WisskiPathbuilderListBuilder",
 *	 "form" = {
 *       "add" = "Drupal\wisski_pathbuilder\Form\WisskiPathbuilderForm",
 *       "add_existing" = "Drupal\wisski_pathbuilder\Form\WisskiPathbuilderAddExistingForm",
 *       "edit" = "Drupal\wisski_pathbuilder\Form\WisskiPathbuilderForm",
 *       "delete" = "Drupal\wisski_pathbuilder\Form\WisskiPathbuilderDeleteForm",
 *       "configure_field_form" = "Drupal\wisski_pathbuilder\Form\WisskiPathbuilderConfigureFieldForm",
 *     }
 *   },
 *   config_prefix = "wisski_pathbuilder",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "name" = "name"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/wisski/pathbuilder/{wisski_pathbuilder}/edit",
 *     "delete-form" = "/admin/config/wisski/pathbuilder/{wisski_pathbuilder}/delete",
 *     "overview" = "/admin/config/wisski/pathbuilder/{wisski_pathbuilder}/view"
 *   }
 * )
 */
class WisskiPathbuilderEntity extends ConfigEntityBase implements WisskiPathbuilderInterface {

  /**
   * represents the "generate/connect no field please" option in the path['field'] entry
   */
  const CONNECT_NO_FIELD = '1ae353e47a8aa3fc995220848780758a';
  
  /**
   * represents the "generate fresh field please" option in the path['field'] entry
   */
  const GENERATE_NEW_FIELD = 'ea6cd7a9428f121a9a042fe66de406eb';
  
  /**
   * The ID of the PB
   *
   * @var string
   */
  protected $id;

  /**
   * The name of the PB
   *
   * @var string
   */
  protected $name;

  /**
   * The machine-name of the adapter this pathbuilder belongs to
   *
   * @var string
   */
  protected $adapter;
  
  /**
   * The create mode for the pathbuilder
   *
   * @var string
   */
  protected $create_mode;

  /**
   * The hierarchical tree of paths consisting of two values:
   * (id, children) and children being an array pointing to other values.
   *
   * @var array
   */    
  protected $pathtree;
  
  /**
   * An array of Pathbuilderpaths. Typical Format is
   * (id, weight, enabled, parent, bundle, field)
   * where id is unique, parent is an id, bundle and field
   * are bundle and field ids.
   * The key in this array is the id.
   *
   * @var array
   */    
  protected $pbpaths;

/*    
  public function getID() {
    return $this->id;
  }
*/    
  public function getName(){
    return $this->name;
  }
                         
  public function setName($name){
    $this->name = $name;
  }
  
  public function getAdapterId(){
    return $this->adapter;
  }
                         
  public function setAdapterId($adapter){
    $this->adapter = $adapter;
  }
                                  
  public function getPathTree(){
    return $this->pathtree;
  }
  
  public function getPbPaths(){
    return $this->pbpaths;
  }
  
  public function hasPbPath($pathid) {
    return isset($this->pbpaths[$pathid]);
  }
  
  public function getPbPath($pathid){
    return $this->pbpaths[$pathid] ?: NULL;
  }
  
  public function setPbPaths($paths){
    $this->pbpaths = $paths;
  }
                                              
  public function setPathTree($pathtree){
    $this->pathtree = $pathtree;
  }
  
  public function setCreateMode($create_mode) {
    $this->create_mode = $create_mode;
  }
  
  public function getCreateMode() {
    return $this->create_mode;
  }
  
  public function generateCid($eid) {
    return 'wisski_pathbuilder:' . $eid;
  }
  
  /**
   * Get the Bundle Id for a given entity id.
   * If possible get it from cache, if not it is complicated.
   * For now we return NULL in this case.
   */    
  public function getBundleIdForEntityId($eid) {
  
    $cid = $this->generateCid($eid);
    
    $data = NULL;
        
#    drupal_set_message(serialize(\Drupal::cache()->get($cid)));
    if ($cache = \Drupal::cache()->get($cid)) {
      $data = $cache->data;
      return $data;
    }
    else {

      $bundle_from_uri = \Drupal::request()->query->get('wisski_bundle');

      if(!empty($bundle_from_uri)) {
        // cache resolving was not successfull
        // so we write it to the cache
        $this->setBundleIdForEntityId($eid, $bundle_from_uri);
        
        return $bundle_from_uri;
      }

      // still alive? make a best guess.
      $adapterid = $this->getAdapterId();
      $adapter = \Drupal\wisski_salz\Entity\Adapter::load($adapterid);
      
      $ids = $adapter->getBundleIdsForEntityId($eid);

      if(!empty($ids)) {
        
        $topids = \Drupal\wisski_core\WisskiHelper::getTopBundleIds();
        
        foreach($ids as $id) {
          if(in_array($id, $topids)) {
            // this is dangerous!
            $this->setBundleIdForEntityId($eid, $id);

            return $id;
          }
        }
        
        // if there is only one, return that.
        if(count($ids) == 1) {
          $id = current($ids);
          
          $this->setBundleIdForEntityId($eid, $id);

          return $id;
        }
        
      }

      drupal_set_message("No Bundle found for $eid - error.", "error");    
      return NULL;
#        $data = my_module_complicated_calculation();
#        \Drupal::cache()->set($cid, $data);
    }
  }
  
  /**
   * Set the Bundle id for a given entity
   */
  public function setBundleIdForEntityId($eid, $bundleid) {
#      dpm(serialize($eid), "setbundleid");
    $cid = $this->generateCid($eid);
    \Drupal::cache()->set($cid, $bundleid);
    return TRUE;
    
  }
  
  public function getBundle($pathid) {
    // get the pb-path
    $pbpath = $this->getPbPath($pathid);
    
    // if it is empty it is bad.
    if(empty($pbpath)) {
      drupal_set_message("No PB-Path found for $pathid.");
      return NULL;
    }
    
    if(empty($pbpath['parent']))
      return NULL;
    
    // get the parent of this path which probably is a group     
    $parentpbpath = $this->getPbPath($pbpath['parent']);
    
#      drupal_set_message(serialize($parentpbpath));
    
    // if it is empty it is bad.
    if(empty($parentpbpath)) {
      drupal_set_message("No Parent-PB-Path found for $pathid.");
      return NULL;
    }
    
    return $parentpbpath['bundle'];
    
  }
  
  /**
   * Generates the id for the bundle
   *
   */
  public function generateIdForBundle($group_id) {
    return 'b' . substr(md5($this->id() . '_' . $group_id . '_' . $this->getCreateMode()), 0, -1 );
  }
  
  /**
   * Generates the id for the bundle
   *
   */
  public function generateIdForField($path_id) {
    return 'f' . substr(md5($this->id() . '_' . $path_id . '_' . $this->getCreateMode() ), 0, -1);
  }
  
  /**
   * Generates the field in the bundle to link to a field collection as a
   * sub group if it was not already there. 
   *
   */
  public function generateFieldForSubGroup($pathid, $field_name, $orig_bundle) {
    #drupal_set_message("I am generating Fields for path " . $pathid . " and got " . $field_name . ". ");

    // get the bundle for this pathid
    $bundle = $this->getBundle($pathid); #$form_state->getValue('bundle');

    if(empty($bundle)) {
      return FALSE;
    }
    
    // if the field is already there...
    if(empty($field_name) || 
       !empty(\Drupal::entityManager()->getStorage('field_storage_config')->loadByProperties(array('field_name' => $field_name)))) {
      drupal_set_message(t('Field %bundle with id %id was already there.',array('%bundle'=>$field_name, '%id' => $field_name)));
#      $form_state->setRedirect('entity.wisski_pathbuilder.edit_form',array('wisski_pathbuilder'=>$this->id()));
      // get the pbpaths
#        $pbpaths = $this->getPbPaths();
      // set the path and the bundle - beware: one is empty!
#        $pbpaths[$pathid]['field'] = $field_name;
#        $pbpaths[$pathid]['bundle'] = $bundle;
#        // save it
#        $this->setPbPaths($pbpaths);
#      
#        $this->save();
      return;
    }
                
    //don't go on if the user whishes not to
    if ($orig_bundle === self::CONNECT_NO_FIELD) return;
    
    //create a new field if the user whishes to
    if ($orig_bundle === self::GENERATE_NEW_FIELD || empty($bundle) || empty($orig_bundle)) $fieldid = $this->generateIdForField($pathid);
    else $fieldid = $orig_bundle;


    // this is old
    //$fieldid = $this->generateIdForField($pathid);
    
    $type = $this->getCreateMode(); //'field_collection'

    $pbpaths = $this->getPbPaths(); 
    $card = isset($pbpaths[$pathid]['cardinality']) ? $pbpaths[$pathid]['cardinality'] : \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;

    // this was called field?
    $field_storage_values = [
      'field_name' => $fieldid,#$values['field_name'],
      'entity_type' =>  'wisski_individual',
      'type' => ($type == 'wisski_bundle') ? 'entity_reference' : 'field_collection',//has to fit the field component type, see below
      'translatable' => TRUE,
      'cardinality' => $card,
    ];
    
    if($type == 'wisski_bundle')
      $field_storage_values['settings']['target_type'] = 'wisski_individual';
  
    // this was called instance?
    $field_values = [
      'field_name' => $fieldid,
      'entity_type' => 'wisski_individual',
      'bundle' => $bundle,
      'label' => $field_name,
      // Field translatability should be explicitly enabled by the users.
      'translatable' => FALSE,
      'disabled' => FALSE,
    ];
    
    if($type == 'wisski_bundle') {
      $field_values['settings']['handler'] = "default:wisski_individual";
      $field_values['settings']['handler_settings']['target_bundles'][$this->generateIdForBundle($pathid)] = $this->generateIdForBundle($pathid);
      $field_values['field_type'] = "entity_reference";
    }

    // get the pbpaths
    $pbpaths = $this->getPbPaths();
    // set the path and the bundle - beware: one is empty!
    $pbpaths[$pathid]['field'] = $fieldid;
    #$pbpaths[$pathid]['bundle'] = $bundle;
    // save it
    $this->setPbPaths($pbpaths);
    
    $this->save();
  
    // if the field is already there...
    if(empty($field_name) || 
       !empty(\Drupal::entityManager()->getStorage('field_storage_config')->loadByProperties(array('field_name' => $fieldid)))) {
      drupal_set_message(t('Field %bundle with id %id was already there.',array('%bundle'=>$field_name, '%id' => $fieldid)));
#      $form_state->setRedirect('entity.wisski_pathbuilder.edit_form',array('wisski_pathbuilder'=>$this->id()));
      return;
    }

    \Drupal::entityManager()->getStorage('field_storage_config')->create($field_storage_values)->enable()->save();

    \Drupal::entityManager()->getStorage('field_config')->create($field_values)->save();

    $view_options = array(
      'type' => 'inline_entity_form_complex', #'entity_reference_autocomplete_tags',
      'settings' => array(
        'match_operator' => 'CONTAINS',
        'size' => '60',
        'placeholder' => '',
      ),
#        'type' => 'entity_reference_entity_view',//has to fit the field type, see above
#        'settings' => array('trim_length' => '200'),
#        'weight' => 1,//@TODO specify a "real" weight
      'weight' => $pbpaths[$pathid]['weight'],
    );
  
    $view_entity_values = array(
      'targetEntityType' => 'wisski_individual',
      'bundle' => $bundle,
      'mode' => 'default',
      'status' => TRUE,
    );
    
    $display_options = array(
      'type' => 'entity_reference_entity_view',
      'weight' => $pbpaths[$pathid]['weight'],
    );

    $display = \Drupal::entityManager()->getStorage('entity_view_display')->load('wisski_individual' . '.'.$bundle.'.default');
    if (is_null($display)) $display = \Drupal::entityManager()->getStorage('entity_view_display')->create($view_entity_values);
    $display->setComponent($fieldid,$display_options)->save();

    $form_display = \Drupal::entityManager()->getStorage('entity_form_display')->load('wisski_individual' . '.'.$bundle.'.default');
    if (is_null($form_display)) $form_display = \Drupal::entityManager()->getStorage('entity_form_display')->create($view_entity_values);
    $form_display->setComponent($fieldid, $view_options)->save();

    drupal_set_message(t('Created new field %field in bundle %bundle for this path',array('%field'=>$field_name,'%bundle'=>$bundle)));
  
  }

  
  /**
   * Generates the field for a given path in a given bundle if it
   * was not already there.
   *
   */
  public function generateFieldForPath($pathid, $field_name) {
    // get the bundle for this pathid
    $bundle = $this->getBundle($pathid); #$form_state->getValue('bundle');

#      drupal_set_message("I am generating Fields for path " . $pathid . " and got " . $field_name . " bundle " . serialize($bundle) . ". ");

    if(empty($bundle)) {
      return FALSE;
    }

   // if the create mode is field collection
   // create main groups as wisski bundle dingens
   // all other as field collections     
    if($this->getCreateMode() == 'field_collection') {
      $pbpaths = $this->getPbPaths();
      
      if(in_array($pbpaths[$pathid]['parent'], array_keys($this->getMainGroups())))
        $mode = 'wisski_individual';
      else
        $mode = 'field_collection_item';
    } else { # create everything as wisski individual things
      $mode = 'wisski_individual';
    }
    
    // if the field is already there...
    if(empty($field_name)) { # || I Just don't get why dorian does this here... field_name is nothing that we can find in there... 
       #!empty(\Drupal::entityManager()->getStorage('field_storage_config')->loadByProperties(array('field_name' => $field_name)))) {
      drupal_set_message(t('Field %bundle with id %id was already there.',array('%bundle'=>$field_name, '%id' => $field_name)));
#      $form_state->setRedirect('entity.wisski_pathbuilder.edit_form',array('wisski_pathbuilder'=>$this->id()));

      // get the pbpaths
      $pbpaths = $this->getPbPaths();
      // set the path and the bundle - beware: one is empty!
      $pbpaths[$pathid]['field'] = $field_name;
      $pbpaths[$pathid]['bundle'] = $bundle;
      // save it
      $this->setPbPaths($pbpaths);
      // do this accumulated in one session.      
      # $this->save();
      return;
    }      
    
    // get the pbpaths
    $pbpaths = $this->getPbPaths();

    $fieldid = $pbpaths[$pathid]['field'];
    
    //don't go on if the user whishes not to
    if ($fieldid === self::CONNECT_NO_FIELD) return;
    
    //create a new field if the user whishes to
    if ($fieldid === self::GENERATE_NEW_FIELD || empty($fieldid)) $fieldid = $this->generateIdForField($pathid);
    
    // danger catch!
    #if(empty($fieldid)) {
    #  drupal_set_message('I did not find a fieldid for path ' . $pathid . ' with name ' . $field_name, "error");
    #  return;
    #}
    
    // this was called field?
    $field_storage_values = [
      'field_name' => $fieldid,#$values['field_name'],
      'entity_type' =>  $mode,
      'type' => $pbpaths[$pathid]['fieldtype'], #'type' => 'text',//has to fit the field component type, see below
      'translatable' => TRUE,
    ];
  
    // this was called instance?
    $field_values = [
      'field_name' => $fieldid,
      'entity_type' => $mode,
      'bundle' => $bundle,
      'label' => $field_name,
      // Field translatability should be explicitly enabled by the users.
      'translatable' => FALSE,
      'disabled' => FALSE,
    ];
    
#      dpm($field_storage_values, 'fsv');
#      dpm($field_values, 'fv');
  

    // set the path and the bundle - beware: one is empty!
    $pbpaths[$pathid]['field'] = $fieldid;
    $pbpaths[$pathid]['bundle'] = $bundle;
    // save it
    $this->setPbPaths($pbpaths);
    
    // don't save anymore, save all at once.      
#      $this->save();

    //if there were problems with the field name, should not happen
    if(empty($field_name)) {
      drupal_set_message(t('Cannot create field for path %path_id without field name',array('5path_id'=>$pathid)),'error');
    }

    $create_fs = FALSE;
    // if the field is already there...
    $field_storages = \Drupal::entityManager()->getStorage('field_storage_config')->loadByProperties(
      array(
        'field_name' => $fieldid,
        //'entity_type' => $mode,
      )
    );

    if (!empty($field_storages)) {
      if (count($field_storages) > 1) drupal_set_message('There are multiple field storages for this field name: '.$field_name,'warning');
      $field_storage = current($field_storages);
      drupal_set_message(t('Field %bundle with id %id was already there.',array('%bundle'=>$field_name, '%id' => $fieldid)));
      //dpm($field_storage,'storage');
      if ($field_storage->getType() != $field_storage_values['type']) {
        $field_storage->delete();
        drupal_set_message(t('Field %bundle with id %id had to be deleted and recreated.',array('%bundle'=>$field_name, '%id' => $fieldid)), 'warning');
        $create_fs = TRUE;
      }
    } else $create_fs = TRUE;
          
    if ($create_fs) {
      drupal_set_message(t('Created new field %field in bundle %bundle for this path',array('%field'=>$field_name,'%bundle'=>$bundle)));
#        dpm($field_storage_values, 'fsv boc');
      $field_storage = \Drupal::entityManager()->getStorage('field_storage_config')->create($field_storage_values)->enable();
    } else { // if everything is there - why not skip? By Mark: I am unsure if this is a good idea.
    //   return;
    }
                  
    $card = isset($pbpaths[$pathid]['cardinality']) ? $pbpaths[$pathid]['cardinality'] : \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
    //dpm($field_storage->id(),'ID before');
    $field_storage->setCardinality($card);
    $field_storage->save();
    //dpm($field_storage->id(), 'ID after');
    
    $create_fo = FALSE;
    $field_objects = \Drupal::entityManager()->getStorage('field_config')->loadByProperties(
      array(
        'field_name'=>$fieldid,
        'bundle' => $bundle,
        'entity_type' => $mode,
      )
    );

    if (!empty($field_objects)) {
      foreach ($field_objects as $field_object) {
        //dpm($field_object,'field');
        if ($field_object->getType() != $field_storage_values['type']) {
          $create_fo = TRUE;
          //dpm(array($field_object->getType(),$field_storage_values['type']),'severe type differences');
          $field_object->delete();
        }
      }      
    } else $create_fo = TRUE;
    
    if ($create_fo) {
      $field_object = \Drupal::entityManager()->getStorage('field_config')->create($field_values);
    }
    
    //@TODO make it possible to set the $required value
    //$field_object->setRequired($required);
    $field_object->save();

    $view_options = array(
      // this might also be formatterwidget - I am unsure here. @TODO
      'type' => $pbpaths[$pathid]['displaywidget'], #'text_summary_or_trimmed',//has to fit the field type, see above
      'settings' => array(), #array('trim_length' => '200'),
      'weight' => $pbpaths[$pathid]['weight'],#'weight' => 1,//@TODO specify a "real" weight
    );
  
    $view_entity_values = array(
      'targetEntityType' => 'wisski_individual',
      'bundle' => $bundle,
      'mode' => 'default',
      'status' => TRUE,
    );

    // Special case for images
    // use medium as standard display
    // user can change this lateron      
    if(strpos($pbpaths[$pathid]['fieldtype'], 'image') !== FALSE) {
      if(strpos($pbpaths[$pathid]['formatterwidget'], 'wisski_iip_image') !== FALSE) {
        $display_options = array(
          'type' => $pbpaths[$pathid]['formatterwidget'],
          'settings' => array('colorbox_node_style' => 'medium', 'colorbox_image_style' => 'large'),
          'weight' => $pbpaths[$pathid]['weight'],
        );
      } else {
        $display_options = array(
          'type' => $pbpaths[$pathid]['formatterwidget'],
          'settings' => array('image_style' => 'medium'),
          'weight' => $pbpaths[$pathid]['weight'],
        );
      }
    } else {

      $display_options = array(
        'type' => $pbpaths[$pathid]['formatterwidget'],
        'settings' => array(),
        'weight' => $pbpaths[$pathid]['weight'],
      );
    }

    // find the current display elements
    $display = \Drupal::entityManager()->getStorage('entity_view_display')->load('wisski_individual' . '.'.$bundle.'.default');
    if (is_null($display)) $display = \Drupal::entityManager()->getStorage('entity_view_display')->create($view_entity_values);
    // setComponent enables them
    $display->setComponent($fieldid,$display_options)->save();

    // find the current form display elements
    $form_display = \Drupal::entityManager()->getStorage('entity_form_display')->load('wisski_individual' . '.'.$bundle.'.default');
    if (is_null($form_display)) $form_display = \Drupal::entityManager()->getStorage('entity_form_display')->create($view_entity_values);
    // setComponent enables them
    $form_display->setComponent($fieldid, $view_options)->save();      

  }
  
  /**
   * Generates a bundle for a given group if there was not already
   * one existing.
   *
   */
  public function generateBundleForGroup($groupid) {
    // what is the mode of the pb?
    if(in_array($groupid, array_keys($this->getMainGroups())))
      $mode = 'wisski_bundle';
    else
      $mode = $this->getCreateMode();

    // get all the pbpaths
    $pbpaths = $this->getPbPaths();
    
    // which group should I handle?
    $my_group = $pbpaths[$groupid];
    
    // if there is nothing we don't generate anything.
    if(empty($my_group))
      return FALSE;
      
    $bundleid = NULL;
    
    //don't go on if the user whishes not to
    if ($my_group['bundle'] === self::CONNECT_NO_FIELD) return;
    
#      //create a new field if the user whishes to
#      if ($my_group['bundle'] === self::GENERATE_NEW_FIELD || empty($bundle)) $fieldid = $this->generateIdForField($pathid);
    
    // if there is a bundle it still might be not there due to table clashes etc.
    if (!empty($my_group['bundle']) && $my_group['bundle'] !== self::GENERATE_NEW_FIELD) {

      // try to load it
      $bundleid = $my_group['bundle'];
      $bundles = \Drupal::entityManager()->getStorage($mode)->loadByProperties(array('id' => $bundleid));

      if (!empty($bundles)) {
        $bundle_object = current($bundles);
        $bundle_name = $bundle_object->label();
        drupal_set_message(t('Connected bundle %bundlelabel (%bundleid) with group %groupid.',array('%bundlelabel'=>$bundle_name, '%bundleid'=>$bundleid, '%groupid'=>$groupid)));
      } else {
        drupal_set_message(t('Could not connect bundle with id %bundleid with group %groupid. Generating new one.',array('%bundleid'=>$bundleid, '%groupid'=>$groupid)));
        // if there was nothing, reset the bundleid as it is wrong.
        $bundleid = NULL;
      }
    }
    
    // if we have a bundleid here, we can stop - if not we have to generate one.
    if(empty($bundleid)) {
    
      $my_real_group = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($groupid);
    
      // the name for the bundle is the id of the group
      $bundle_name = $my_real_group->getName();

      // generate a 32 char name
      $bundleid = $this->generateIdForBundle($groupid);

      // if the bundle is already there...
      if(empty($bundle_name) || !empty(\Drupal::entityManager()->getStorage($mode)->loadByProperties(array('id' => $bundleid)))) {
        drupal_set_message(t('Bundle %bundle with id %id was already there.',array('%bundle'=>$bundle_name, '%id' => $bundleid)));
        return;
      }

      // set the the bundle_name to the path
      $pbpaths[$groupid]['bundle'] = $bundleid;

      // save it
      $this->setPbPaths($pbpaths);
      $this->save();

      $bundle = \Drupal::entityManager()->getStorage($mode)->create(array('id'=>$bundleid, 'label'=>$bundle_name));
      $bundle->save();
      
      // disable entity name and uid for now everywhere @TODO perhaps subgroups only?
      $view_entity_values = array(
        'targetEntityType' => 'wisski_individual',
        'bundle' => $bundleid,
        'mode' => 'default',
        'status' => TRUE,
      );
      
      $evd = \Drupal::entityManager()->getStorage('entity_view_display')->load('wisski_individual.' . $bundleid . '.default');
      if (is_null($evd)) $evd = \Drupal::entityManager()->getStorage('entity_view_display')->create($view_entity_values);
      $efd = \Drupal::entityManager()->getStorage('entity_form_display')->load('wisski_individual.' . $bundleid . '.default');
      if (is_null($efd)) $efd = \Drupal::entityManager()->getStorage('entity_form_display')->create($view_entity_values);
      
      $evd->removeComponent('name');
      $evd->removeComponent('uid');
      $evd->save();
      
      $efd->removeComponent('name');
      $efd->removeComponent('uid');
      $efd->save();
      
      drupal_set_message(t('Created new bundle %bundle for group with id %groupid.',array('%bundle'=>$bundle_name, '%groupid'=>$groupid)));
    }
    
  }
  
  private function addDataToParentInTree($parentid, $data, $tree) {
    foreach($tree as $key => $branch) {
      // if there are children, search in them
      if(!empty($tree[$key]))
        $tree[$key]['children'] = $this->addDataToParentInTree($parentid, $data, $tree[$key]['children']);
      
      // we have the correct location, add the data!
      if($parentid == $key) {
        $tree[$key]['children'][$data['id']] = $data;
      }        
    }
    
    // return the tree!
    return $tree;
  }
  
  /**
   * Add a Pathid to the Pathtree
   * @TODO rename this to addPathIdToPathTree...
   * @param $pathid the id of the path to add
   *
   */    
  public function addPathToPathTree($pathid, $parentid = 0, $is_group = FALSE) {
    $pathtree = $this->getPathTree();
    $pbpaths = $this->getPbPaths();
#    drupal_set_message("yay!" . $pathid . " and " . $parentid);   
    #$pathtree[$pathid] = array('id' => $pathid, 'weight' => 0, 'enabled' => 0, 'children' => array(), 'bundle' => 0, 'field' => 0);
    #$pathtree[$pathid] = array('id' => $pathid, 'weight' => 0, 'enabled' => 0, 'children' => array(), 'bundle' => 'e21_person', 'field' => $pathid);

    if(empty($parentid))      
      $pathtree[$pathid] = array('id' => $pathid, 'children' => array());   
    else {
#      Drupal::logger("I add $pathid to $parentid.");
#      drupal_set_message("I add $pathid to $parentid.");
      // find the location in the pathtree
      // and add it there
      $pathtree = $this->addDataToParentInTree($parentid, array('id' => $pathid, 'children' => array()), $pathtree);
#      drupal_set_message(serialize($pathtree));
#      Drupal::logger(serialize($pathtree));
    }
    
    // if it is a group - we usually want to do entity reference if we are in wisski_bundle-mode
#      if($field_type == "group") {
#        if($this->getCreateMode() == 'wisski_bundle') 
#          $pbpaths[$pathid] = array('id' => $pathid, 'weight' => 0, 'enabled' => 0, 'parent' => 0, 'bundle' => '', 'field' => '', 'fieldtype' => '', 'displaywidget' => '', 'formatterwidget' => '');
#        else // we might need that later
#          $pbpaths[$pathid] = array('id' => $pathid, 'weight' => 0, 'enabled' => 0, 'parent' => 0, 'bundle' => '', 'field' => '', 'fieldtype' => '', 'displaywidget' => '', 'formatterwidget' => '');
#      } else if($field_type == "field_reference") {
#        // in any other case, we stick to string for the beginning
#        $pbpaths[$pathid] = array('id' => $pathid, 'weight' => 0, 'enabled' => 0, 'parent' => 0, 'bundle' => '', 'field' => '', 'fieldtype' => 'entity_reference', 'displaywidget' => 'inline_entity_form_complex', 'formatterwidget' => 'entity_reference_entity_view');
#      } else {
#        $pbpaths[$pathid] = array('id' => $pathid, 'weight' => 0, 'enabled' => 0, 'parent' => 0, 'bundle' => '', 'field' => '', 'fieldtype' => 'string', 'displaywidget' => 'string_textfield', 'formatterwidget' => 'string');
#      }

    if($is_group) {
      $pbpaths[$pathid] = array('id' => $pathid, 'weight' => 0, 'enabled' => 0, 'parent' => 0, 'bundle' => '', 'field' => '', 'fieldtype' => '', 'displaywidget' => '', 'formatterwidget' => '');
    } else {
      //these are the OLD default values, which make the PB create a textfield for every path
      //$pbpaths[$pathid] = array('id' => $pathid, 'weight' => 0, 'enabled' => 0, 'parent' => 0, 'bundle' => '', 'field' => '', 'fieldtype' => 'string', 'displaywidget' => 'string_textfield', 'formatterwidget' => 'string');
      //these new values keep the defaults for fieldtype, formatter and widget but will not result ion a generated field automatically
      $pbpaths[$pathid] = array('id' => $pathid, 'weight' => 0, 'enabled' => 0, 'parent' => 0, 'bundle' => '', 'field' => self::CONNECT_NO_FIELD, 'fieldtype' => 'string', 'displaywidget' => 'string_textfield', 'formatterwidget' => 'string');
    }
    
    $this->setPathTree($pathtree);
    $this->setPbPaths($pbpaths);
    
    return true;      
  }
  
  public function removePath($path_id) {
    
    $path_tree = $this->getPathTree();
    if (isset($path_tree[$path_id])) {
      unset($path_tree[$path_id]);
      $this->setPathTree($path_tree);
    }
         
    $pb_paths = $this->getPbPaths();
    if (isset($pb_paths[$path_id])) {
      unset($pb_paths[$path_id]);
      $this->setPbPaths($pb_paths);
    }
    
  }

  /**
   * Gets the main groups of the pathbuilder - usually what we are talking about.
   * @return An array of path objects that are groups
   */    
  public function getMainGroups() {
    $maingroups = array();
    
    // iterate through the path tree on the first level
    foreach($this->getPathTree() as $potmainpath) {
      // load the path
      $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($potmainpath["id"]);
      
      // if empty, go on.
      if(empty($path))
        continue;
      
      // if it is a group we want it.
      if($path->isGroup())
        $maingroups[$path->id()] = $path;
    }
    
    return $maingroups;
  }
  
  /**
   *
   * Returns all groups that are used in the pathbuilder
   * @return An array of path objects that are groups
   */
  public function getAllGroups() {
    $groups = array();
    
    if(empty($this->getPbPaths()))
      return array();
    
    // iterate through the paths array
    foreach($this->getPbPaths() as $potpath) {
      $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($potpath["id"]);
      
      if(empty($path))
        continue;
      
      // if it is a group - we want it
      if($path->isGroup())
        $groups[] = $path;
    }
    
    return $groups; 
  }
  
  /**
   *
   * Returns all paths that are used in the pathbuilder
   * @return An array of path objects that are paths
   */
  public function getAllPaths() {
    $paths = array();
    
    // iterate through the paths array
    foreach($this->getPbPaths() as $potpath) {
      $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($potpath["id"]);
      
      if(empty($path))
        continue;
      
      // if it is a group - we want it
      if(!$path->isGroup())
        $paths[] = $path;
    }
    
    return $paths; 
  }

  public function getAllPathsForBundleId($bundleid, $recursive) {
    $groups = $this->getGroupsForBundle($bundleid);
    
    $paths = array();
    
    foreach($groups as $group) {
      $paths = array_merge($paths, $this->getAllPathsForGroupId($group->id(), $recursive));
    }
    
    return $paths;
  }

  public function getAllPathsForGroupId($groupid, $recursive) {
    $paths = array();
          
    $subgps = array_filter($this->getPathsAndGroupsForGroupId($groupid));
    
    foreach($subgps as $subgp) {
  
      if($subgp->getType() == "Path")
        $paths[] = $subgp;
      else { // it is a group        
        if($recursive) {
          $paths = array_merge($paths, $this->getAllPathsForGroupId($subgp->id(), $recursive));
        }
      }
    }
    
    return $paths;
    
  }
  
  public function getPathsAndGroupsForGroupId($groupid) {
    $allpaths = $this->getPbPaths();
    
    $paths = array();
    
    foreach($allpaths as $path) {
      if((string) $path['parent'] === (string) $groupid) 
        $paths[] = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($path['id']);
    }

    return $paths;
    
  }
  
  /**
   *
   * Returns all groups and paths that are used in the pathbuilder
   * @return An array of path objects that are paths or groups
   */
  public function getAllGroupsAndPaths() {
    $paths = array();
    
    // iterate through the paths array
    foreach($this->getPbPaths() as $potpath) {
      $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($potpath["id"]);

      if(empty($path))
        continue;
      
      $paths[] = $path;
    }
    
    return $paths; 
  }
  
  public function getGroupsForBundle($bundleid) {
    $groups = $this->getAllGroups();
    $pbpaths = $this->getPbPaths();
    
    $outgroups = array();
    
    foreach($groups as $group) {
      if($pbpaths[$group->id()]['bundle'] == $bundleid)
        $outgroups[] = $group;
    }
    
    return $outgroups;
    
  }

  /**
   * Gets the real path-object for a given fieldid
   *
   * @return a path object
   */
  public function getPathForFid($fieldid) {      
    $pbpaths = $this->getPbPaths();
    
    foreach($pbpaths as $potpath) {
      
#        drupal_set_message(serialize($fieldid) . " = " . serialize($potpath['field']));
      if($fieldid == $potpath['field']) {
        $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($potpath["id"]);
        return $path;
      }
      
    }
    
    // nothing found?
    return array();
  }
  
  /**
   * If you want the array from the tree e.g. for the bundle etc. (what is pb-specific)
   * you have to use this one here. - If you just want the path you can use getPathForFid
   * 
   * @return an array consisting of the tree elements
   */    
  public function getPbEntriesForFid($fieldid) {
#      $return = NULL;
#      if($treepart == NULL)
#        $treepart = $this->getPathTree();
    $pbpaths = $this->getPbPaths();
    
    if(empty($pbpaths)) {
#      drupal_set_message("this pb has no paths!");
      return array();
    }
          
    foreach($pbpaths as $potpath) {
      
#        drupal_set_message(serialize($fieldid) . " = " . serialize($potpath['field']));
      if($fieldid == $potpath['field']) {
#          $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($potpath["id"]);
        
#          $path->bundle = $potpath['bundle'];
#          $path->enabled = $potpath['enabled'];
        return $potpath;
#          return $path;
      }
    }
    // nothing found?
    return array();
  }
  
  /**
   * Determine all image paths for a given bundle
   *
   * @return an array of path objects
   */
   
  public function getImagePathIDsForGroup($groupid, $recursive = true, $subtree = NULL) {
    $pbpaths = $this->getPbPaths();
     
    $group = $pbpaths[$groupid];
    
    if(empty($group))
      return array();
    
    $paths = array();
    
    if(empty($subtree)) {
      $parents = array();
      
      $to_look = $group;
      
      while($to_look && $to_look['parent']) {
        $parents = array_merge(array($to_look['parent']), $parents);
        
        $to_look = $pbpaths[$to_look['parent']];
      }

      $tmptree = $this->pathtree;
      $i = 0;
              
      foreach($parents as $parent) {
        $tmptree = $tmptree[$parent];
        
        $i++;
        
        if($i < count($parents))
          $tmptree = $tmptree['children'];
      }
      
      // special case - top group. then just descend one time.
      if(empty($parents))
        $tmptree = $tmptree[$groupid];
      
      $subtree = $tmptree;

    } 
                    
    foreach($subtree['children'] as $sub) {
       
      if(!empty($sub['children'])) {

        if($recursive)
          $paths = array_merge($paths, $this->getImagePathIDsForGroup($sub['id'], $recursive, $sub));

      } else {
      
        if(strpos($pbpaths[$sub['id']]['fieldtype'], 'image') !== FALSE) 
          $paths[] = $sub['id'];
        
      }
    }       
    
#      if(!empty($paths));
#      dpm($paths, "paths");
    
    return $paths;    
  }
  
  /**
   * Get the parent groupid for a given groupid
   *
   * @return False if not found, the groupid if something was found
   */
  public function getParentBundleId($bundleid) {
    $groups = $this->getGroupsForBundle($bundleid);  

    if(empty($groups))
      return NULL;
    
#      dpm($groupids);
  
    // @TODO we just use the first one here for now
    $group = current($groups);
    
    $parent = $this->getParentGroupId($group->id());
    
    $pbpaths = $this->getPbPaths();

    if(empty($pbpaths[$parent]))
      return NULL;
    
    return $pbpaths[$parent]['bundle'];
  
  }
  
  /**
   * Get the parent groupid for a given groupid
   *
   * @return False if not found, the groupid if something was found
   */
  public function getParentGroupId($groupid, $tree = NULL, $parent = FALSE) {
    if(empty($tree))
      $tree = $this->pathtree;
    
    foreach($tree as $sub) {
      if($sub['id'] == $groupid)
        return $parent;
      
      if(empty($sub['children']))
        continue;
      
      $subout = $this->getParentGroupId($groupid, $sub['children'], $sub['id']);
      
      if(!empty($subout))
        return $subout;
    }
    
    return FALSE;   
  }
  
  /**
   * Get the relative path part for the path
   * The parameter with_start_connection declares if the
   * connection to the parent path should be provided from this 
   * relative point.
   *
   * @return False if not found, the path if there is one
   */
  public function getRelativePath($path, $with_start_connection = TRUE) {
    if(empty($path))
      return;
    
    $path_array = $path->getPathArray();
    
    $allpbpaths = $this->getPbPaths();
    $pbarray = $allpbpaths[$path->id()];
    
    // main path - so return everything.
    if(empty($pbarray['parent']))
      return $path_array;
    
    $parentpath = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($pbarray["parent"]);
    
    $parent_path_array = $parentpath->getPathArray();
    
    $parent_count = count($parent_path_array);
    
    if(!$with_start_connection)
      $parent_count += 2;
    
    for($i=0; $i < ($parent_count -1); $i++) {
      unset($path_array[$i]);
    }
    
    return $path_array;
    
  }
  
  /**
   * Gets the starting position for the relative path part
   */
  public function getRelativeStartingPosition($path, $with_start_connection = TRUE) {
if (!$path) \Drupal::logger('bad wisski')->error(array_reduce(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 7), function($a, $b) {return "$a<br/>".$b['function'];}, ""));
    $path_length = count($path->getPathArray());
    $relative_path_length = count($this->getRelativePath($path, $with_start_connection));
    $starting_position = ($path_length - $relative_path_length) / 2;
    return $starting_position;
  }
} 
            
