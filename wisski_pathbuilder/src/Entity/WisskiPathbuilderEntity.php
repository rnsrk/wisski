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
    public $id;

    /**
     * The name of the PB
     *
     * @var string
     */
    public $name;

    /**
     * The paths of the PB
     *
     * @var array
     */    
    public $paths;
   
  } 
              