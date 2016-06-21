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
  
  /**
   * Splits the array in two parts at a given index.
   * Not tested with non-numeric indices. Expect keys to be re-arranged
   * @param $array the array to be split
   * @param $offset the index where to split the array. The entry at $offset will be the first in the
   * second part after splitting i.e. there will we a number of $offset elements in the first part
   * @return an array with two elements representing the first and second part of the original array
   */
  public static function array_split(array $array,int $offset=NULL) {
    
    if (is_null($offset)||$offset===0) return array(array(),$array);
    $count = count($array);
    if ($offset >= $count) return array($array,array());
    if (2*$offset < $count) {
      $rev = array_chunk(array_reverse($array),$count-$offset);
      $out = array();
      foreach ($rev as $chunk) {
        $out[] = array_reverse($chunk);
      }
      $out = array_reverse($out);
      while (count($out) < 2) $out[] = array();
      return $out;
    } else return array_chunk($array,$offset);
  }

  public static function array_insert(array $array,array $insertion,int $offset=NULL) {
    dpm(func_get_args(),__METHOD__);    
    if (is_null($offset)||$offset===0) return array_merge($insertion,$array);
    list($part1,$part2) = self::array_split($array,$offset);
    dpm(array($part1,$part2),__FUNCTION__);
    return array_merge($part1,$insertion,$part2);
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