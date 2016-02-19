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
  *       "delete" = "Drupal\wisski_pathbuilder\Form\WisskiPathDeleteForm",
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
  *     "edit-form" = "/admin/config/wisski/path/{wisski_path}",
  *     "delete-form" = "/admin/config/wisski/path/{wisski_path}/delete",
  *   }        
  *  )
  */
class WisskiPathEntity extends ConfigEntityBase implements WisskiPathInterface {
 
     /**
      * The ID of the path
      *
      * @var string
      */
  public $id;
  /**
   * The human readable name of the path
   *
   * @var string
   */
  public $name;
   
   /**
    * The position weight of the path
    *
    * @var int
    */
  public $weight;                                                       
                             
}
     