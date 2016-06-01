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
    
    public function getPbPath($pathid){
      return $this->pbpaths[$pathid];
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
      if ($cache = \Drupal::cache()->get($cid)) {
        $data = $cache->data;
        return $data;
      }
      else {
        return NULL;
#        $data = my_module_complicated_calculation();
#        \Drupal::cache()->set($cid, $data);
      }
    }
    
    /**
     * Set the Bundle id for a given entity
     */
    public function setBundleIdForEntityId($eid, $bundleid) {
      
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
      return 'b' . substr(md5($this->id() . '_' . $parent_bundle . '_' . $group_id . '_' . $this->getCreateMode()), 0, -1 );
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
    public function generateFieldForSubGroup($pathid, $field_name) {
#      drupal_set_message("I am generating Fields for path " . $pathid . " and got " . $field_name . ". ");

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
      
      
      $fieldid = $this->generateIdForField($pathid);
      
      $type = $this->getCreateMode(); //'field_collection'
      
      // this was called field?
      $field_storage_values = [
        'field_name' => $fieldid,#$values['field_name'],
        'entity_type' =>  'wisski_individual',
        'type' => ($type == 'wisski_bundle') ? 'entity_reference' : 'field_collection',//has to fit the field component type, see below
        'translatable' => TRUE,
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
      );
    
      $view_entity_values = array(
        'targetEntityType' => 'wisski_individual',
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      );
      
      $display_options = array(
        'type' => 'entity_reference_entity_view',
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
      if(empty($field_name) || 
         !empty(\Drupal::entityManager()->getStorage('field_storage_config')->loadByProperties(array('field_name' => $field_name)))) {
        drupal_set_message(t('Field %bundle with id %id was already there.',array('%bundle'=>$field_name, '%id' => $field_name)));
  #      $form_state->setRedirect('entity.wisski_pathbuilder.edit_form',array('wisski_pathbuilder'=>$this->id()));
        // get the pbpaths
        $pbpaths = $this->getPbPaths();
        // set the path and the bundle - beware: one is empty!
        $pbpaths[$pathid]['field'] = $field_name;
        $pbpaths[$pathid]['bundle'] = $bundle;
        // save it
        $this->setPbPaths($pbpaths);
      
        $this->save();
        return;
      }
      
      
      $fieldid = $this->generateIdForField($pathid);
      // get the pbpaths
      $pbpaths = $this->getPbPaths();

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
    

      // set the path and the bundle - beware: one is empty!
      $pbpaths[$pathid]['field'] = $fieldid;
      $pbpaths[$pathid]['bundle'] = $bundle;
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
        // this might also be formatterwidget - I am unsure here. @TODO
        'type' => $pbpaths[$pathid]['fieldtype'], #'text_summary_or_trimmed',//has to fit the field type, see above
        'settings' => array(), #array('trim_length' => '200'),
        'weight' => 1,//@TODO specify a "real" weight
      );
    
      $view_entity_values = array(
        'targetEntityType' => 'wisski_individual',
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      );

      // find the current display elements
      $display = \Drupal::entityManager()->getStorage('entity_view_display')->load('wisski_individual' . '.'.$bundle.'.default');
      if (is_null($display)) $display = \Drupal::entityManager()->getStorage('entity_view_display')->create($view_entity_values);
      // setComponent enables them
      $display->setComponent($fieldid,$view_options)->save();

      // find the current form display elements
      $form_display = \Drupal::entityManager()->getStorage('entity_form_display')->load('wisski_individual' . '.'.$bundle.'.default');
      if (is_null($form_display)) $form_display = $display = \Drupal::entityManager()->getStorage('entity_form_display')->create($view_entity_values);
      // setComponent enables them
      $form_display->setComponent($fieldid)->save();

      drupal_set_message(t('Created new field %field in bundle %bundle for this path',array('%field'=>$field_name,'%bundle'=>$bundle)));
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
      $my_real_group = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($groupid);
      
      // if there is nothing we don't generate anything.
      if(empty($my_group))
        return FALSE;
              
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
      drupal_set_message(t('Created new bundle %bundle for group with id %groupid.',array('%bundle'=>$bundle_name, '%groupid'=>$groupid)));
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
      
      #$pathtree[$pathid] = array('id' => $pathid, 'weight' => 0, 'enabled' => 0, 'children' => array(), 'bundle' => 0, 'field' => 0);
      #$pathtree[$pathid] = array('id' => $pathid, 'weight' => 0, 'enabled' => 0, 'children' => array(), 'bundle' => 'e21_person', 'field' => $pathid);

      if(empty($parentid))      
        $pathtree[$pathid] = array('id' => $pathid, 'children' => array());   
      else {
        // find the location in the pathtree
        // and add it there
        $pathtree = $this->addDataToParentInTree($parentid, array('id' => $pathid, 'children' => array()), $pathtree);
      
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
        $pbpaths[$pathid] = array('id' => $pathid, 'weight' => 0, 'enabled' => 0, 'parent' => 0, 'bundle' => '', 'field' => '', 'fieldtype' => 'string', 'displaywidget' => 'string_textfield', 'formatterwidget' => 'string');
      }
      
      $this->setPathTree($pathtree);
      $this->setPbPaths($pbpaths);
      
      return true;      
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
      
      if(empty($pbpaths))
        return array();
            
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
     
    public function getImagePathIDsForGroup($groupid, $recursive = true) {
      $pbpaths = $this->getPbPaths();
       
      $group = $pbpaths[$groupid];
       
      $paths = array();
       
      if(empty($group))
        return array();
         
      foreach($group as $potpath) {
         
        if(!empty($potpath['children']) && $recursive)
          $paths = array_merge($paths, $this->getImagePathsForGroup($potpath['id'], $recursive));
        else {
        
          if(empty($potpath['field']))
            continue;
          
          $field = \Drupal\field\Entity\FieldStorageConfig::loadByName('wisski_individual', $potpath['field']);#->getItemDefinition()->mainPropertyName();
          
          if(strpos('image', $field->getType()))
           $paths[] = $potpath;
          
        }

      }       
      return $paths;    
    }
                        
  } 
              