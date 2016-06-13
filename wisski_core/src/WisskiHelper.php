<?php

namespace Drupal\wisski_core;

/**
 * a class that holds static convenient methods to be used by the Wisski modules
 */
class WisskiHelper {

  /**
   * Merges two associative arrays according to their keys. Values for keys that are present in both arrays
   * will be taken from the SECOND one, unless their value is considered empty.
   */
  public static function array_merge_nonempty(array $array1,array $array2) {
    
    $return = $array1;
    foreach ($array2 as $key => $value) {
      if (!empty($value)) $return[$key] = $value;
    }
    return $return;
  }

  static $path_options = array();

  public static function getPathOptions($bundle_id) {
    
    $options = &self::$path_options[$bundle_id];
    //if we already gathered the data, we can stop here
    if (empty($options)) {
      $options['uri'] = 'URI';
      //find all paths from all active pathbuilders
      $pbs = \Drupal::entityManager()->getStorage('wisski_pathbuilder')->loadMultiple();
      $paths = array();
      $flip = array();
      foreach ($pbs as $pb_id => $pb) {
        $pb_paths = $pb->getAllPaths();
        foreach ($pb_paths as $path) {
          $path_id = $path->getID();
          if ($bundle_id === $pb->getBundle($path_id)) {
            $options[$pb_id][$pb_id.'.'.$path_id] = $path->getName();
            $flip[$path_id][] = $bundle_id;
          }
        }
      }
    }
    //dpm(array('$bundle_id'=>$bundle_id,'result'=>$options,'flip'=>$flip),__METHOD__);
    return $options;
  }
  
  public static function getParentBundleIds($bundle_id,$get_labels=TRUE) {
    
    $pbs = \Drupal::entityManager()->getStorage('wisski_pathbuilder')->loadMultiple();
    $parents = array();
    foreach ($pbs as $pb_id => $pb) {
      $parent_id = $pb->getParentBundleId($bundle_id);
      if ($parent_id) {
        if ($get_labels) {
          $parents[$parent_id] = \Drupal\wisski_core\Entity\WisskiBundle::load($parent_id)->label();
        } else $parents[$parent_id] = $parent_id;
      }
    }
    return $parents;
  }
}