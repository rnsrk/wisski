<?php

namespace Drupal\wisski_core\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\wisski_core\WisskiBundleInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

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
  
  public function generateEntityTitle($wisski_individual) {
    
    $bundle_id = $this->id();
    $cache_id = 'wisski_title_cache'.$bundle_id;
    $entity_id = $wisski_individual->id();
    if (!isset($this->cached_titles)) {  
      if ($cache = \Drupal::cache()->get($cache_id)) {
        $this->cached_titles = $cache->data;  
      }  
    }
    if (isset($this->cached_titles[$entity_id])) {
      drupal_set_message('Title from cache');
      return $this->cached_titles[$entity_id];
    }
    $pattern = $this->getTitlePattern();
    unset($pattern['max_id']);
    //dpm(array('pattern'=>$pattern,'entity'=>$wisski_individual),__METHOD__);
    $parts = array();
    $empty_children = array();
    $title = '';
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
          if ($cardinality < 0) $cardinality = count($values);
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

    //dpm(func_get_args()+array('pattern'=>$pattern,'result'=>$title),__METHOD__);
    $this->cached_titles[$entity_id] = $title;
    \Drupal::cache()->set($cache_id,$this->cached_titles);
    return $title;
  }
  
  private function gatherTitleValues($eid,$path_id) {

    $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($path_id);
    //dpm($path,$path_id);
    $adapters = \Drupal::entityManager()->getStorage('wisski_salz_adapter')->loadMultiple();
    $values = array();
    foreach ($adapters as $adapter) {
      $values += $adapter->getEngine()->pathToReturnValue($path->getPathArray(), $path->getDatatypeProperty(), $eid, 0,'', $path->getDisamb());
    }
    return $values;
  }

  /**
   * Flushes the cache of generated entity titles
   * @param $entity_ids an array of IDs of entities whose titles shall be removed from this bundle's cache list, if NULL, all titles will be deleted
   */
  public function flushTitleCache($entity_ids = NULL) {
    $cache_id = 'wisski_title_cache'.$this->id;
    if (is_null($entity_id)) {
      unset($this->cached_titles);
      \Drupal::cache()->delete($cache_id);
    } elseif ($entity_ids !== array()) {
      if ($cache = \Drupal::cache()->get($cache_id)) {
        $data = $cache->data;
        $data = array_diff_key($data,array_flip($entity_ids));
        \Drupal::cache()->set($cache_id,$data);
        $this->cached_titles = $data;
      } 
    }
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
