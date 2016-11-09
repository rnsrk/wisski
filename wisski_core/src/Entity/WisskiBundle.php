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
  
  use StringTranslationTrait;
  
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
  
  /**
   * The options array for this bundle's title pattern
   */
  protected $path_options = array();
  
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
  
  public function generateEntityTitle($entity_id,$fallback_title='Wisski Individual',$include_bundle=FALSE,$force_new=FALSE) {
    
    if (!$force_new) {
      $title = $this->getCachedTitle($entity_id);
      if (isset($title)) {
        //drupal_set_message('Title from cache');
        if ($include_bundle) {
          drupal_set_message('Enhance Title '.$title);
          $title = $this->label().': '.$title;
        }    
        return $title;
      }
    }
    $pattern = $this->getTitlePattern();
    unset($pattern['max_id']);
    //dpm(array('pattern'=>$pattern,'entity'=>$entity_id,'fallback'=>$fallback_title),__METHOD__);
    if (empty($pattern)) {
      return $fallback_title;
    } else {
      //dpm($pattern,__FUNCTION__);
      $parts = array();
      $pattern_order = array_keys($pattern);
      //just to avoid endless runs we introduce an upper bound,
      //this is possible since per run at most k-1 other elements have to be cycled through before
      //having seen all parents i.e. $max = sum_{k = 0}^$count k
      $count = count($pattern);
      $max = ($count * ($count+1)) / 2;
      $count = 0;
      while ($count < $max && list($key,$attributes) = each($pattern)) {
        $count++;
        unset($pattern[$key]);
        reset($pattern);
        //dpm($pattern,'Hold '.$key);
        //if we have a dependency make sure we only consider this one, when all dependencies are clear
        if (!empty($attributes['parents'])) {
          foreach ($attributes['parents'] as $parent => $positive) {
            //dpm($parts,'Ask for '.$parent.' '.($positive ? 'pos' : 'neg'));
            if (!isset($parts[$parent])) {
              $pattern[$key] = $attributes;
              continue 2;
            } elseif ($positive) {
              if ($parts[$parent] === '') continue 2;
            } else { //if negative
              if (!empty($parts[$parent])) continue 2;
            }
          }
        }
        if ($attributes['type'] === 'path') {
          $name = $attributes['name'];
                    
          if ($name === 'eid') $values = array($entity_id);
          elseif ($name === 'uri.long' || $name === 'uri.short') {
            $values = array($this->getUriString($entity_id,$name));
          }
          else {
            list($pb_id,$path_id) = explode('.',$attributes['name']);
            $values = $this->gatherTitleValues($entity_id,$path_id);
            //dpm($values,'gathered values for '.$path_id);
          }
          
          if (empty($values)) {
            if ($attributes['optional'] === FALSE) {
              //we detected an invalid title;
              drupal_set_message('Detected invalid title','error');
              return $fallback_title;
            } else $parts[$key] = '';
            continue;
          }
          $part = '';
          $cardinality = $attributes['cardinality'];
          if ($cardinality < 0 || $cardinality > count($values)) $cardinality = count($values);
          $delimiter = $attributes['delimiter'];
          $i = 0;
          foreach ($values as $value) {
            if ($i >= $cardinality) break;
#dpm($value, 'get');
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
      //dpm(array('parts'=>$parts),'after');
      
      //reorder the parts according original pattern
      $title = '';
      foreach ($pattern_order as $pos) {
        if (isset($parts[$pos])) $title .= $parts[$pos];
      }
      if (empty(trim($title))) $title = $fallback_title;
    }
    $this->setCachedTitle($entity_id,$title);
    //dpm(array_combine(['$entity_id','$fallback_title','$include_bundle','$force_new'],func_get_args())+array('pattern'=>$pattern,'result'=>$title),__METHOD__);
    if ($include_bundle) {
      drupal_set_message('Enhance Title '.$title);
      $title = $this->label().': '.$title;
    }   
    return $title;
  }
  
  public function gatherTitleValues($eid,$path_id) {

    $values = array();
    $pbs = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::loadMultiple();
    //we ask all pathbuilders if they know the path
    foreach ($pbs as $pb_id => $pb) {
      if ($pb->hasPbPath($path_id)) {
        // if the PB knows the path we try to load it
        $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($path_id);
        if (empty($path)) {
          //dpm('can\'t load path '.$path_id,$pb_id);
          continue;
        }
        //dpm($path,$path_id);
        // then we try to load the path's adapter
        $adapter = \Drupal\wisski_salz\Entity\Adapter::load($pb->getAdapterId());
        if (empty($adapter)) {
          //dpm('can\'t load adapter '.$pb->getAdapterId(),$pb_id);
          continue;
        }
        if (\Drupal\wisski_salz\AdapterHelper::getUrisForDrupalId($eid,$adapter->id())) {
          //finally, having a valid path and adapter, we can ask the adapter for the path's value
          $new_values = $adapter->getEngine()->pathToReturnValue($path, $pb, $eid, 0, NULL, FALSE);
          \Drupal::logger($pb_id.' '.$path_id.' '.__FUNCTION__)->debug('Entity '.$eid."{out}",array('out'=>serialize($new_values)));
        }  
        if (empty($new_values)) {
          //dpm('don\'t have values for '.$path_id.' in '.$pb_id,$adapter->id());
        } else $values += $new_values;
      } //else dpm('don\'t know path '.$path_id,$pb_id);
    }
    return $values;
  }
  
  public function getPathOptions() {
    
    $options = &$this->path_options;
    //if we already gathered the data, we can stop here
    if (empty($options)) {
      $options = array(
        'eid' => $this->t('Entity\'s Drupal ID'),
        'uri.long' => $this->t('Full URI'),
        'uri.short' => $this->t('Short URI'),
      );
      //find all paths from all active pathbuilders
      $pbs = \Drupal::entityManager()->getStorage('wisski_pathbuilder')->loadMultiple();
#      $paths = array();
      foreach ($pbs as $pb_id => $pb) {
        $paths = $pb->getAllPathsForBundleId($this->id(), TRUE);
        
        foreach($paths as $path) {
          $options[$pb_id][$pb_id.'.'.$path->id()] = $path->getName();
        }
/*
        $pb_paths = $pb->getAllPaths();
        foreach ($pb_paths as $path) {
          $path_id = $path->getID();
          if ($this->id() === $pb->getBundle($path_id)) {
            $options[$pb_id][$pb_id.'.'.$path_id] = $path->getName();
          } 
        }
*/
      }
    }
    return $options;
  }

  public function getUriString($entity_id,$type) {
    
    $uris = \Drupal\wisski_salz\AdapterHelper::getUrisForDrupalId($entity_id);
    if (empty($uris)) return '';
    $uri = current($uris);
    if ($type === 'uri.long') return $uri;
    if ($type === 'uri.short') {
      $matches = array();
      if (preg_match('/^.*[\#\/](.+)$/',$uri,$matches)) {
        return $matches[1];
      } else {
        dpm($uri,'no match');
      }
    }
    return '';
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
  
  public function getParentBundleIds($get_labels=TRUE) {
    
    $pbs = \Drupal::entityManager()->getStorage('wisski_pathbuilder')->loadMultiple();
    $parents = array();
    foreach ($pbs as $pb_id => $pb) {
      $parent_id = $pb->getParentBundleId($this->id());
      if ($parent_id) {
        if ($get_labels) {
          $parents[$parent_id] = self::load($parent_id)->label();
        } else $parents[$parent_id] = $parent_id;
      }
    }
    return $parents;
  }
}
