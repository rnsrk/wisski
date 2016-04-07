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
      if(empty($this->name))
        return "Pathbuilder";
      return $this->name;
    }
                           
    public function setName($name){
      $this->name = $name;
    }
    
    public function getAdapterId(){
      if(empty($this->adapter))
        return "Pathbuilder";
      return $this->adapter;
    }
                           
    public function setAdapterId($adapter){
      $this->adapter = $adapter;
    }
                                    
    public function getPathTree(){
      return $this->pathtree;
    }
    
    public function getPbPaths($pathid){
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
    
    public function getBundle($pathid) {
      // get the pb-path
      $pbpath = $this->getPbPath($pathid);
      
      // if it is empty it is bad.
      if(empty($pbpath)) {
        drupal_set_message("No PB-Path found for $pathid.");
        return NULL;
      }
      
      // get the parent of this path which probably is a group     
      $parentpbpath = $this->getPbPath($pbpath['parent']);
      
      // if it is empty it is bad.
      if(empty($parentpbpath)) {
        drupal_set_message("No Parent-PB-Path found for $pathid.");
        return NULL;
      }
      
      return $parentpbpath['bundle'];
      
    }
    
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
    
    public function getMainGroups() {
      $maingroups = array();
      foreach($this->getPathTree() as $potmainpath) {
        $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($potmainpath["id"]);
        
#        drupal_set_message(serialize($potmainpath["id"]));
        
        if($path->isGroup())
          $maingroups[] = $path;
      }
      
      return $maingroups;
      #drupal_set_message(serialize($this->getPathTree()));
    }
    
    public function getAllGroups($treepart = NULL) {
      if($treepart == NULL)
        $treepart = $this->getPathTree();
      
      $groups = array();
      
      foreach($treepart as $potpath) {
        $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($potpath["id"]);
        
        if($path->isGroup())
          $groups = $path;
          
        if(!empty($treepart['children']))
          $groups = array_merge($groups, $this->getAllGroups($treepart['children']));
      }
      
      return $groups;
      
    }
    
    public function getGroupsForBundle($bundleid) {
      $groups = $this->getAllGroups();
      
      $outgroups = array();
      
      foreach($groups as $group) {
        if($group['bundle'] == $bundleid)
          $outgroups[] = $group;
      }
      
      return $outgroups;
      
    }
    
    public function getPathForFid($fieldid, $treepart = NULL) {
      /*
      $return = NULL;
      if($treepart == NULL)
        $treepart = $this->getPathTree();
      */
      
      $pbpaths = $this->getPbPaths();
      
      foreach($pbpaths as $potpath) {
        
#        drupal_set_message(serialize($fieldid) . " = " . serialize($potpath['field']));
        if($fieldid == $potpath['field']) {
          $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($potpath["id"]);
          return $path;
        }
        
      }
      
      // nothing found?
      return NULL;
    }
    
    /**
     * If you want the array from the tree e.g. for the bundle etc. (what is pb-specific)
     * you have to use this one here. - If you just want the path you can use getPathForFid
     * 
     * @return an array consisting of the tree elements
     */    
    public function getPbEntriesForFid($fieldid, $treepart = NULL) {
#      $return = NULL;
#      if($treepart == NULL)
#        $treepart = $this->getPathTree();
      $pbpaths = $this->getPbPaths();
      
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
      return NULL;
    }
                  
  } 
              