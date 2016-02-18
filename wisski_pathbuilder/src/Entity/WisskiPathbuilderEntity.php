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
   *   controllers = {
   *	 "list_builder" = "Drupal\wisski_pathbuilder\WisskiPathbuilderListBuilder",
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
   *     "label" = "name"
   *   },
   *   links = {
   *     "edit-form" = "wisski_pathbuilder.edit",
   *     "delete-form" = "wisski_pathbuilder.delete",
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
   
  } 
                                                                                                                                                                                                                                                                            
                                                                                                                                                                                                                                                                                  /**
                                                                                                                                                                                                                                                                                         * The number of petals.
                                                                                                                                                                                                                                                                                                *
                                                                                                                                                                                                                                                                                                       * @var int
                                                                                                                                                                                                                                                                                                              */
                                                                                                                                                                                                                                                                                                                    public $petals;
                                                                                                                                                                                                                                                                                                                     
                                                                                                                                                                                                                                                                                                                           /**
                                                                                                                                                                                                                                                                                                                                  * The season in which this flower can be found.
                                                                                                                                                                                                                                                                                                                                         *
                                                                                                                                                                                                                                                                                                                                                * @var string
                                                                                                                                                                                                                                                                                                                                                       */
                                                                                                                                                                                                                                                                                                                                                             public $season;
                                                                                                                                                                                                                                                                                                                                                              
                                                                                                                                                                                                                                                                                                                                                                  }