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
     * The hierarchical tree of paths consisting of three values:
     * (id, weight, children) and children pointing to other triples.
     *
     * @var array
     */    
    protected $pathtree;
    
    public function getID() {
      return $this->id;
    }
    
    public function getName(){
      if(empty($this->name))
        return "Pathbuilder";
      return $this->name;
    }
                           
    public function setName($name){
      $this->name = $name;
    }
    
    public function getAdapter(){
      if(empty($this->adapter))
        return "Pathbuilder";
      return $this->adapter;
    }
                           
    public function setAdapter($adapter){
      $this->adapter = $adapter;
    }
                                    
    public function getPathTree(){
      return $this->pathtree;
    }
                                            
    public function setPathTree($pathtree){
      $this->pathtree = $pathtree;
    }
    
    public function addPathToPathTree($pathid) {
      $pathtree = $this->getPathTree();
      $pathtree[$pathid] = array('id' => $pathid, 'weight' => 0, 'enabled' => 0, 'children' => array());
      $this->setPathTree($pathtree);
      
      return true;      
    }
              
  } 
              