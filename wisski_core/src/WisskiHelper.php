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
  
  /**
   * makes an array of arrays of n-grams from the string indexed by n.
   * @param $string the input string
   * @param $n the integer length of the n-grams, due to database restrictions we limit this to eight (8) when $db_limit is TRUE
   * @param $all_shorter bool indicating whether all n-grams with a smaller length shall be included, those will be stored in different sub-arrays
   * @param $min integer giving the shortest n-gram length, only used if $all_shorter == TRUE
   * @param $db_limit limits the output n-gram length to eight, see $n
   * @return two level array holding arrays with n-grams keyed by n
   */
  public static function str_n_grams($string,$n=5,$all_shorter=TRUE,$min=2,$db_limit=TRUE) {
    
    if (!is_int($n) || ($all_shorter && (!is_int($min) || $min > $n))) return NULL;
    if ($db_limit && $n > 8) {
      drupal_set_message('N-grams of length '.$n.' cannot be handled. Reduced to 8','notice');
      $n = 8;
    }
    $string = strtolower($string);
    return self::do_str_n_grams($string,$n,$all_shorter,$min);
  }
  
  /**
   * @see self::str_n_grams
   */
  private static function do_str_n_grams($string,$n,$all_shorter,$min) {
  
    $out = array();
    for ($i = 0; $i <= strlen($string)-$n; $i++) {
      $sub = trim(substr($string,$i,$n)," \t\n\r\0\x0B\.\,\;\"\'");
      if (strlen($sub) === $n) $out[] = $sub;
    }
    $out = array_unique($out);
    sort($out);
    if ($n === $min || !$all_shorter) return array($n => $out);      
    $all = self::do_str_n_grams($string,$n-1,TRUE,$min);
    $all[$n] = $out;
    return $all;
  }
}
