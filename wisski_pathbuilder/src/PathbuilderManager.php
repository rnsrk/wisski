<?php

use Drupal\wisski_pathbuilder\Entity\WisskiPathEntity;
use Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity;

namespace Drupal\wisski_pathbuilder;

/**
 *
 */
class PathbuilderManager {

  private static $pbsForAdapter = NULL;

  private static $pbsUsingBundle = NULL;

  private static $bundlesWithStartingConcept = NULL;

  private static $imagePaths = NULL;

  private static $pbs = NULL;

  private static $paths = NULL;

  /**
   * Reset the cached mappings.
   */
  public function reset() {
    self::$pbsForAdapter = NULL;
    self::$pbsUsingBundle = NULL;
    self::$imagePaths = NULL;
    self::$pbs = NULL;
    self::$paths = NULL;
    \Drupal::cache()->delete('wisski_pathbuilder_manager_pbs_for_adapter');
    \Drupal::cache()->delete('wisski_pathbuilder_manager_pbs_using_bundle');
    \Drupal::cache()->delete('wisski_pathbuilder_manager_image_paths');
  }

  /**
   * Get the pathbuilders that make use of a given adapter.
   *
   * @param adapter_id the ID of the adapter
   *
   * @return if adapter_id is empty, returns an array where the keys are
   *   adapter IDs and the values are arrays of corresponding
   *          pathbuilders. If adapter_id is given returns an array of
   *          corresponding pathbuilders.
   */
  public function getPbsForAdapter($adapter_id = NULL) {
    // Not yet fetched from cache?
    if (self::$pbsForAdapter === NULL) {
      if ($cache = \Drupal::cache()->get('wisski_pathbuilder_manager_pbs_for_adapter')) {
        self::$pbsForAdapter = $cache->data;
      }
    }
    // Was reset.
    if (self::$pbsForAdapter === NULL) {
      self::$pbsForAdapter = [];
      $pbs = entity_load_multiple('wisski_pathbuilder');
      foreach ($pbs as $pbid => $pb) {
        $aid = $pb->getAdapterId();
        $adapter = entity_load('wisski_salz_adapter', $aid);
        if ($adapter) {
          if (!isset(self::$pbsForAdapter[$aid])) {
            self::$pbsForAdapter[$aid] = [];
          }
          self::$pbsForAdapter[$aid][$pbid] = $pbid;
        }
        else {
          drupal_set_message(
          t(
            'Pathbuilder %pb refers to non-existing adapter with ID %aid.', [
              '%pb' => $pb->getName(),
              '%aid' => $pb->getAdapterId(),
            ]
          ), 'error'
          );
        }
      }
      \Drupal::cache()->set('wisski_pathbuilder_manager_pbs_for_adapter', self::$pbsForAdapter);
    }
    return empty($adapter_id)
           ? self::$pbsForAdapter
    // If there is no pb for this adapter there is no array key.
           : (isset(self::$pbsForAdapter[$adapter_id])
         ? self::$pbsForAdapter[$adapter_id]
    // ... thus we return an empty array.
         : []);
  }

  /**
   *
   */
  public function getPbsUsingBundle($bundle_id = NULL) {
    // Not yet fetched from cache?
    if (self::$pbsUsingBundle === NULL) {
      if ($cache = \Drupal::cache()->get('wisski_pathbuilder_manager_pbs_using_bundle')) {
        self::$pbsUsingBundle = $cache->data;
      }
    }
    // Was reset, recalculate.
    if (self::$pbsUsingBundle === NULL) {
      $this->calculateBundlesAndStartingConcepts();
    }
    return empty($bundle_id)
    // If no bundle given, return all.
           ? self::$pbsUsingBundle
           : (isset(self::$pbsUsingBundle[$bundle_id])
    // If bundle given and we know it, return only for this.
         ? self::$pbsUsingBundle[$bundle_id]
    // If bundle is unknown, return empty array.
         : []);

  }

