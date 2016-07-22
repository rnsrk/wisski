<?php

namespace Drupal\wisski_core\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\wisski_core\WisskiBundleInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

use Drupal\wisski_core\WisskiCacheHelper;

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
 *     "entity-list" = "/wisski/navigate/{wisski_bundle}",
 *     "list" = "/admin/structure/wisski_core",
 *     "title-form" = "/admin/structure/wisski_core/{wisski_bundle}/title",
 *     "delete-title-form" = "/admin/structure/wisski_core/{wisski_bundle}/delete-title",
 *   }
 * )
 */
class WisskiBundle extends ConfigEntityBundleBase implements WisskiBundleInterface {
  
  /**
   * The field based pattern for the entity title generation.
   * A serialized array.
   * @var string
   */
  protected $title_pattern = '';
  
  /**
   * The pager limit for the bundle based entity list
   */
  protected $pager_limit = 10;
  
  public function getTitlePattern() {
    
    return unserialize($this->title_pattern);
  }
  
  public function removeTitlePattern() {

    if ('' !== $this->title_pattern) {
      $this->title_pattern = '';
      $this->flushTitleCache(); 
    }
  }
  
  protected $cached_titles;
  
  public function generateEntityTitle($wisski_individual,$include_bundle=FALSE) {
    
    $entity_id = $wisski_individual->id();
    $title = $this->getCachedTitle($entity_id);
    if (isset($title)) {
      //drupal_set_message('Title from cache');
      if ($include_bundle) {
        drupal_set_message('Enhance Title '.$title);
        $title = $this->label().': '.$title;
      }
    
      return $title;
    }
    $pattern = $this->getTitlePattern();
    unset($pattern['max_id']);
    //dpm(array('pattern'=>$pattern,'entity'=>$wisski_individual),__METHOD__);
    $parts = array();
    $empty_children = array();
    if (empty($pattern)) {
      $title_list = $wisski_individual->get('name')->getValue();
      $title = $title_list[0]['value'];
    } else {
      foreach ($pattern as $key => $attributes) {
        if ($attributes['type'] === 'path') {
          $name = $attributes['name'];
          if ($name === 'uri') $values = array($entity_id);
          else {
            list($pb_id,$path_id) = explode('.',$attributes['name']);
            $values = $this->gatherTitleValues($entity_id,$path_id);
          }
          if (empty($values)) {
            if ($attributes['optional'] === FALSE) {
              $parts[$key] = FALSE;
            }
            if (isset($attributes['children'])) {
              $empty_children += $attributes['children'];
            }
            continue;
          }
          $part = '';
          $cardinality = $attributes['cardinality'];
          if ($cardinality < 0 || $cardinality > count($values)) $cardinality = count($values);
          $delimiter = $attributes['delimiter'];
          $i = 0;
          foreach ($values as $value) {
            if ($i >= $cardinality) break;
            $part .= $value;
            if (++$i < $cardinality) $part .= $delimiter;
          } 
        }
        if ($attributes['type'] === 'text') {
          $part = $attributes['label'];
        }
        //if (!empty($attributes['children'])){dpm($part,'Part');dpm($parts,'Parts '.$key);}
        
        $parts[$key] = $part;
      }
      //dpm(array('parts'=>$parts,'empty_children'=>$empty_children),'after');
      $parts = array_diff_key($parts,array_flip($empty_children));
      if (in_array(FALSE,$parts)) {
        drupal_set_message('Detected invalid title','error');
        $title_list = $wisski_individual->get('name')->getValue();
        #drupal_set_message("bla: " . serialize($title_list));
        $title = $title_list[0]['value'];
      } else {
        $title = implode('',$parts);
      }
    }
    $this->setCachedTitle($entity_id,$title);
    //dpm(func_get_args()+array('pattern'=>$pattern,'result'=>$title),__METHOD__);
    if ($include_bundle) {
      drupal_set_message('Enhance Title '.$title);
      $title = $this->label().': '.$title;
    }  
    return $title;
  }
  
  private function gatherTitleValues($eid,$path_id) {

    $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($path_id);
    //dpm($path,$path_id);
    $adapters = \Drupal::entityManager()->getStorage('wisski_salz_adapter')->loadMultiple();
    $values = array();
    foreach ($adapters as $adapter) {
      $values += $adapter->getEngine()->pathToReturnValue($path, $adapter->getEngine()->getPbForThis(), $eid, 0);
    }
    return $values;
  }

  /**
   * Flushes the cache of generated entity titles
   * @param $entity_ids an array of IDs of entities whose titles shall be removed from this bundle's cache list, if NULL, all titles will be deleted
   */
  public function flushTitleCache($entity_ids = NULL) {

    if (is_null($entity_ids)) {
      unset($this->cached_titles);
      WisskiCacheHelper::flushAllEntityTitles($this->id());
    } elseif (!empty($entity_ids)) {
      foreach ((array) $entity_ids as $entity_id) {
        unset($this->cached_titles[$entity_id]);
        WisskiCacheHelper::flushEntityTitle($entity_id,$this->id());
      } 
    }
  }

  private function setCachedTitle($entity_id,$title) {
    
    $this->cached_titles[$entity_id] = $title;
    WisskiCacheHelper::putEntityTitle($entity_id,$title,$this->id());
  }

  public function getCachedTitle($entity_id) {
    
    if (!isset($this->cached_titles[$entity_id])) {  
      if ($title = WisskiCacheHelper::getEntityTitle($entity_id,$this->id())) $this->cached_titles[$entity_id] = $title;
      else return NULL;
    }//dpm($this->cached_titles,'cached titles');
    return $this->cached_titles[$entity_id];
  }
  
  public function setTitlePattern($title_pattern) {
    $input = serialize($title_pattern);
    if ($input !== $this->title_pattern) {
      $this->title_pattern = $input;
      $this->flushTitleCache(); 
    }
  }

  public function getPagerLimit() {
    return $this->pager_limit;
  }
  
  public function setPagerLimit($limit) {
    $this->pager_limit = $limit;
  }
}
