<?php

namespace Drupal\wisski_core\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\wisski_core\WisskiBundleInterface;

/**
 * Defines the bundle configuration entity.
 *
 * @ConfigEntityType(
 *   id = "wisski_bundle",
 *   label = @Translation("Wisski Bundle"),
 *	 fieldable = FALSE,
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\wisski_core\Form\WisskiBundleForm",
 *       "edit" = "Drupal\wisski_core\Form\WisskiBundleForm",
 *			 "delete" = "Drupal\wisski_core\Form\WisskiBundleDeleteForm",
 *       "title" = "Drupal\wisski_core\Form\WisskiTitlePatternForm",
 *     },
 *     "list_builder" = "Drupal\wisski_core\Controller\WisskiBundleListBuilder",
 *     "access" = "Drupal\wisski_core\Controller\WisskiBundleAccessHandler",
 *   },
 *   admin_permission = "administer wisski_core",
 *   config_prefix = "wisski_bundle",
 *   bundle_of = "wisski_individual",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "description" = "description",
 *   },
 *   links = {
 *     "edit-form" = "/admin/structure/wisski_core/{wisski_bundle}/edit",
 *     "delete-form" = "/admin/structure/wisski_core/{wisski_bundle}/delete",
 *     "entity-list" = "/admin/structure/wisski_core/{wisski_bundle}/list",
 *     "list" = "/admin/structure/wisski_core",
 *   }
 * )
 */
class WisskiBundle extends ConfigEntityBundleBase implements WisskiBundleInterface {
  
  /**
   * The field based pattern for the entity title generation
   * @var string
   */
  protected $title_pattern = '';
  
  public function getTitlePattern() {
    
    return unserialize($this->title_pattern);
  }
  
  public function removeTitlePattern() {
    $this->title_pattern = '';
  }
  
  public function setTitlePattern($title_pattern) {
    dpm($this->entityManager()->getStorage($this->entityTypeId));
    if (!$this->isValidTitlePattern($title_pattern)) return FALSE;
    drupal_set_message('Saving title pattern for bundle '.$this->id.' '.serialize($title_pattern));
    $this->title_pattern = serialize($title_pattern);
    return TRUE;
  }
  
  protected function isValidTitlePattern($title_pattern) {
  
    return TRUE;
  }
}
