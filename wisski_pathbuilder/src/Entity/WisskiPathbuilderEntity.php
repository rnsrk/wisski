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
    public function generateIdForBundle($bundle_name) {
      return md5('b_' . $this->id() . '_' . $bundle_name);
    }
    
    /**
     * Generates the id for the bundle
     *
     */
    public function generateIdForField($field_name) {
      return 'field_' . substr(md5('b_' . $this->id() . '_' . $field_name), 6);
    }
    
    /**
     * Generates the field for a given path in a given bundle if it
     * was not already there.
     *
     */
    public function generateFieldForPath($pathid, $field_name) {

      drupal_set_message("I am generating Fields for path " . $pathid . " and got " . $field_name . ". ");
      // get the bundle for this pathid
      $bundle = $this->getBundle($pathid); #$form_state->getValue('bundle');

      if(empty($bundle)) {
        return FALSE;
      }
      
      $fieldid = $this->generateIdForField($field_name);
      
      // this was called field?
      $field_storage_values = [
        'field_name' => $fieldid,#$values['field_name'],
        'entity_type' => 'wisski_individual',
        'type' => 'text',//has to fit the field component type, see below
        'translatable' => TRUE,
      ];
    
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
    

      // get the pbpaths
      $pbpaths = $this->getPbPaths();
      // set the path and the bundle - beware: one is empty!
      $pbpaths[$pathid]['field'] = $fieldid;
      $pbpaths[$pathid]['bundle'] = $bundle;
      // save it
      $this->setPbPaths($pbpaths);
      
      $this->save();
    
      // if the field is already there...
      if(empty($field_name) || !empty(\Drupal::entityManager()->getStorage('field_storage_config')->loadByProperties(array('field_name' => $fieldid)))) {
        drupal_set_message(t('Field %bundle with id %id was already there.',array('%bundle'=>$field_name, '%id' => $fieldid)));
  #      $form_state->setRedirect('entity.wisski_pathbuilder.edit_form',array('wisski_pathbuilder'=>$this->id()));
        return;
      }

      // bundle?
      \Drupal::entityManager()->getStorage('field_storage_config')->create($field_storage_values)->enable()->save();

      // path?
      \Drupal::entityManager()->getStorage('field_config')->create($field_values)->save();

      $view_options = array(
        'type' => 'text_summary_or_trimmed',//has to fit the field type, see above
        'settings' => array('trim_length' => '200'),
        'weight' => 1,//@TODO specify a "real" weight
      );
    
      $view_entity_values = array(
        'targetEntityType' => 'wisski_individual',
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      );

      $display = \Drupal::entityManager()->getStorage('entity_view_display')->load('wisski_individual.'.$bundle.'.default');
      if (is_null($display)) $display = \Drupal::entityManager()->getStorage('entity_view_display')->create($view_entity_values);
      $display->setComponent($field_id,$view_options)->save();

      $form_display = \Drupal::entityManager()->getStorage('entity_form_display')->load('wisski_individual.'.$bundle.'.default');
      if (is_null($form_display)) $form_display = $display = \Drupal::entityManager()->getStorage('entity_form_display')->create($view_entity_values);
      $form_display->setComponent($field_id)->save();

      drupal_set_message(t('Created new field %field in bundle %bundle for this path',array('%field'=>$field_name,'%bundle'=>$bundle)));
    }
    
    /**
     * Generates a bundle for a given group if there was not already
     * one existing.
     *
     */
    public function generateBundleForGroup($groupid) {
      // get all the pbpaths
      $pbpaths = $this->getPbPaths();
      
      // which group should I handle?
      $my_group = $pbpaths[$groupid];
      
      // if there is nothing we don't generate anything.
      if(empty($my_group))
        return FALSE;
              
      // the name for the bundle is the id of the group
      $bundle_name = $my_group['id'];

      // generate a 32 char name
      $bundleid = $this->generateIdForBundle($bundle_name);

      // if the bundle is already there...
      if(empty($bundle_name) || !empty(\Drupal::entityManager()->getStorage('wisski_bundle')->loadByProperties(array('id' => $bundleid)))) {
        drupal_set_message(t('Bundle %bundle with id %id was already there.',array('%bundle'=>$bundle_name, '%id' => $bundleid)));
        return;
      }

      // set the the bundle_name to the path
      $pbpaths[$groupid]['bundle'] = $bundleid;

      // save it
      $this->setPbPaths($pbpaths);
      $this->save();

      $bundle = \Drupal::entityManager()->getStorage('wisski_bundle')->create(array('id'=>$bundleid, 'label'=>$bundle_name));
      $bundle->save();
      drupal_set_message(t('Created new bundle %bundle for this group.',array('%bundle'=>$bundle_name)));
    }
    
    /**
     * Add a Pathid to the Pathtree
     * @TODO rename this to addPathIdToPathTree...
     * @param $pathid the id of the path to add
     *
     */    
    public function addPathToPathTree($pathid) {
      $pathtree = $this->getPathTree();
      $pbpaths = $this->getPbPaths();
      
      #$pathtree[$pathid] = array('id' => $pathid, 'weight' => 0, 'enabled' => 0, 'children' => array(), 'bundle' => 0, 'field' => 0);
      #$pathtree[$pathid] = array('id' => $pathid, 'weight' => 0, 'enabled' => 0, 'children' => array(), 'bundle' => 'e21_person', 'field' => $pathid);
      
      $pathtree[$pathid] = array('id' => $pathid, 'children' => array());
      $pbpaths[$pathid] = array('id' => $pathid, 'weight' => 0, 'enabled' => 0, 'parent' => 0, 'bundle' => '', 'field' => '');
      
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
        
        // if it is a group we want it.
        if($path->isGroup())
          $maingroups[] = $path;
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
      return array();;
    }
                  
  } 
              