<?php

namespace Drupal\wisski_core\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\wisski_core\WisskiBundleInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

use Drupal\wisski_core\WisskiCacheHelper;
use Drupal\menu_link_content\Entity\MenuLinkContent;

use Drupal\Core\Entity\EntityStorageInterface;
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
 *   config_export = {
 *     "id",
 *     "label",
 *     "title_pattern",
 *     "on_empty",
 *     "fallback_title",
 *     "pager_limit",
 *     "menu_items",
 *   },
 *
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
  
  /** constants to identify empty title reaction types */
  const DONT_SHOW = 1;
  const FALLBACK_TITLE = 2;
  const DEFAULT_PATTERN = 3;

  const MENU_CREATE = 1;
  const MENU_ENABLE = 2;
  
  /**
   * The field based pattern for the entity title generation.
   * A serialized array.
   * @var string
   */
  protected $title_pattern = '';
  
  /**
   * The way in which to react on the detection of an invalid title
   * defaults to fallback title
   */
  protected $on_empty = self::DEFAULT_PATTERN;
  
  /**
   * The fallback title that may be shown when an entity title cannot be resolved
   */
  protected $fallback_title = 'WissKI Entity';
  
  /**
   * The pager limit for the bundle based entity list
   */
  protected $pager_limit = 10;
  
  /**
   * The options array for this bundle's title pattern
   */
  protected $path_options = array();


  protected $menu_items = array();
  
  /**
   * Where should this be listed?
   * @return array with key = menu name
   *         and value route parameters
   */
  public static function getWissKIMenus() {
    return array('navigate' => 'entity.wisski_bundle.entity_list',
                 'create' => 'entity.wisski_individual.add');
  }
  
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

