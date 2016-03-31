<?php
/**
 * @file
 * Contains \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity.
 */
   
namespace Drupal\wisski_pathbuilder\Entity;
  
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
#use Drupal\wisski_pathbuilder\WisskiPathbuilderInterface;
use Drupal\wisski_pathbuilder\WisskiPathInterface;
   
 /**
  * Defines the Wisski Path entity.
  * The Wisski Path entity stores information about 
  * a path of the wisski pathbuilder.
  * @ConfigEntityType(
  *   id = "wisski_path",
  *   label = @Translation("WisskiPath"),
  *   fieldable = FALSE,
  *   handlers = {
  *     "list_builder" = "Drupal\wisski_pathbuilder\Controller\WisskiPathListBuilder",
  *     "form" = {
  *       "add" = "Drupal\wisski_pathbuilder\Form\WisskiPathForm",
  *       "edit" = "Drupal\wisski_pathbuilder\Form\WisskiPathForm",
  *       "delete" = "Drupal\wisski_pathbuilder\Form\WisskiPathDeleteForm"
  *     }             
  *    },
  *   config_prefix = "wisski_path",
  *   admin_permission = "administer site configuration",
  *   entity_keys = {
  *     "id" = "id",
  *     "label" = "name",
  *     "weight" = "weight"
  *   },
  *   links = {
  *     "edit-form" = "/admin/config/wisski/pathbuilder/{wisski_pathbuilder}/path/{wisski_path}",
  *     "delete-form" = "/admin/config/wisski/pathbuilder/{wisski_pathbuilder}/path/{wisski_path}/delete",
  *     "entity-list" = "/admin/structure/wisski_core/{wisski_bundle}/list"
  *   }        
  *  )
  */
class WisskiPathEntity extends ConfigEntityBase implements WisskiPathInterface {
 
  /**
   * The ID of the path
   *
   * @var string
   */
  protected $id;
  
  /**
   * The human readable name of the path
   *
   * @var string
   */
  protected $name;
  
  /**
   * The path array containing the complete path structure 
   * beginning with its starting concept, 
   * followed by the property-concept pairs ending with a concept.
   *
   * @var string
   */
  protected $path_array;
  
  /**
   * The datatype property of the path
   *
   * @var string
   */
  protected $datatype_property;
  
  /**
   * The short name of the path
   *
   * @var string
   */
  protected $short_name;
  
  /**
   * The integer value as position number of the disambiguation
   * drop down list array 
   *
   * @var int
   */
  protected $disamb;
  
  /**
   * The length of the path
   *
   * @var int
   */
  protected $length;
  
  /**
   * The description text of the path
   *
   * @var string
   */
  protected $description;
                                                                                                                  
   /**
    * The position weight of the path
    *
    * @var int
    */
  protected $weight;
  
   /**
    * "Group" if this path is a group
    " "SmartGroup" if this path is a SmartGroup
    * "Path" if this path is a regular path
    *
    * @var string
    */
  protected $type;
  
  /**
    * True if this path is a enabled, false otherwise.
    *
    * @var boolean
    */
  protected $enabled;

  public function getID(){
    return $this->id;
  }
  
  public function setID($id){
    $this->id = $id;
  }
         
  public function getName(){
    return $this->name;
  }
  
  public function setName($name){
    $this->name = $name;
  }
         
  public function getPathArray(){
    return $this->path_array;  
  }
  
  public function setPathArray($path_array){
    $this->path_array = $path_array;
  }
          
  public function getDatatypeProperty(){
    return $this->datatype_property;
  }
  
  public function setDatatypeProperty($datatype_property){
    $this->datatype_property = $datatype_property;
  }
         
  public function getShortName(){
    return $this->short_name;
  }
  
  public function setShortName($short_name){
    $this->short_name = $short_name;
         }
                       
  public function getDisamb(){
    return $this->disamb;
  }
  
  public function setDisamb($disamb){
    $this->disamb = $disamb;
  }
                                    
  public function getLength(){
    return $this->length;
  }
  
  public function setLength($length){
    $this->length = $length;
  }
         
  public function getDescription(){
    return $this->description;
  }
  
  public function setDescription($description){
    $this->description = $description;
  }
               
  public function isGroup(){
    if($this->type == "Group" || $this->type == "SmartGroup")
      return true;
    else
      return false;
  }
  
  public function getType(){
    return $this->type;
  }
  
  public function setType($type){
    $this->type = $type;
  }
         
  public function isEnabled(){
    return $this->enabled;
  }
  
  public function setEnabled($enabled){
    $this->enabled = $enabled;
  }
 
#  public function getWeight(){
#    return $this->weight;
#  }
  
  public function printPath(){
    $out = "";
      
    foreach($this->getPathArray() as $step) {
      $out .= empty($out) ? $step : ' -> ' . $step;
    }
        
    return $out;
  }
                                       
}
    