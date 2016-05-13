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
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\wisski_core\Form\WisskiBundleForm",
 *       "edit" = "Drupal\wisski_core\Form\WisskiBundleForm",
 *			 "delete" = "Drupal\wisski_core\Form\WisskiBundleDeleteForm",
 *     },
 *     "list_builder" = "Drupal\wisski_core\Controller\WisskiBundleListBuilder",
 *		 "access" = "Drupal\wisski_core\Controller\WisskiBundleAccessHandler",
 *   },
 *   admin_permission = "administer wisski_core",
 *   config_prefix = "bundle",
 *   bundle_of = "wisski_individual",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *		 "description" = "description",
 *   },
 *   links = {
 *     "edit-form" = "/admin/structure/wisski_core/{wisski_bundle}/edit",
 *     "delete-form" = "/admin/structure/wisski_core/{wisski_bundle}/delete",
 *     "entity-list" = "/admin/structure/wisski_core/{wisski_bundle}/list",
 *		 "list" = "/admin/structure/wisski_core",
 *   }
 * )
 */
class WisskiBundle extends ConfigEntityBundleBase implements WisskiBundleInterface {
  
  private $title_pattern_string = '';
  private $title_pattern_matches = array();
  
  public function getTitlePattern() {
    
    return array($this->title_pattern_string,$this->title_pattern_matches);
  }
  
  public function setTitlePattern($title_pattern) {
    
    if (!$this->isValidTitlePattern($title_pattern)) return FALSE;
    $this->title_pattern = $title_pattern;
    return TRUE;
  }
  
  protected function isValidTitlePattern($title_pattern) {
  
    if (empty($title_pattern)) {
      $this->title_pattern_string = '';
      $this->title_pattern_matches = array();
      return TRUE;
    }
    $regex = '\%(\w+)\%';
    $matches = array();
    if (preg_match_all('/'.$regex.'/',$title_pattern,$matches)) {
      $available_names = array_keys(\Drupal::entityManager()->getFieldStorageDefinitions('wisski_individual',$this->id));
      $diff = array_diff($matches[1],$available_names);
      dpm($matches,'Matches');
      if (empty($diff)) {
        $this->title_pattern_matches = $matches[1];
        return TRUE;
      } else {
        drupal_set_message(t('Inavlid field names used: %names',array('%names' => implode(', ',$diff))),'error');
        return FALSE;
      }
    }
    return TRUE;
  }
}
