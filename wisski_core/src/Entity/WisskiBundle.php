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
 *			 "delete_title" = "Drupal\wisski_core\Form\WisskiTitlePatternDeleteForm",
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
 *     "title-form" = "/admin/structure/wisski_core/{wisski_bundle}/title",
 *     "delete-title-form" = "/admin/structure/wisski_core/{wisski_bundle}/delete-title",
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
    dpm($title_pattern,'Saving title pattern for bundle '.$this->id);
    $this->title_pattern = serialize($title_pattern);
    return TRUE;
  }
  
  protected function isValidTitlePattern($title_pattern) {
    
    foreach($title_pattern as $key => $attributes) {
      if (!isset($attributes['type'])) return $this->showPatternError($key,'type',$this->t('not set'));
      if ($attributes['type'] === 'field') {
        if (empty($attributes['label'])) return $this->showPatternError($key,'label',$this->t('empty'));
        if (preg_match('/[^a-z0-9_]/',$attributes['label'])) return $this->showPatternError($key,'label',$this->t('invalid'));
        if (!in_array($attributes['cardinality'],array(-1,1,2,3))) return $this->showPatternError($key,'cardinality',$this->t('invalid'));
      } elseif ($attributes['type'] === 'text') {
        if (empty($attributes['label'])) return $this->showPatternError($key,'label',$this->t('empty'));
      } else return $this->showPatternError($key,'type',$this->t('invalid'));
      
    }
    return TRUE;
  }
  
  protected function showPatternError($key,$attribute,$type,$explanation=NULL) {
    $message = $this->t('The %attribute for %key is %type',array('%attribute' => $attribute,'%key'=>$key,'%type'=>$type));
    if (!empty($explanation)) $message .= ': '.$explanation;
    drupal_set_message($message,'error');
    return FALSE;
  }
}