  /**
   *
   */
  public function getPreviewImage($entity_id, $bundle_id, $adapter) {
    $pbs_and_paths = $this->getImagePathsAndPbsForBundle($bundle_id);

    // dpm($pbs_and_paths, "yay!");.
    foreach ($pbs_and_paths as $pb_id => $paths) {

      if (empty(self::$pbs)) {
        $pbs = WisskiPathbuilderEntity::loadMultiple();
        self::$pbs = $pbs;
      }
      else {
        $pbs = self::$pbs;
      }

      $pb = $pbs[$pb_id];

      $the_pathid = NULL;
      // Beat this ...
      $weight = 99999999999;

      // Go through all paths and look for the lowest weight.
      foreach ($paths as $key => $pathid) {
        $pbp = $pb->getPbPath($pathid);

        if (empty($pbp['enabled'])) {
          continue;
        }

        if (isset($pbp['weight'])) {
          if ($pbp['weight'] < $weight) {
            // Only take this if the weight is better or the same.
            $the_pathid = $pathid;
            $weight = $pbp['weight'];
          }
        }
        elseif (empty($the_pathid)) {
          // If there was nothing before, something is better at least.
          $the_pathid = $pathid;
        }
      }

      // dpm($pathid, "assa");.
      // Nothing found?
      if (empty($the_pathid)) {
        return [];
      }

      if (empty(self::$paths)) {
        $paths = WisskiPathEntity::loadMultiple();
        self::$paths = $paths;
      }
      else {
        $paths = self::$paths;
      }

      $path = $paths[$the_pathid];
      // dpm(microtime(), "ptr?");.
      $values = $adapter->getEngine()->pathToReturnValue($path, $pb, $entity_id, 0, NULL, FALSE);
      // dpm(microtime(), "ptr!");.
      if (!empty($values)) {
        return $values;
      }

    }
    return [];
  }

  /**
   *
   */
  public function getImagePathsAndPbsForBundle($bundle_id) {

    // Not yet fetched from cache?
    if (self::$imagePaths === NULL) {
      if ($cache = \Drupal::cache()->get('wisski_pathbuilder_manager_image_paths')) {
        self::$imagePaths = $cache->data;
      }
    }
    // Was reset, recalculate.
    if (self::$imagePaths === NULL) {
      $this->calculateImagePaths();
    }

    if (isset(self::$imagePaths[$bundle_id])) {
      return self::$imagePaths[$bundle_id];
    }

    return [];

  }

  /**
   *
   */
  public function calculateImagePaths() {
    $info = [];

    // $pbs = entity_load_multiple('wisski_pathbuilder');.
    if (empty(self::$pbs)) {
      $pbs = WisskiPathbuilderEntity::loadMultiple();
      self::$pbs = $pbs;
    }
    else {
      $pbs = self::$pbs;
    }

    foreach ($pbs as $pbid => $pb) {
      $groups = $pb->getMainGroups();

      foreach ($groups as $group) {
        $bundleid = $pb->getPbPath($group->id())['bundle'];
        $paths = $pb->getImagePathIDsForGroup($group->id());

        if (!empty($paths)) {
          self::$imagePaths[$bundleid][$pbid] = $paths;
        }

        // foreach($paths as $pathid) {
        // $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($pathid);
        // $info[$bundleid][$pbid][$pathid] = $pathid;
        // }.
      }
    }

    \Drupal::cache()->set('wisski_pathbuilder_manager_image_paths', self::$imagePaths);
  }

  /**
   *
   */
  public function getBundlesWithStartingConcept($concept_uri = NULL) {
    // Not yet fetched from cache?
    if (self::$bundlesWithStartingConcept === NULL) {
      if ($cache = \Drupal::cache()->get('wisski_pathbuilder_manager_bundles_with_starting_concept')) {
        self::$bundlesWithStartingConcept = $cache->data;
      }
    }
    // Was reset, recalculate.
    if (self::$bundlesWithStartingConcept === NULL) {
      $this->calculateBundlesAndStartingConcepts();
    }
    return empty($concept_uri)
    // If no concept given, return all.
           ? self::$bundlesWithStartingConcept
           : (isset(self::$bundlesWithStartingConcept[$concept_uri])
    // If concept given and we know it, return only for this.
         ? self::$bundlesWithStartingConcept[$concept_uri]
    // If concept is unknown, return empty array.
         : []);

  }

