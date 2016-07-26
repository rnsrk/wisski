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
    
    if ($offset==0) return array(array(),$array);
    $count = count($array);
    if ($offset >= $count) return array($array,array());
    if (2*$offset < $count) {
      $rev = array_chunk(array_reverse($array),$count-$offset);
      $out = array();
      foreach ($rev as $chunk) {
        $out[] = array_reverse($chunk);
      }
      $out = array_reverse($out);
      
    } else $out = array_chunk($array,$offset);
    while (count($out) < 2) $out[] = array();
    return $out;
  }

  /**
   * inserts an array as subarray of another numerically indexed array
   * @param $array the array where the portion shall be inserted
   * @param $insertion the portion to be inserted
   * @param $offset the first index of the inserted sub-array after insertion
   * @return a re-indexed array with the subportion inserted
   */
  public static function array_insert(array $array,array $insertion,int $offset=NULL) {
    
    if ($offset==0) return array_merge($insertion,$array);
    list($part1,$part2) = self::array_split($array,$offset);
    return array_merge($part1,$insertion,$part2);
  }
  
  /**
   * Removes a portion of a numerically indexed array
   * @param $array the input array
   * @param $offset the first index to be removed
   * @param $the length of the portion to remove
   * @return an array resembling the input but with the specified part removed and re-indexed
   */
  public static function array_remove_part(array $array,int $offset,int $length=NULL) {
    
    if ($length == 0) return $array;
    list($part1,$part2) = self::array_split($array,$offset);
    list($lost,$part3) = self::array_split($part2,$length);
    return array_merge($part1,$part3);
  }

  /**
   * Gets the top bundles from the pathbuilders of the system
   * @param $get_labels useless
   * @return returns a list of top bundle ids
   */
  public static function getTopBundleIds($get_labels=FALSE) {
    
    $pbs = \Drupal::entityManager()->getStorage('wisski_pathbuilder')->loadMultiple();
    $parents = array();
    foreach ($pbs as $pb_id => $pb) {
      $pathtree = $pb->getPathTree();
      $pbarr = $pb->getPbPaths();
      
      foreach($pathtree as $key => $value) {
        if(!empty($pbarr[$key]['bundle']))
          $parents[$key] = $pbarr[$key]['bundle'];
      }
#      $parent_id = $pb->getParentBundleId($bundle_id);
#      if ($parent_id) {
#        if ($get_labels) {
#          $parents[$parent_id] = \Drupal\wisski_core\Entity\WisskiBundle::load($parent_id)->label();
#        } else $parents[$parent_id] = $parent_id;
#      }
    }
    return $parents;
  }
  
}