#    $menus = array("navigate" => 'entity.wisski_bundle.entity_list', "create" => 'entity.wisski_individual_create.list');
    
    foreach($entities as $entity) {
      $menus = $entity->getWissKIMenus();
      foreach($menus as $menu_name => $route) {
        $entity->deleteBundleFromMenu($menu_name,  $route);
      }
    }
  }
  
  public function getTitlePattern() {

    if(empty($this->title_pattern)) {

      $state = \Drupal::state()->get('wisski_core_title_patterns') ?: serialize(array());
      $state = unserialize($state);

      $title = isset($state[$this->id]) ? $state[$this->id] : '';
      if(!empty($title));
        return $title;
    }
  
    return unserialize($this->title_pattern);
  }
  
  public function removeTitlePattern() {

    if ('' !== $this->title_pattern) {
      $this->title_pattern = '';
      $this->flushTitleCache(); 
    }
  }
  
  public function getDefaultPattern() {
    
    return \Drupal::config('wisski_core.settings')->get('wisski_default_title_pattern');
  }
  
  protected $cached_titles;
  
  public function generateEntityTitle($entity_id,$include_bundle=FALSE,$force_new=FALSE) {
    $pattern = $this->getTitlePattern();
#    drupal_set_message(serialize($pattern));
#    drupal_set_message("generated: " . $this->applyTitlePattern($pattern,$entity_id));
    if (!$force_new) {
      $title = $this->getCachedTitle($entity_id);
      if (isset($title)) {
        #drupal_set_message('Title from cache');
        if ($include_bundle) {
          drupal_set_message('Enhance Title '.$title);
          $title = $this->label().': '.$title;
        }    
        return $title;
      }
    }
    
    $pattern = $this->getTitlePattern();
    
    //now do the work
    $title = $this->applyTitlePattern($pattern,$entity_id);
    
    $this->setCachedTitle($entity_id,$title);
    
    if ($include_bundle && $title !== FALSE) {
      drupal_set_message('Enhance Title '.$title);
      $title = $this->label().': '.$title;
    }   
    return $title;
  }
  
  /**
   * Applies the title pattern to generate the entity title,
   * this is a seperate function since we want to be able to apply it again in case we end up with an empty title
   */
  private function applyTitlePattern($pattern,$entity_id) {
    
    #dpm($pattern,__FUNCTION__);
    if(isset($pattern['max_id']))
      unset($pattern['max_id']);
        
    // just in case...
    if (empty($pattern)) return $this->createFallbackTitle($entity_id);;
    
    $parts = array();
    $pattern_order = array_keys($pattern);
    //just to avoid infinite loops we introduce an upper bound,
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
        unset($values);
        switch ($name) {
          case 'eid':
            $values = array($entity_id);
            break;
          case 'uri.long':
          case 'uri.short':
            $values = array($this->getUriString($entity_id,$name));
            break;
          case 'bundle_label':
            $values = array($this->label());
            break;
          case 'bundle_id':
            $values = array($this->id());
            break;
          default: {
            list($pb_id,$path_id) = explode('.',$attributes['name']);
            $values = $this->gatherTitleValues($entity_id,$path_id);
#            dpm($values,'gathered values for '.$path_id);
          }
        }
        if (empty($values)) {
          if ($attributes['optional'] === FALSE) {
            //we detected an invalid title;
            drupal_set_message('Detected invalid title','error');
            return $this->createFallbackTitle($entity_id);
          } else $parts[$key] = '';
          continue;
        }
        $part = '';
        $cardinality = $attributes['cardinality'];
        if ($cardinality < 0 || $cardinality > count($values)) $cardinality = count($values);
        $delimiter = $attributes['delimiter'];
        $i = 0;
#        dpm($values, "values");
        foreach ($values as $value) {

          // fix for empty values, we ignore these for now.
          if(empty($value))
            continue;
#          dpm($i, "i");
#          dpm($cardinality, "card");
          if ($i >= $cardinality) break;
#          dpm($value, 'get');
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
#    dpm(array('parts'=>$parts),'after');
    
    //reorder the parts according original pattern
    $title = '';
    foreach ($pattern_order as $pos) {
      if (isset($parts[$pos])) $title .= $parts[$pos];
    }
    
    if (empty(trim($title))) return $this->createFallbackTitle($entity_id);

    #dpm(func_get_args()+array('result'=>$title),__METHOD__);
    return $title;
  }
  
  public function createFallbackTitle($entity_id) {
    
    switch ($this->onEmpty()) {
      case self::FALLBACK_TITLE: return $this->fallback_title;
      case self::DEFAULT_PATTERN: return $this->applyTitlePattern($this->getDefaultPattern(),$entity_id);
      case self::DONT_SHOW:
      default: return FALSE;
    }
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
        #dpm($path,$path_id);
        // then we try to load the path's adapter
        $adapter = \Drupal\wisski_salz\Entity\Adapter::load($pb->getAdapterId());
        if (empty($adapter)) {
          #dpm('can\'t load adapter '.$pb->getAdapterId(),$pb_id);
          continue;
        }

        if (\Drupal\wisski_salz\AdapterHelper::getUrisForDrupalId($eid,$adapter->id())) {
          //finally, having a valid path and adapter, we can ask the adapter for the path's value
          $pbpath = $pb->getPbPath($path_id);
                              
          $bundle_of_path = $pbpath['bundle'];
 
          // if this is empty, then we get the parent and take this.
          if(empty($bundle_of_path) || $path->getType() == "Path") {
            $group = $pb->getPbPath($pbpath['parent']);
            $bundle_of_path = $group['bundle'];
          }

          // get the group-object for the current bundle we're on
          $groups = $pb->getGroupsForBundle($this->id());
                    
          // if there are several groups, for now take only the first one
          $group = current($groups);
          
          if(empty($group)) {
            drupal_set_message("There is an empty group in your system: " . serialize($groups));
            continue;
          }
          
          // if the bundle and this object are not the same, the eid is the one of the
          // main bundle and the paths have to be absolute. In this case
          // we have to call it with false. 
          if($bundle_of_path != $this->id()) {
            // if this bundle is not the bundle where the path is in, we go to
            // absolute mode and give the length of the group because we find 
            // $eid there.
            $new_values = $adapter->getEngine()->pathToReturnValue($path, $pb, $eid, count($group->getPathArray())-1, NULL, FALSE); 
          } else // if not they are relative.
            $new_values = $adapter->getEngine()->pathToReturnValue($path, $pb, $eid, 0, NULL, TRUE);
          if (WISSKI_DEVEL) \Drupal::logger($pb_id.' '.$path_id.' '.__FUNCTION__)->debug('Entity '.$eid."{out}",array('out'=>serialize($new_values)));
        }  
        if (empty($new_values)) {
          //dpm('don\'t have values for '.$path_id.' in '.$pb_id,$adapter->id());
        } else $values += $new_values;
      } //else dpm('don\'t know path '.$path_id,$pb_id);
    }
    return $values;
  }
  
  public static function defaultPathOptions() {
    
    return array(
      'eid' => t('Entity\'s Drupal ID'),
      'uri.long' => t('Full URI'),
      'uri.short' => t('Short URI'),
      'bundle_label' => t('The bundle\'s label'),
      'bid' => t('The bundle\'s ID'),
    );    
  }
  
  public function getPathOptions() {
    
    $options = &$this->path_options;
    //if we already gathered the data, we can stop here
    if (empty($options)) {
      $options = self::defaultPathOptions();
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
        drupal_set_message("no match for URI $uri", 'error');
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
    
#    $config = \Drupal::configFactory()->getEditable('wisski_core.wisski_bundle_title');
#    $config->set($this->id, $title_pattern)->save();
    $state = \Drupal::state()->get('wisski_core_title_patterns') ?: serialize(array());
    $state = unserialize($state);
    $state[$this->id] = $title_pattern;
    $state = serialize($state);
    \Drupal::state()->set('wisski_core_title_patterns', $state);
  }

  public function onEmpty() {
    
    return $this->on_empty;
  }
  
  public function setOnEmpty($type) {
    
    $type = intval($type);
    if ($type == self::DEFAULT_PATTERN || $type == self::FALLBACK_TITLE || $type == self::DONT_SHOW) {
      $this->on_empty = $type;
    } else drupal_set_message('Invalid fallback type for title pattern');
  }
  
  public function getFallbackTitle() {
    
    return $this->fallback_title;
  }
  
  public function setFallbackTitle($fallback_title) {
    
    if (is_string($fallback_title) && !empty($fallback_title))
      $this->fallback_title = $fallback_title;
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
          $parent = self::load($parent_id);
          if (!empty($parent)) {
            $parents[$parent_id] = $parent->label();
          }
        } else $parents[$parent_id] = $parent_id;
      }
    }
    return $parents;
  }

  public function deleteBundleFromMenu($menu_name,  $destination_route = "entity.wisski_bundle.entity_list", $parameters = array() ) {

    if(empty($parameters))
      $parameters = array("wisski_bundle" => $this->id());
  
    $link = \Drupal\Core\Link::createFromRoute($this->label(), $destination_route, $parameters);
    
    // generate the parameter-string for the menu_link_content table
    $params = "";
    foreach($parameters as $key => $parameter) {
      $params .= $key . '=' . $parameter. ';';
    }

    // kill the last ; in the end
    $params = substr($params, 0, -1);

#    dpm('route:' . $destination_route . ';' . $params);

    // get the matching entities     
    $entities = \Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['menu_name' => $menu_name, 'title' => $this->label(), 'link__uri' => 'route:' . $destination_route . ';' . $params ]);

    // typically there should be only one.
    $entity = current($entities);
    
    if(!empty($entity))
      $entity->delete();  
  
  }


  /**
   * Adds a menu entry for a bundle
   * e.g. to navigate, find or create
   */
  public function addBundleToMenu($menu_name, $destination_route = "entity.wisski_bundle.entity_list", $parameters = array() ) {
    
    $menu_mode = $this->getCreateMenuItems($menu_name, self::MENU_CREATE);
    if (!$menu_mode) {
      // the setting says that we should not create a menu item
      return;
    }

    if(empty($parameters))
      $parameters = array("wisski_bundle" => $this->id());
//    $name = 'wisski_menu_' . $this->id();
    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $entity */

 //   $entity = MenuLinkContent::load($name);
 
    $link = \Drupal\Core\Link::createFromRoute($this->label(), $destination_route, $parameters);
    
    // generate the parameter-string for the menu_link_content table
    $params = "";
    foreach($parameters as $key => $parameter) {
      $params .= $key . '=' . $parameter. ';';
    }

    // kill the last ; in the end
    $params = substr($params, 0, -1);

#    dpm('route:' . $destination_route . ';' . $params);

    // get the matching entities     
    $entities = \Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['menu_name' => $menu_name, 'title' => $this->label(), 'link__uri' => 'route:' . $destination_route . ';' . $params ]);

    // typically there should be only one.
    $entity = current($entities);
    
    $weight = 0;
    
    // only act if there is no entity. Otherwise we can just check if everything is ok.

    $pbs = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::loadMultiple();
    
    $groups = array();
    //we ask all pathbuilders if they know the bundle
    foreach ($pbs as $pb_id => $pb) {
      $groups = array_merge($groups, $pb->getGroupsForBundle($this->id()));
    
      if(!empty($groups)) // we take the first one for now
        $group = current($groups);
        
      if(!empty($group)) {
        $pbp = $pb->getPbPath($group->id());
        $weight = $pbp['weight'];
        break;
      }    
    }  
  
      // for further usage: language coding... currently no support at this point