  /**
   *
   */
  private function calculateBundlesAndStartingConcepts() {
    self::$pbsUsingBundle = [];
    self::$bundlesWithStartingConcept = [];

    if (empty(self::$pbs)) {
      $pbs = WisskiPathbuilderEntity::loadMultiple();
      self::$pbs = $pbs;
    }
    else {
      $pbs = self::$pbs;
    }

    foreach ($pbs as $pbid => $pb) {
      foreach ($pb->getAllGroups() as $group) {
        $pbpath = $pb->getPbPath($group->getID());
        $bid = $pbpath['bundle'];
        if (!empty($bid)) {
          if (!isset(self::$pbsUsingBundle[$bid])) {
            self::$pbsUsingBundle[$bid] = [];
          }
          $adapter = entity_load('wisski_salz_adapter', $pb->getAdapterId());
          if ($adapter) {
            // Struct for pbsUsingBundle.
            if (!isset(self::$pbsUsingBundle[$bid][$pbid])) {
              $engine = $adapter->getEngine();
              $info = [
                'pb_id' => $pbid,
                'adapter_id' => $adapter->id(),
                'writable' => $engine->isWritable(),
                'preferred_local' => $engine->isPreferredLocalStore(),
                'engine_plugin_id' => $engine->getPluginId(),
              // Filled below.
                'main_concept' => [],
              // Filled below.
                'is_top_concept' => [],
              // Filled below.
                'groups' => [],
              ];
              self::$pbsUsingBundle[$bid][$pbid] = $info;
            }
            $path_array = $group->getPathArray();
            // The last concept is the main concept.
            $main_concept = end($path_array);
            self::$pbsUsingBundle[$bid][$pbid]['main_concept'][$main_concept] = $main_concept;
            if (empty($pbpath['parent'])) {
              self::$pbsUsingBundle[$bid][$pbid]['is_top_concept'][$main_concept] = $main_concept;
            }
            self::$pbsUsingBundle[$bid][$pbid]['groups'][$group->getID()] = $main_concept;

            // Struct for bundlesWithStartingConcept.
            if (!isset(self::$bundlesWithStartingConcept[$main_concept])) {
              self::$bundlesWithStartingConcept[$main_concept] = [];
            }
            if (!isset(self::$bundlesWithStartingConcept[$main_concept][$bid])) {
              self::$bundlesWithStartingConcept[$main_concept][$bid] = [
                'bundle_id' => $bid,
                'is_top_bundle' => FALSE,
                'pb_ids' => [],
                'adapter_ids' => [],
              ];
            }
            self::$bundlesWithStartingConcept[$main_concept][$bid]['pb_ids'][$pbid] = $pbid;
            self::$bundlesWithStartingConcept[$main_concept][$bid]['adapter_ids'][$adapter->id()] = $adapter->id();
            if (empty($pbpath['parent'])) {
              self::$bundlesWithStartingConcept[$main_concept][$bid]['is_top_bundle'] = TRUE;
            }

          }
          else {
            drupal_set_message(
            t(
            'Pathbuilder %pb refers to non-existing adapter with ID %aid.', [
              '%pb' => $pb->getName(),
              '%aid' => $pb->getAdapterId(),
            ]
            ), 'error'
            );
          }
        }
      }
    }
    \Drupal::cache()->set('wisski_pathbuilder_manager_pbs_using_bundle', self::$pbsUsingBundle);
    \Drupal::cache()->set('wisski_pathbuilder_manager_bundles_with_starting_concept', self::$bundlesWithStartingConcept);
  }

  /**
   *
   */
  public function getOrphanedPaths() {

    $pba = entity_load_multiple('wisski_pathbuilder');
    $pa = entity_load_multiple('wisski_path');
    // Filled in big loop.
    $tree_path_ids = [];

    // Here go regular paths, ie. that are in a pb's path tree.
    $home = [];
    // Here go paths that are listed in a pb but not in its path tree (are "hidden")
    $semiorphaned = [];
    // Here go paths that aren't mentioned in any pb.
    $orphaned = [];

    foreach ($pa as $pid => $p) {
      $is_orphaned = TRUE;
      foreach ($pba as $pbid => $pb) {
        if (!isset($tree_path_ids[$pbid])) {
          $tree_path_ids[$pbid] = $this->getPathIdsInPathTree($pb);
        }
        $pbpath = $pb->getPbPath($pid);
        if (isset($tree_path_ids[$pbid][$pid])) {
          $home[$pid][$pbid] = $pbid;
          $is_orphaned = FALSE;
        }
        elseif (!empty($pbpath)) {
          $semiorphaned[$pid][$pbid] = $pbid;
          $is_orphaned = FALSE;
        }
      }
      if ($is_orphaned) {
        $orphaned[$pid] = $pid;
      }
    }
    return [
      'home' => $home,
      'semiorphaned' => $semiorphaned,
      'orphaned' => $orphaned,
    ];

  }

  /**
   *
   */
  public function getPathIdsInPathTree($pb) {
    $ids = [];
    $agenda = $pb->getPathTree();
    while ($node = array_shift($agenda)) {
      $ids[$node['id']] = $node['id'];
      $agenda = array_merge($agenda, $node['children']);
    }
    return $ids;
  }

}
