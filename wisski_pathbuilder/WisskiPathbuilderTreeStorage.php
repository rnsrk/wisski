<?php

/**
 * @file
 * Contains \Drupal\Core\Menu\MenuTreeStorage.
 */
  
namespace Drupal\wisski_pathbuilder;
   
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\SchemaObjectExistsException;
   
/**
 * Provides a pathbuilder tree storage using the database.
 */
class WisskiPathbuilderTreeStorage implements MenuTreeStorageInterface {


  public function loadTreeData($path_name, MenuTreeParameters $parameters) {
    // Build the cache ID; sort 'expanded' and 'conditions' to prevent duplicate
    // cache items.
    sort($parameters->expandedParents);
    asort($parameters->conditions);
    $tree_cid = "tree-data:$menu_name:" . serialize($parameters);
    $cache = $this->menuCacheBackend->get($tree_cid);
    if ($cache && isset($cache->data)) {
      $data = $cache->data;
      // Cache the definitions in memory so they don't need to be loaded again.
      $this->definitions += $data['definitions'];
      unset($data['definitions']);
    }
    else {
      $links = $this->loadLinks($path_name, $parameters);
      $data['tree'] = $this->doBuildTreeData($links, $parameters->activeTrail, $parameters->minDepth);
      $data['definitions'] = array();
      $data['route_names'] = $this->collectRoutesAndDefinitions($data['tree'], $data['definitions']);
      $this->menuCacheBackend->set($tree_cid, $data, Cache::PERMANENT, ['config:system.menu.' . $menu_name]);
      // The definitions were already added to $this->definitions in
      // $this->doBuildTreeData()
      unset($data['definitions']);
    }
    return $data;
  }
}     