/*
    if ($entity->isTranslatable()) {
      if (!$entity->hasTranslation($node->language()->getId())) {
        $entity = $entity->addTranslation($node->language()->getId(), $entity->toArray());
      }
      else {
        $entity = $entity->getTranslation($node->language()->getId());
      }
    }
    */
    if(empty($entity)) {
#      $link = \Drupal\Core\Link::createFromRoute($this->label(), "entity.wisski_bundle.entity_list", array("wisski_bundle" => $this->id()));
      // Create a new menu_link_content entity.
      $entity = MenuLinkContent::create(array(
        'link' => ['uri' => $link->getUrl()->toUriString()],
#        'langcode' => $node->language()->getId(),
      ));
      $entity->enabled->value = $menu_mode & self::MENU_ENABLE;
    }

#    dpm($entity, "bundle");
#    $entity->id = $name;
    $entity->title->value = trim($this->label());
#    $entity->description->value = trim($group->getDe);
    $entity->menu_name->value = $menu_name;
#    $entity->parent->value = $values['parent'];
    $entity->weight->value = isset($entity->weight->value) ? $entity->weight->value : $weight;
    $entity->save();
  }

  
  /** For each of the menus associated with this bundle, returns information 
   * whether to create a menu item for this bundle and whether it should be 
   * enabled by default.
   *
   * @param menu_name restrict the return value to the info for this menu
   * @param filter filter the menus' info. Can be MENU_CREATE, MENU_ENABLE or a
            combination thereof
   * @return an array where the keys are menu ids and the values are 
   *         MENU_CREATE, MENU_ENABLE or a combination thereof. If menu_name is
   *         given, only the menu's info is returned; if the menu does not 
   *         exist or was filtered out, FALSE is returned.
   */
  public function getCreateMenuItems($menu_name = NULL, $filter = NULL) {
    $menus = $this->menu_items + self::getCreateMenuItemDefaults();
    if ($filter !== NULL) {
      $menus = array_filter($menus, function($v) use ($filter) {
        return $v & $filter;
      });
    }
    if ($menu_name !== NULL) {
      if (isset($menus[$menu_name])) {
        return $menus[$menu_name];
      }
      // either the menu name does not exist or it was filtered out
      return FALSE;
    }
    return $menus;
  }


  public function setCreateMenuItems($items) {
    $this->menu_items = $items;
  }

  public static function getCreateMenuItemDefaults() {
    // currently we always want to create items for every menu
    return array_fill_keys(array_keys(self::getWissKIMenus()), self::MENU_CREATE | self::MENU_ENABLE);
  }


}
