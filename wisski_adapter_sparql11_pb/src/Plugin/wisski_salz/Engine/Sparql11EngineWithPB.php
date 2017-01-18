<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\Plugin\wisski_salz\Engine\Sparql11EngineWithPB.
 */

namespace Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine;

use Drupal\Core\Form\FormStateInterface;
use Drupal\wisski_salz\Plugin\wisski_salz\Engine\Sparql11Engine;
use Drupal\wisski_pathbuilder\PathbuilderEngineInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\wisski_salz\AdapterHelper;

use Drupal\wisski_core\Entity\WisskiEntity;

use Drupal\wisski_adapter_sparql11_pb\Query\Query;
use \EasyRdf;

/**
 * Wiki implementation of an external entity storage client.
 *
 * @Engine(
 *   id = "sparql11_with_pb",
 *   name = @Translation("Sparql 1.1 With Pathbuilder"),
 *   description = @Translation("Provides access to a SPARQL 1.1 endpoint and is configurable via a Pathbuilder")
 * )
 */
class Sparql11EngineWithPB extends Sparql11Engine implements PathbuilderEngineInterface  {
/*
  public function directQuery($query) {
  
    //ensure graph rewrite
    return parent::directQuery($query,TRUE);
  }
*/
  /******************* BASIC Pathbuilder Support ***********************/

  /**
   * {@inheritdoc}
   */
  public function providesFastMode() {
    return TRUE;
  }
  
  /**
   * {@inheritdoc}
   */
  public function providesCacheMode() {
  
    return TRUE;
  }
  
  /**
   * returns TRUE if the cache is pre-computed and ready to use, FALSE otherwise
   */
  public function isCacheSet() {
    //see $this->doTheReasoning()
    // and $this->getPropertiesFromCache() / $this->getClassesFromCache
    //the rasoner sets all reasoning based caches i.e. it is sufficient to check, that one of them is set
    
    //if ($cache = \Drupal::cache()->get('wisski_reasoner_properties')) return TRUE;
    //return FALSE;
    
    return $this->isPrepared();
  }

  /**
   * {@inheritdoc}
   * returns the possible next steps in path creation, if $this->providesFastMode() returns TRUE then this
   * MUST react fast i.e. in the blink of an eye if $fast_mode = TRUE and it MUST return the complete set of options if $fast_mode=FALSE
   * otherwise it should ignore the $fast_mode parameter
   */  
  public function getPathAlternatives($history = [], $future = [],$fast_mode=FALSE,$empty_uri='empty') {

#    \Drupal::logger('WissKI path alternatives: '.($fast_mode ? 'fast mode' : "normal mode"))->debug('History: '.serialize($history)."\n".'Future: '.serialize($future));
    
    $search_properties = NULL;
    
    $last = NULL;
    if (!empty($history)) {
      $candidate = array_pop($history);
      if ($candidate === $empty_uri) {
//        \Drupal::logger('WissKI path alternatives')->error('Not a valid URI: "'.$candidate.'"');
        //as a fallback we assume that the full history is given so that every second step is a property
        //we have already popped one element, so count($history) is even when we need a property
        $search_properties = (0 === count($history) % 2);
      }
      elseif ($this->isValidUri('<'.$candidate.'>')) {
        $last = $candidate;
        if ($this->isAProperty($last) === FALSE) $search_properties = TRUE; 
      } else {
        \Drupal::logger('WissKI path alternatives')->debug('invalid URI '.$candidate);
        return array();
      }
    }
    
    $next = NULL;
    if (!empty($future)) {
      $candidate = array_shift($future);
      if ($candidate !== $empty_uri) {
        if ($this->isValidUri('<'.$candidate.'>')) {
          $next = $candidate;
          if ($search_properties === NULL) {
            if ($this->isAProperty($next) === FALSE) $search_properties = TRUE;
          } elseif ($this->isAProperty($next) === $search_properties) {
            drupal_set_message('History and Future are inconsistent','error');
          }
        } else {
          \Drupal::logger('WissKI path alternatives')->debug('invalid URI '.$candidate);
          return array();
        }
      }
    }
    
#    \Drupal::logger('WissKI next '.($search_properties ? 'properties' : 'classes'))->debug('Last: '.$last.', Next: '.$next);
    //$search_properties is TRUE if and only if last and next are valid URIs and no owl:Class-es
    if ($search_properties) {
      $return = $this->nextProperties($last,$next,$fast_mode);
    } else {
      $return = $this->nextClasses($last,$next,$fast_mode);
    }
//    dpm(func_get_args()+array('result'=>$return),__FUNCTION__);
    return $return;
  }
  
  /**
   * @{inheritdoc}
   */
//  public function getPathAlternatives($history = [], $future = []) {
//
//  \Drupal::logger('WissKI SPARQL Client')->debug("normal mode");
//    if (empty($history) && empty($future)) {
//      
//      return $this->getClasses();
//
//    } elseif (!empty($history)) {
//      
//      $last = array_pop($history);
//      $next = empty($future) ? NULL : $future[0];
//
//      if ($this->isaProperty($last)) {
//        return $this->nextClasses($last, $next);
//      } else {
//        return $this->nextProperties($last, $next);
//      }
//    } elseif (!empty($future)) {
//      $next = $future[0];
//      if ($this->isaProperty($next))
//        return $this->getClasses();
//      else
//        return $this->getProperties();
//    } else {
//      return [];
//    }
//
//    
//  }
  
  /**
   * @{inheritdoc}
   */
  public function getPrimitiveMapping($step) {
    
    $info = [];

    // this might need to be adjusted for other standards than rdf/owl
    $query = 
      "SELECT DISTINCT ?property "
      ."WHERE { "
        ."?property a owl:DatatypeProperty. "
#        ."?property rdfs:domain ?d_superclass. "
#        ."<$step> rdfs:subClassOf* ?d_superclass. }"
      ;
      
      // By Mark: TODO: Please check this. I have absolutely
      // no idea what this does, I just copied it from below
      // and I really really hope that Dorian did know what it
      // does and it will work forever.      
      $query .= 
        "{"
          ."{?d_def_prop rdfs:domain ?d_def_class.}"
          ." UNION "
          ."{"
            ."?d_def_prop owl:inverseOf ?inv. "
            ."?inv rdfs:range ?d_def_class. "
          ."}"
        ."} "
        ."<$step> rdfs:subClassOf* ?d_def_class. "
        ."{"
          ."{?d_def_prop rdfs:subPropertyOf* ?property.}"
          ." UNION "
          ."{ "
            ."?property rdfs:subPropertyOf+ ?d_def_prop. "
            ." FILTER NOT EXISTS {"
              ."{ "
                ."?mid_prop rdfs:subPropertyOf+ ?d_def_prop. "
                ."?property rdfs:subPropertyOf* ?mid_prop. "
              ."}"
              ."{"
                ."{?mid_prop rdfs:domain ?any_domain.}"
                ." UNION "
                ."{ "
                  ."?mid_prop owl:inverseOf ?mid_inv. "
                  ."?mid_inv rdfs:range ?any_range. "
                ."}"
              ."}"
            ."}"
          ."}"
        ."}}";

    $result = $this->directQuery($query);
#    dpm($result, 'res');

    if (count($result) == 0) return array();
    
    $output = array();
    foreach ($result as $obj) {
      $prop = $obj->property->getUri();
      $output[$prop] = $prop;
    }
    uksort($output,'strnatcasecmp');
    return $output;
  } 

  public function getStepInfo($step, $history = [], $future = []) {
    
    $info = [];

    $query = "SELECT DISTINCT ?label WHERE { <$step> <http://www.w3.org/2000/01/rdf-schema#label> ?label . } LIMIT 1";
    $result = $this->directQuery($query);
    if (count($result) > 0) {
      $info['label'] = $result[0]->label->getValue();
    }

    $query = "SELECT DISTINCT ?comment WHERE { <$step> <http://www.w3.org/2000/01/rdf-schema#comment> ?comment . } LIMIT 1";
    $result = $this->directQuery($query);
    if (count($result) > 0) {
      $info['comment'] = $result[0]->comment->getValue();
    }


    return $info;
  }

  
  
  public function isaProperty($p) {
    
    return $this->directQuery("ASK { <$p> a owl:ObjectProperty . }")->isTrue();

  }


  public function getClasses() {
  
    $out = $this->retrieve('classes','class');
    if (!empty($out)) return $out;
    $query = "SELECT DISTINCT ?class WHERE { ?class a owl:Class . FILTER(!isBlank(?class))}";  
    $result = $this->directQuery($query);
    
    if (count($result) > 0) {
      $out = array();
      foreach ($result as $obj) {
        $class = $obj->class->getUri();
        $out[$class] = $class;
      }
      uksort($out,'strnatcasecmp');
      return $out;
    }
    return FALSE;
  }
  
  public function getProperties() {
  
    $out = $this->retrieve('properties','property');
    if (!empty($out)) return $out;
    $query = "SELECT DISTINCT ?property WHERE { ?property a owl:ObjectProperty . FILTER(!isBlank(?property))}";  
    $result = $this->directQuery($query);
    
    if (count($result) > 0) {
      $out = array();
      foreach ($result as $obj) {
        $class = $obj->property->getUri();
        $out[$class] = $class;
      }
      uksort($out,'strnatcasecmp');
      return $out;
    }
    return FALSE;
  }

  public function nextProperties($class=NULL,$class_after = NULL,$fast_mode=FALSE) {

    if (!isset($class) && !isset($class_after)) return $this->getProperties();
#    \Drupal::logger(__METHOD__)->debug('class: '.$class.', class_after: '.$class_after);
    $output = $this->getPropertiesFromCache($class,$class_after);
    if ($output === FALSE) {
      //drupal_set_message('none in cache');
      $output = $this->getPropertiesFromStore($class,$class_after,$fast_mode);
    }
    uksort($output,'strnatcasecmp');
    return $output;
  }

  /**
   * returns an array of properties for which domain and/or range match the input
   * @param an associative array with keys 'domain' and/or 'range'
   * @return array of matching properties | FALSE if there was no cache data
   */
  protected function getPropertiesFromCache($class,$class_after = NULL) {

/* cache version
    $dom_properties = array();
    $cid = 'wisski_reasoner_reverse_domains';
    if ($cache = \Drupal::cache()->get($cid)) {
      $dom_properties = $cache->data[$class]?:array();
    } else return FALSE;
    $rng_properties = array();
    if (isset($class_after)) {
      $cid = 'wisski_reasoner_reverse_ranges';
      if ($cache = \Drupal::cache()->get($cid)) {
        $rng_properties = $cache->data[$class_after]?:array();
      } else return FALSE;
    } else return $dom_properties;
    return array_intersect_key($dom_properties,$rng_properties);
    */
    
    //DB version
    $dom_properties = $this->retrieve('domains','property','class',$class);
    if (isset($class_after)) $rng_properties = $this->retrieve('ranges','property','class',$class_after);
    else return $dom_properties;
    return array_intersect_key($dom_properties,$rng_properties);
  }

  public function getPropertiesFromStore($class=NULL,$class_after = NULL,$fast_mode=FALSE) {

    $query = "SELECT DISTINCT ?property WHERE {"
      ."?property a owl:ObjectProperty. ";
    if ($fast_mode) {  
      if (isset($class)) $query .= "?property rdfs:domain <$class>. ";
      if (isset($class_after)) $query .= "?property rdfs:range <$class_after>.";
    } else {
      if (isset($class)) {
        $query .= 
          "{"
            ."{?d_def_prop rdfs:domain ?d_def_class.}"
            ." UNION "
            ."{"
              ."?d_def_prop owl:inverseOf ?inv. "
              ."?inv rdfs:range ?d_def_class. "
            ."}"
          ."} "
          ."<$class> rdfs:subClassOf* ?d_def_class. "
          ."{"
            ."{?d_def_prop rdfs:subPropertyOf* ?property.}"
            ." UNION "
            ."{ "
              ."?property rdfs:subPropertyOf+ ?d_def_prop. "
              ." FILTER NOT EXISTS {"
                ."{ "
                  ."?mid_prop rdfs:subPropertyOf+ ?d_def_prop. "
                  ."?property rdfs:subPropertyOf* ?mid_prop. "
                ."}"
                ."{"
                  ."{?mid_prop rdfs:domain ?any_domain.}"
                  ." UNION "
                  ."{ "
                    ."?mid_prop owl:inverseOf ?mid_inv. "
                    ."?mid_inv rdfs:range ?any_range. "
                  ."}"
                ."}"
              ."}"
            ."}"
          ."}";
      }
      if (isset($class_after)) {
        $query .= "{"
            ."{ "
                ."{?r_def_prop rdfs:range ?r_def_class.} "
                ."UNION "
                ."{ "
                  ."?r_def_prop owl:inverseOf ?inv. "
                  ."?inv rdfs:domain ?inv. "
                ."} "
              ."} "
            ."<$class_after> rdfs:subClassOf* ?r_def_class. "
          ."}"
          ."{"
            ."{?r_def_prop rdfs:subPropertyOf* ?property.} "
          ."UNION "
            ."{ "
              ."?property rdfs:subPropertyOf+ ?r_def_prop. "
              ."FILTER NOT EXISTS { "
                ."{ "
                  ."?mid_prop rdfs:subPropertyOf+ ?r_def_prop. "
                  ."?property rdfs:subPropertyOf* ?mid_prop. "
                ."} "
                ."{?mid_prop rdfs:range ?any_range.}"
                  ." UNION "
                  ."{ "
                    ."?mid_prop owl:inverseOf ?mid_inv. "
                    ."?mid_inv rdfs:domain ?any_domain. "
                  ."}"
                ."}"
              ."} "
            ."}"
          ."} ";
      }  
    }
    $query .= "}";
    $result = $this->directQuery($query);
    $output = array();
    foreach ($result as $obj) {
      $prop = $obj->property->getUri();
      $output[$prop] = $prop;
    }
    return $output;
  }

  public function nextClasses($property=NULL,$property_after = NULL,$fast_mode=FALSE) {
    
    if (!isset($property) && !isset($property_after)) return $this->getClasses();
#    \Drupal::logger(__METHOD__)->debug('property: '.$property.', property_after: '.$property_after);
    $output = $this->getClassesFromCache($property,$property_after);
    if ($output === FALSE) {
      //drupal_set_message('none in cache');
      $output = $this->getClassesFromStore($property,$property_after,$fast_mode);
    }
    uksort($output,'strnatcasecmp');
    return $output;
  }

  protected function getClassesFromCache($property,$property_after = NULL) {

  /* cache version
    $dom_classes = array();
    $cid = 'wisski_reasoner_ranges';
    if ($cache = \Drupal::cache()->get($cid)) {
      $rng_classes = $cache->data[$property]?:array();
    } else return FALSE;
    $dom_classes = array();
    if (isset($property_after)) {
      $cid = 'wisski_reasoner_domains';
      if ($cache = \Drupal::cache()->get($cid)) {
        $dom_classes = $cache->data[$property_after]?:array();
      } else return FALSE;
    } else return $rng_classes;
    return array_intersect_key($rng_classes,$dom_classes);
    */
    
    //DB version
    $rng_classes = $this->retrieve('ranges','class','property',$property);
    if (isset($property_after)) $dom_classes = $this->retrieve('domains','class','property',$property_after);
    else return $rng_classes;
    return array_intersect_key($rng_classes,$dom_classes);
  }

  public function getClassesFromStore($property=NULL,$property_after = NULL,$fast_mode=FALSE) {
  
    $query = "SELECT DISTINCT ?class WHERE {"
      ."?class a owl:Class. ";
    if ($fast_mode) {  
      if (isset($property)) $query .= "<$property> rdfs:range ?class. ";
      if (isset($property_after)) $query .= "<$property_after> rdfs:domain ?class. ";
    } else {
      if (isset($property)) {
        $query .= "<$property> rdfs:subPropertyOf* ?r_super_prop. "
          ."?r_super_prop rdfs:range ?r_super_class. "
          ."FILTER NOT EXISTS { "
            ."?r_sub_prop rdfs:subPropertyOf+ ?r_super_prop. "
            ."<$property> rdfs:subPropertyOf* ?r_sub_prop. "
            ."?r_sub_prop rdfs:range ?r_any_class. "
          ."} "
          ."?class rdfs:subClassOf* ?r_super_class. ";
      }
      if (isset($property_after)) {
        $query .= "<$property_after> rdfs:subPropertyOf* ?d_super_prop. "
          ."?d_super_prop rdfs:domain ?d_super_class. "
          ."FILTER NOT EXISTS { "
            ."?d_sub_prop rdfs:subPropertyOf+ ?d_super_prop. "
            ."<$property_after> rdfs:subPropertyOf* ?d_sub_prop. "
            ."?d_sub_prop rdfs:domain ?d_any_class. "
          ."} "
          ."?class rdfs:subClassOf* ?d_super_class. ";
      }  
    }
    $query .= "}";
    $result = $this->directQuery($query);
    
    if (count($result) == 0) return array();

    $output = array();
    foreach ($result as $obj) {
      $class = $obj->class->getUri();
      $output[$class] = $class;
    }
    natsort($output);
    return $output;

  }

  /******************* End of BASIC Pathbuilder Support ***********************/

  // copy from yaml-adapter - likes camels.
  
  private $entity_info;

  /*
   * Load the image data for a given entity id
   * @return an array of values?
   *
   */
  public function getImagesForEntityId($entityid, $bundleid) {
    $pbs = $this->getPbsForThis();

    $entityid = $this->getDrupalId($entityid);
    
    $ret = array();
      
    foreach($pbs as $pb) {
#    drupal_set_message("yay!" . $entityid . " and " . $bundleid);
    
      $groups = $pb->getGroupsForBundle($bundleid);
    
      foreach($groups as $group) {
        $paths = $pb->getImagePathIDsForGroup($group->id());
    
#      drupal_set_message("paths: " . serialize($paths));
            
        foreach($paths as $pathid) {
    
          $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($pathid);
        
#        drupal_set_message(serialize($path));
        
#        drupal_set_message("thing: " . serialize($this->pathToReturnValue($path->getPathArray(), $path->getDatatypeProperty(), $entityid, 0, NULL, 0)));
        
          // this has to be an absulte path - otherwise subgroup images won't load.
          $new_ret = $this->pathToReturnValue($path, $pb, $entityid, 0, NULL, FALSE);
#          if (!empty($new_ret)) dpm($pb->id().' '.$pathid.' '.$entitid,'News');
          $ret = array_merge($ret, $new_ret);
          
        } 
      }
    }    
#    drupal_set_message("returning: " . serialize($ret));
    //dpm($ret,__FUNCTION__);
    return $ret;
  }
  
  /**
   *
   *
   *
   */
  public function getBundleIdsForEntityId($entityid) {
        
    $pbs = $this->getPbsForThis();
#    dpm($pb,$this->adapterId().' Pathbuilder');
#    dpm($entityid, "eid");

    $uri = $this->getUriForDrupalId($entityid);    
    
    #$uri = str_replace('\\', '/', $entityid);

#    drupal_set_message("parse url: " . serialize(parse_url($uri)));

    $url = parse_url($uri);

    if(!empty($url["scheme"]))
      $query = "SELECT ?class WHERE { <" . $uri . "> a ?class }";
    else {
      //it is possible, that we got an entity URI instead of an entity ID here, so try that one first
      $url = parse_url($entityid);
      if (!empty($url['scheme'])) $entityid = '<'.$entityid.'>';
      $query = "SELECT ?class WHERE { " . $entityid . " a ?class }";
    }
    
    $result = $this->directQuery($query);
    
#    drupal_set_message("res: " . serialize($result));
    
   $out = array();
    foreach($result as $thing) {
      foreach($pbs as $pb) {
      // ask for a bundle from the pb that has this class thing in it	
        $groups = $pb->getAllGroups();
      
#      drupal_set_message("groups: " . count($groups) . " " . serialize($groups));

        $i = 0;
      
        foreach($groups as $group) {
        // this does not work for subgroups
        #$path_array = $group->getPathArray();
                
#        $path_array = $this->getClearPathArray($group, $pb);
          $path_array = $this->getClearGroupArray($group, $pb);
          $i++;
 
#        drupal_set_message("p_a " . $i . " " . $group->getName() . " " . serialize($path_array));
        
          if(empty($group) || empty($path_array))
            continue;

        // this checks if the last element is the same
        // however this is evil whenever there are several elements in the path array
        // typically subgroups ask for the first element part.        
          if($path_array[ count($path_array)-1] == $thing->class->dumpValue("text") || $path_array[0] == $thing->class->dumpValue("text")) {
            $pbpaths = $pb->getPbPaths();
          
#          drupal_set_message(serialize($pbpaths[$group->id()]));
          
            if(!empty($pbpaths[$group->id()])) {
              $elem = $pbpaths[$group->id()];
            // priorize top groups to the front if the array
#            dpm($elem);
              if(empty($elem['parent'])) {
#              dpm("I resort...");
                $tmpout = $out;
                $out = array();
                $out[$pbpaths[$group->id()]['bundle']] = $pbpaths[$group->id()]['bundle'];
                $out = array_merge($out, $tmpout);
              }
              $out[$pbpaths[$group->id()]['bundle']] = $pbpaths[$group->id()]['bundle'];
            }
          }
        }
      }
    }

#    drupal_set_message("serializing out: " . serialize($out));

    return $out;    
    
  }
  
  /**
   * Gets the array part to get from one subgroup to another
   *
   */
  public function getClearGroupArray($group, $pb) {
    // we have to modify the group-array in case of jumps
    // from one subgroup to another
    // if you have a groups with grouppaths:
    // g1: x0
    // g2: x0 y0 x1
    // g3: x0 y0 x1 y1 x2 y2 x3
    // then the way from g2 to g3 is x1 y1 x2 y2 x3
    // this should be calculated here.
    $patharraytoget = $group->getPathArray();
    $allpbpaths = $pb->getPbPaths();
    $pbarray = $allpbpaths[$group->id()];

    // do some error handling    
    if(!$group->isGroup()) {
      drupal_set_message("getClearGroupArray called with something that is not a group: " . serialize($group), "error");
      return;
    }
        
    // if we are a top group, won't do anything.
    if($pbarray['parent'] > 0) {
        
      // first we have to calculate our own ClearPathArray
      $clearGroupArray = $this->getClearPathArray($group, $pb);
    
      // then we have to get our parents array
      $pbparentarray = $allpbpaths[$pbarray['parent']];
      
      $parentpath = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($pbarray["parent"]);
      
      // if there is nothing, do nothing!
      // I am unsure if that ever could occur
      if(empty($parentpath))
        return;
      
      // -1 because we don't want to cut our own concept
      $parentcnt = count($parentpath->getPathArray())-1;

#      drupal_set_message("before cut: " . serialize($patharraytoget));
      
      for($i=0; $i<$parentcnt; $i++) {
        unset($patharraytoget[$i]);
      }
      
#      drupal_set_message("in between: " . serialize($patharraytoget));
      
      $patharraytoget = array_values($patharraytoget);
      
#      drupal_set_message("cga: " . serialize($clearGroupArray));
      
      $max = count($patharraytoget);
      
      // we have to cut away everything that is in $cleargrouparray
      // so we take the whole length and subtract that as a starting point
      // and go up from there
      for($i=(count($patharraytoget)-count($clearGroupArray)+1);$i<$max;$i++)
        unset($patharraytoget[$i]);
      
#      drupal_set_message("after cut: " . serialize($patharraytoget));
      
      $patharraytoget = array_values($patharraytoget);      
      
    }
    return $patharraytoget;    
  }
  
  /**
   * This is Obsolete!
   *
   * Gets the common part of a group or path
   * that is clean from subgroup-fragments
   */
  public function getClearPathArray($path, $pb) {
    // We have to modify the path-array in case of subgroups.
    // Usually if we have a subgroup path x0 y0 x1 we have to skip x0 y0 in
    // the paths of the group.
    if (!is_object($path) || !is_object($pb)) {
      drupal_set_message('getClearPathArray found no path or no pathbuilder. Error!', 'error');
      return array();
    }
    
    $patharraytoget = $path->getPathArray();
    $allpbpaths = $pb->getPbPaths();
    $pbarray = $allpbpaths[$path->id()];
    
#    dpm($pbarray, "pbarray!");
    // is it in a group?
    if(!empty($pbarray['parent'])) {

      $pbparentarray = $allpbpaths[$pbarray['parent']];
      
      // how many path-parts are in the pb-parent?
      $parentpath = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($pbarray["parent"]);
            
      // if there is nothing, do nothing!
      // I am unsure if that ever could occur
      if(empty($parentpath))
        continue;
      
      
      // we have to handle groups other than paths
      if($path->isGroup()) {
        // so this is a subgroup?
        // in this case we have to strip the path of the parent and
        // one object property from our path
        $pathcnt = count($parentpath->getPathArray()) +1;

        // strip exactly that.
        for($i=0; $i< $pathcnt; $i++) {
          unset($patharraytoget[$i]);
        }        
      
      } else {
        // this is no subgroup, it is a path
#        if(!empty($pbparentarray['parent'])) {
          // only do something if it is a path in a subgroup, not in a main group  
          
#          $parentparentpath = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($pbparentarray["parent"]);
          
          // in that case we have to remove the subgroup-part, however minus one, as it is the       
#          $pathcnt = count($parentpath->getPathArray()) - count($this->getClearPathArray($parentpath, $pb));
          $pathcnt = count($parentpath->getPathArray()) - 1; #count($parentparentpath->getPathArray());        

#          dpm($pathcnt, "pathcnt");
#          dpm($parentpath->getPathArray(), "pa!");
        
          for($i=0; $i< $pathcnt; $i++) {
            unset($patharraytoget[$i]);
          }
#        }
      }
    }
          
#          drupal_set_message("parent is: " . serialize($pbparentarray));
          
#          drupal_set_message("I am getting: " . serialize($patharraytoget));
          
    $patharraytoget = array_values($patharraytoget);
    
    return $patharraytoget;
  }

  /**
   * Gets the bundle and loads every individual in the TS
   * and returns an array of ids if there is something...
   *
   */ 
  public function loadIndividualsForBundle($bundleid, $pathbuilder, $limit = NULL, $offset = NULL, $count = FALSE, $conditions = FALSE) {

    $conds = array();
    // see if we have any conditions
    foreach($conditions as $cond) {
      if($cond["field"] != "bundle") {
        // get pb entries
        $pbentries = $pathbuilder->getPbEntriesForFid($cond["field"]);
        
        if(empty($pbentries))
          continue;
        
        $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($pbentries['id']);
        
        if(empty($path))
          continue;
        
        $conds[] = $path;
      }
    }

    // build the query
    if(!empty($count))
      $query = "SELECT (COUNT(?x0) as ?cnt) WHERE {";
    else
      $query = "SELECT ?x0 WHERE {";
    

    if(empty($conds)) {
      
      // there should be someone asking for more than one...
      $groups = $pathbuilder->getGroupsForBundle($bundleid);
      
      
      // no group defined in this pb - return   
      if(empty($groups)) {
        if ($count) return 0;
        return array();
      }

      // for now simply take the first one
      // in future: iterate here!
      // @TODO!
      $group = $groups[0];
    
      // get the group 
      // this does not work for subgroups! do it otherwise!
      #$grouppath = $group->getPathArray();    
      $grouppath = $this->getClearPathArray($group, $pathbuilder);
                   
      foreach($grouppath as $key => $pathpart) {
        if($key % 2 == 0)
          $query .= " ?x" . $key . " a <". $pathpart . "> . ";
        else
          $query .= " ?x" . ($key-1) . " <" . $pathpart . "> ?x" . ($key+1) . " . "; 
      }
    } else {
      foreach($conds as $path) {
        $query .= $this->generateTriplesForPath($pathbuilder, $path, '', NULL, NULL, 0, 0, FALSE);
      }
    }

    $query .= "}";
    
    if(is_null($limit) == FALSE && is_null($offset) == FALSE && empty($count))
      $query .= " LIMIT $limit OFFSET $offset ";
     
#    drupal_set_message("query: " . serialize($query) . " and " . microtime());
    
#    return;
    //dpm($query,__FUNCTION__.' '.$this->adapterId());
    // ask for the query

    $result = $this->directQuery($query);

    $outarr = array();

    // for now simply take the first element
    // later on we need names here!
    foreach($result as $thing) {

      // if it is a count query, return the integer      
      if(!empty($count)) {
        //dpm($thing,'Count Thing');
        return $thing->cnt->getValue();
      }
      
      $uri = $thing->x0->dumpValue("text");
      
      #$uri = str_replace('/','\\',$uri);
      // this is no uri anymore - rename this variable.
      
      $uriname = $this->getDrupalId($uri);
      
      // store the bundleid to the bundle-cache as it might be important
      // for subsequent queries.      
      $pathbuilder->setBundleIdForEntityId($uriname, $bundleid);

      $outarr[$uriname] = array('eid' => $uriname, 'bundle' => $bundleid, 'name' => $uri);
    }
    //dpm($outarr, "outarr");

    #    return;

    if (empty($outarr) && $count) return 0;
    return $outarr;
  }

  public function loadEntity($id) {
#    drupal_set_message("b1: $id " . microtime());
        
    $out = array();
#    $uri = str_replace('\\', '/', $id);

    $uri = $this->getUriForDrupalId($id);
#    dpm(serialize($uri), 'uri!');

#    drupal_set_message("parse url: " . serialize(parse_url($uri)));

    $url = parse_url($uri);
#    drupal_set_message("b2: " . microtime());

    if(!empty($url["scheme"]))
      $query = "SELECT * WHERE { { <$uri> ?p ?o } UNION { ?s ?p <$uri> } } LIMIT 1"; 
    else
      $query = 'SELECT * WHERE { ?s ?p "' . $id . '" } LIMIT 1';  
#    drupal_set_message("b3: " . microtime());    
    $result = $this->directQuery($query);
#    drupal_set_message("b4: " . microtime());
    foreach($result as $thing) {
#      $uri = $thing->s->dumpValue("text");
#      $uri = str_replace('/','\\',$uri);
      $out = array('eid' => $id, 'bundle' => 'e21_person', 'name' => 'frizt');

#      $out[$uri] = array('eid' => $uri, 'bundle' => 'e21_person', 'name' => 'frizt');#$thing->s->dumpValue("text"), 'bundle' => 'e21_person', 'name' => 'frizt');
#      $i++;
    }
#    drupal_set_message("b5: " . microtime());
#    drupal_set_message("load single");
    
    return $out;
  }
  
  public function loadMultipleEntities($ids = NULL) {
#    dpm($this->getConfiguration());
#    $this->entity_info = Yaml::parse($this->entity_string);
#    dpm($this->entity_info,__METHOD__);
#    if (is_null($ids)) return $this->entity_info;
    $query = "SELECT ?s WHERE { ?s a/a owl:Class}";
    
    $result = $this->directQuery($query);
    
#    drupal_set_message(serialize($result));
    
    $out = array();
    foreach($result as $thing) {
      
      $uri = $thing->s->dumpValue("text");
      #$uri = str_replace('/','\\',$uri);
      
      $uri = $this->getUriForDrupalId($uri);
    
#      drupal_set_message("my uri is: " . htmlentities($uri));
      
      $out[$uri] = array('eid' => $uri, 'bundle' => 'e21_person', 'name' => 'frizt');
    }
    
#    drupal_set_message("load Mult...");
    
    return $out;
    
    return array_intersect_key($this->entity_info,array_flip($ids));
  }
    
  /**
   * @inheritdoc
   */
  public function hasEntity($entity_id) {
 
#    dpm($entity_id, "eid");
    
    $ent = $this->loadEntity($entity_id);

#    dpm(!empty($ent), "ent");

    return !empty($ent);
  }
  
  /**
   * The elemental data aggregation function
   * fetches the data for display purpose
   */
  public function pathToReturnValue($path, $pb, $eid = NULL, $position = 0, $main_property = NULL, $relative = TRUE) {

    if(!$path->isGroup())
      $primitive = $path->getDatatypeProperty();
    else
      $primitive = NULL;
      
    $disamb = $path->getDisamb();
    
    // also
    if($disamb > 0)
      $disamb = ($disamb-1)*2;
    else
      $disamb = NULL;

    $sparql = "SELECT DISTINCT * WHERE { ";

    if(!empty($eid)) {
      // rename to uri
      $eid = $this->getUriForDrupalId($eid);
      
#      $eid = str_replace("\\", "/", $eid);
#      $url = parse_url($eid);
      
      $starting_position = count($path->getPathArray()) - count($pb->getRelativePath($path));
      
      // if the path is a group it has to be a subgroup and thus entity reference.
      if($path->isGroup()) {
        // it is the same as field - so entity_reference is basic shit here
        $sparql .= $this->generateTriplesForPath($pb, $path, '', $eid, NULL, 0,  ($starting_position/2), FALSE, NULL, 'entity_reference');
      }
      else {
        $sparql .= $this->generateTriplesForPath($pb, $path, '', $eid, NULL, 0, ($starting_position/2), FALSE, NULL, 'field', $relative);
      }

    } else {
      drupal_set_message("No EID for data. Error. ", 'error');
    }

    $sparql .= " } ";

    $result = $this->directQuery($sparql);

    $out = array();
    foreach($result as $thing) {
      
      // if $thing is just a true or not true statement
      if($thing == new \StdClass()) {
        // we continue
        continue;
      }
      
#      $name = 'x' . (count($patharray)-1);
      $name = 'x' . (count($path->getPathArray())-1);
      if(!empty($primitive) && $primitive != "empty") {
        if(empty($main_property)) {
          $out[] = $thing->out->getValue();
        } else {
          
          $outvalue = $thing->out->getValue();

          // special case: DateTime... render this as normal value for now.
          if(is_a($outvalue, "DateTime")) {
            $outvalue = (string)$outvalue->format('Y-m-d\TH:i:s.u');;
          }
          
#          if($main_property == "target_id")
#            $outvalue = $this->getDrupalId($outvalue);
          
          if(is_null($disamb) == TRUE) {
            $out[] = array($main_property => $outvalue);
          }
          else {
            $disambname = 'x'.$disamb;
            if(!isset($thing->{$disambname})) {
              $out[] = array($main_property => $outvalue);
            }
            else {
              $out[] = array($main_property => $outvalue, 'wisskiDisamb' => $thing->{$disambname}->dumpValue("text"));
            }
          }
        }
      } else {
        if(empty($main_property)) {
          $out[] = $thing->{$name}->dumpValue("text");
        } else { 
          $outvalue = $thing->{$name}->dumpValue("text");
          
#          if($main_property == "target_id")
#            $outvalue = $this->getDrupalId($outvalue);
        
          if(is_null($disamb) == TRUE)
            $out[] = array($main_property => $outvalue);
          else {
            $disambname = 'x'.$disamb;
            if(!isset($thing->{$disambname}))
              $out[] = array($main_property => $outvalue);
            else
              $out[] = array($main_property => $outvalue, 'wisskiDisamb' => $thing->{$disambname}->dumpValue("text"));
          }
        }
      }
    }
    
#dpm($out, __METHOD__);
    return $out;
    
  }
  
  /**
   * @inheritdoc
   */
  public function loadFieldValues(array $entity_ids = NULL, array $field_ids = NULL, $bundleid_in = NULL, $language = LanguageInterface::LANGCODE_DEFAULT) {

    // tricky thing here is that the entity_ids that are coming in typically
    // are somewhere from a store. In case of rdf it is easy - they are uris.
    // In case of csv or something it is more tricky. So I don't wan't to 
    // simply go to the store and tell it "give me the bundle of this".
    // The field ids come in handy here - fields are typically attached
    // to a bundle anyway. so I just get the bundle from there. I think it is
    // rather stupid that this function does not load the field values per 
    // bundle - it is implicitely anyway like that.
    // 
    // so I ignore everything and just target the field_ids that are mapped to
    // paths in the pathbuilder.

#    drupal_set_message("I am asked for " . serialize($entity_ids) . " and fields: " . serialize($field_ids));

    // this approach will be not fast enough in the future...
    // the pbs have to have a better mapping of where and how to find fields
    $pbs = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::loadMultiple();
    
    $out = array();
        
    // get the adapterid that was loaded
    // haha, this is the engine-id...
    //$adapterid = $this->getConfiguration()['id'];
        
    foreach($pbs as $pb) {
      
      // if we have no adapter for this pb it may go home.
      if(empty($pb->getAdapterId()))
        continue;
        
      $adapter = \Drupal\wisski_salz\Entity\Adapter::load($pb->getAdapterId());

      // if we have not adapter, we may go home, too
      if(empty($adapter))
        continue;
      
      // if he didn't ask for us...    
      if($this->getConfiguration()['id'] != $adapter->getEngine()->getConfiguration()['id'])
        continue;
        
      // if we find any data, we set this to true.
      $found_any_data = FALSE;
        
      foreach($field_ids as $fkey => $fieldid) {  
        #drupal_set_message("for field " . $fieldid . " with bundle " . $bundleid_in . " I've got " . serialize($this->loadPropertyValuesForField($fieldid, array(), $entity_ids, $bundleid_in, $language)));

        $got = $this->loadPropertyValuesForField($fieldid, array(), $entity_ids, $bundleid_in, $language);

#        drupal_set_message("I've got: " . serialize($got));
        
        if (empty($out)) {
          $out = $got;
        } else {
          foreach($got as $eid => $value) {
            if(empty($out[$eid])) {
              $out[$eid] = $got[$eid];
            } else {
              $out[$eid] = array_merge($out[$eid], $got[$eid]);
            }
          }
        }
        
#        drupal_set_message("out after got: " . serialize($out));
      }
      
#      drupal_set_message("out is empty? " . serialize($out) . serialize(empty($out)));
      
      // @TODO this is a hack.
      // if we did not find any data we unset this part so we don't return anything
      // however this might be evil in cases of edit or something...
      if(empty($out))
        return array();
    }
    
#    drupal_set_message("I return: " . serialize($out));
  
    
    return $out;

  }

  /**
   * @inheritdoc
   * The Yaml-Adapter cannot handle field properties, we insist on field values being the main property
   */
  public function loadPropertyValuesForField($field_id, array $property_ids, array $entity_ids = NULL, $bundleid_in = NULL, $language = LanguageInterface::LANGCODE_DEFAULT) {
#    drupal_set_message("a1: " . microtime());
#    drupal_set_message("fun: " . serialize(func_get_args()));
#    drupal_set_message("2");
#   
#    drupal_set_message("muha: " . serialize($field_id));
    $field_storage_config = \Drupal\field\Entity\FieldStorageConfig::loadByName('wisski_individual', $field_id);#->getItemDefinition()->mainPropertyName();
    if(!empty($field_storage_config))
      $main_property = $field_storage_config->getMainPropertyName();
#     drupal_set_message("mp: " . serialize($main_property) . "for field " . serialize($field_id));
#    if (in_array($main_property,$property_ids)) {
#      return $this->loadFieldValues($entity_ids,array($field_id),$language);
#    }
#    return array();

    if(!empty($field_id) && empty($bundleid_in)) {
      drupal_set_message("Dorian ist doof, weil $field_id angefragt wurde und bundle aber leer ist.", "error");
      return;
    }
    
    // this approach will be not fast enough in the future...
    // the pbs have to have a better mapping of where and how to find fields
    $pbs = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::loadMultiple();
    
    $out = array();
        
    // get the adapterid that was loaded
    // haha, this is the engine-id...
    //$adapterid = $this->getConfiguration()['id'];
        
    foreach($pbs as $pb) {
#      drupal_set_message("a2: " . microtime());
      // if we have no adapter for this pb it may go home.
      if(empty($pb->getAdapterId()))
        continue;
        
      $adapter = \Drupal\wisski_salz\Entity\Adapter::load($pb->getAdapterId());

      // if we have not adapter, we may go home, too
      if(empty($adapter))
        continue;
      
      // if he didn't ask for us...    
      if($this->adapterId() != $adapter->id())
        continue;
      
      // if we find any data, we set this to true.
      $found_any_data = FALSE;
      
      foreach($entity_ids as $eid) {
#        drupal_set_message("a3: " . microtime());
        // here we should check if we really know the entity by asking the TS for it.
        // this would speed everything up largely, I think.
        // 
        // for now we assume we know the entity.
        // $entity = $this->loadEntity($eid);
#        drupal_set_message("a4: " . microtime());
        // if there is nothing, continue.
        // if(empty($entity))
        //  continue;

        if($field_id == "bundle" && !empty($bundleid_in))
          $out[$eid]["bundle"] = array($bundleid_in);

#        drupal_set_message("I am asked for fids: " . serialize($field_ids));
  
        if($field_id == "eid") {
          $out[$eid][$field_id] = array($eid);
          continue;
        }
        
        if($field_id == "name") {
          // tempo hack
          $out[$eid][$field_id] = array($eid);
          continue;
        }
        
        // Bundle is a special case.
        // If we are asked for a bundle, we first look in the pb cache for the bundle
        // because it could have been set by 
        // measures like navigate or something - so the entity is always displayed in 
        // a correct manor.
        // If this is not set we just select the first bundle that might be appropriate.
        // We select this with the first field that is there. @TODO:
        // There might be a better solution to this.
        // e.g. knowing what bundle was used for this id etc...
        // however this would need more tables with mappings that will be slow in case
        // of a lot of data...
        if($field_id == "bundle") {
          
          if(!empty($bundleid_in)) {
            $out[$eid]['bundle'] = array($bundleid_in);
            continue;
          }
          
          // get all the bundles for the eid from us
          $bundles = $this->getBundleIdsForEntityId($eid);
          
          if(!empty($bundles)) {
            // if there is only one, we take that one.
            #foreach($bundles as $bundle) {
            $out[$eid]['bundle'] = array_values($bundles);
            #  break;
            #}
            continue;
          } else {
            // if there is none return NULL
            $out[$eid]['bundle'] = NULL;              
            continue;
          }
        }

        // every other field is an array, we guess
        // this might be wrong... cardinality?          
        if(!isset($out[$eid][$field_id]))
          $out[$eid][$field_id] = array();

        // set the bundle
        // @TODO: This is a hack and might break for multi-federalistic stores
        $pbarray = $pb->getPbEntriesForFid($field_id);
          
        // if there is no data about this path - how did we get here in the first place?
        // fields not in sync with pb?
        if(empty($pbarray["id"]))
          continue;

        $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($pbarray["id"]);

        // if there is no path we can skip that
        if(empty($path))
          continue;

        $clearPathArray = $this->getClearPathArray($path, $pb);
 
 #         drupal_set_message("I have: " . serialize($pbarray), "error");
        if(!empty($path)) {
          // if this is question for a subgroup - handle it otherwise
          if($pbarray['parent'] > 0 && $path->isGroup()) {
#              drupal_set_message("I am asking for: " . serialize($this->getClearGroupArray($path, $pb)) . "with eid: " . serialize($eid));
            // this was the old query without evil numeric ids.
            // now we have to change all this.
            #$out[$eid][$field_id] = array_merge($out[$eid][$field_id], $this->pathToReturnValue($this->getClearGroupArray($path, $pb), NULL, $eid, 0, $main_property, $path->getDisamb()));
            // nowadays we do it otherwise
            
            #$tmp = $this->pathToReturnValue($this->getClearGroupArray($path, $pb), NULL, $eid, 0, $main_property, $path->getDisamb());
            // @TODO: ueberarbeiten
#            drupal_set_message("danger zone!");
            $tmp = $this->pathToReturnValue($path, $pb, $eid, 0, $main_property);            

            foreach($tmp as $key => $item) {
              $tmp[$key]["target_id"] = $this->getDrupalId($item["target_id"]);
            }
            
            $out[$eid][$field_id] = array_merge($out[$eid][$field_id], $tmp);
            
            
#            $out[$eid][$field_id] = array_merge($out[$eid][$field_id], $this->getDrupalId($this->pathToReturnValue($this->getClearGroupArray($path, $pb), NULL, $eid, 0, $main_property, $path->getDisamb())));
#              drupal_set_message("I've got: " . serialize($out[$eid][$field_id]));
          } else {
              // it is a field?
#              $out[$eid][$field_id] = array_merge($out[$eid][$field_id], $this->pathToReturnValue($clearPathArray, $path->getDatatypeProperty(), $eid));
#              drupal_set_message("pa: " . serialize($path->getPathArray()) . " cpa: " . serialize($clearPathArray));

            // get the parentid
            $parid = $pbarray["parent"];
            
            // get the parent (the group the path belongs to) to get the common group path
            $par = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($parid);

            // if there is no parent it is a ungrouped path... who asks for this?
            if(empty($par)) {
              drupal_set_message("Path " . $path->getName() . " with id " . $path->id() . " has no parent.", "error");
              continue;
            }
#              drupal_set_message("pa: " . serialize($path->getPathArray()) . " cpa: " . serialize($clearPathArray) . " cga: " . serialize($this->getClearGroupArray($par, $pb)));
            
            $tmp = $this->pathToReturnValue($path, $pb, $eid, count($path->getPathArray()) - count($clearPathArray), $main_property);            
            
            if ($main_property == 'target_id') {
$oldtmp = $tmp;
              foreach($tmp as $key => $item) {
                $tmp[$key]["original_target_id"] = $item["target_id"];
                $tmp[$key]["target_id"] = $this->getDrupalId(isset($item['wisskiDisamb']) ? $item["wisskiDisamb"] : $item["target_id"]);
              }
#dpm([$oldtmp,$tmp], 'target_id_rewrite');                
            }
          


            $out[$eid][$field_id] = array_merge($out[$eid][$field_id], $tmp);        
#            $out[$eid][$field_id] = array_merge($out[$eid][$field_id], $this->pathToReturnValue($path->getPathArray(), $path->getDatatypeProperty(), $eid, count($path->getPathArray()) - count($clearPathArray), $main_property, $path->getDisamb()));#(count($this->getClearGroupArray($par, $pb))-1), $main_property, $path->getDisamb()));
#            drupal_set_message("smthg: " . serialize($out[$eid][$field_id]));
          
            // 
          }
#          drupal_set_message("bla: " . serialize($out[$eid][$field_id]));
          
#            drupal_set_message($path->getDisamb());
#              drupal_set_message("I loaded: " . serialize($out));
        }
        
        if(empty($out[$eid][$field_id]))
          unset($out[$eid]);
      }
    }

#    drupal_set_message("out: for " . serialize(func_get_args()) . " is: " . serialize($out));

#dpm($out);
    return $out;


  }
  
  
  public function getQueryObject(EntityTypeInterface $entity_type,$condition,array $namespaces) {
    //do NOT copy this to parent, this is namespace dependent  
    return new Query($entity_type,$condition,$namespaces,$this);
  }
  
  public function deleteOldFieldValue($entity_id, $fieldid, $value, $pb, $count = 0) {
    // get the pb-entry for the field
    // this is a hack and will break if there are several for one field
    $pbarray = $pb->getPbEntriesForFid($fieldid);
    
    // get path/field-related config
    // and do some checks to ensure that we are acting on a
    // well configured field
    $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($pbarray['id']);
    if(empty($path)) {
      return;
    }

    // this is an important distinction till now!
    // TODO: maybe we can combine reference delete and value delete
    $is_reference = $path->isGroup() ? : ($pbarray['fieldtype'] == 'entity_reference');

    if ($is_reference) {
      // delete a reference
      // this differs from normal field values as there is no literal
      // and the entity has to be matched to the uri
      
      $subject_uri = $this->getUriForDrupalId($entity_id);
      if (empty($subject_uri)) {
        // the adapter doesn't know of this entity. some other adapter needs
        // to handle it and we can skip it.
        return;
      }
      $subject_uris = array($subject_uri);
      
      // value is the Drupal id of the referenced entity
      $object_uri = $this->getUriForDrupalId($value);
      if (empty($object_uri)) {
        // the adapter doesn't know of this entity. some other adapter needs
        // to handle it and we can skip it.
        return;
      }

      $path_array = $path->getPathArray();

      if (count($path_array) < 3) {
        // This should never occur as it would mean that someone is deleting a
        // reference on a path with no triples!
        drupal_set_message("Bad path: trying to delete a ref with a too short path.", 'error');
        return;
      }
      elseif (count($path_array) == 3) {
        // we have the spacial case where subject and object uri are directly
        // linked in a triple <subj> <prop> <obj> / <obj> <inverse> <subj>.
        // So we know which triples to delete and can skip costly search for 
        // the right triple.

        // nothing to do!
      }
      else {
        // in all other cases we need to readjust the subject uri to cut the 
        // right triples.

        $pathcnt = 0;
        $parent = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($pbarray['parent']);
        if (empty($parent)) {
          // lonesome path!?
        }
        else {
          // we cannot use clearPathArray() here as path is a group and 
          // the function would chop off too much
          $parent_path_array = $parent->getPathArray();
          $pathcnt = count($parent_path_array) - 1;
        }

        // we have to set disamb manually to the last instance
        // otherwise generateTriplesForPath() won't produce right triples
        $disamb = (count($path_array) - 1) / 2;
        // the var that interests us is the one before disamb
        $subject_var = "x" . ($disamb - 1);

        // build up a select query that get us 
        $select  = "SELECT DISTINCT ?$subject_var WHERE {";
        $select .= $this->generateTriplesForPath($pb, $path, "", $subject_uri, $object_uri, $disamb, $pathcnt, FALSE, NULL, 'entity_reference');
        $select .= "}";
        
        $result = $this->directQuery($select);

        if ($result->numRows() == 0) {
          // there is no relation any more. has been deleted before!?
          return;
        }
        
        // reset subjects
        $subject_uris = array();
        foreach ($result as $row) {
          $subject_uris[] = $row->{$subject_var}->getUri();
        }

      }
      
      $prop = $path_array[count($path_array) - 2];
      $inverse = $this->getInverseProperty($prop);

      $delete  = "DELETE DATA {\n";
      foreach ($subject_uris as $subject_uri) {
        $delete .= "  <$subject_uri> <$prop> <$object_uri> .\n";
        $delete .= "  <$object_uri> <$inverse> <$subject_uri> .\n";
      }
      $delete .= ' }';
#dpm(array($subject_uris, $delete), 'del');

      $result = $this->directUpdate($delete);    

    } else {
    
      $subject_uri = $this->getUriForDrupalId($entity_id);
      $clearPathArray = $this->getClearPathArray($path, $pb);
      
      $diff = count($path->getPathArray()) - count($clearPathArray);
          
      // delete normal field value
      $sparql = "SELECT DISTINCT * WHERE { ";

      // I am unsure if this is correct
      // probably it needs to be relative - but I am unsure
      //$triples = $this->generateTriplesForPath($pb, $path, $value, $eid, NULL, 0, $diff, FALSE);
      // make a query without the value - this is necessary
      // because we have to think of the weight.
      $triples = $this->generateTriplesForPath($pb, $path, '', $subject_uri, NULL, 0, $diff, FALSE);
      
      $sparql .= $triples;
      
      $sparql .= " }";
      
      $result = $this->directQuery($sparql);
#      dpm(array($sparql, $result), "find data");

      $outarray = array();
#      dpm($result, "result");
#      dpm($count, "count");

      $loc_count = 0;
      $break = FALSE;
      
      $position = NULL;
      $the_thing = NULL;
      
      foreach($result as $key => $thing) {
#        dpm(serialize($key === $count), "key " . $key . " count " . $count);
#        dpm(serialize($thing->out == $value), "key1 " . $thing->out . " count1 " . $value);
        // Easy case - it is at the "normal position"
        if($key === $count && $thing->out == $value) {
          $the_thing = $thing;
          $position = $count;
          break;
        }
        
        // not so easy case - it is somewhere else 
        if($thing->out == $value) {
          $position = $key;
          $the_thing = $thing;
          if($key >= $count)
            break;
        }
      }
      
      if(is_null($position)) {
        drupal_set_message("I could not find the old value '" . $value . "' and thus could not delete it. ","error");
        return;
      }
      
      // for fuseki we need graph
      $delete  = "DELETE DATA {\n";

      // the datatype-property is not directly connected to the group-part
      if(count($clearPathArray) >= 3) {
        $prop = $clearPathArray[1];
        $inverse = $this->getInverseProperty($prop);

        $name = "x" . ($diff +2);
        $object_uri = $the_thing->{$name}->getUri();

        $delete .= "  <$subject_uri> <$prop> <$object_uri> .\n";
        $delete .= "  <$object_uri> <$inverse> <$subject_uri> .\n";

      } else {
        $primitive = $path->getDatatypeProperty();
        
        if(!empty($primitive)) {
          if(empty($value)) {
          
            $delete .= "  <$subject_uri> <$prop> '$value' .\n";
                        
          } else {
            drupal_set_message("No Primitive was set for path " . $path->id() . " and the path length was too short!", "error");
            return; 
          }
        }
      }
      
      $delete .= ' }';
      
#      dpm($delete, "delete query");
      $result = $this->directUpdate($delete);

      /*
      foreach($result as $key => $thing) {
        
        if($count !== $loc_count) {
          $loc_count++;
          continue;
        }
        
        if($count === $loc_count) {
          $break = TRUE;
        }
      
        $outarray[$key] = array();
        
        for($i=$diff; $i<count($clearPathArray)+$diff; $i++) {
          $name = "x" . $i;
          if($i % 2 == 0) {
            $outarray[$key][$i] = $thing->{$name}->dumpValue("text");
          } else {
            $outarray[$key][$i] = $clearPathArray[($i-$diff)];
          }
        }

        ksort($outarray[$key]);
        if(!empty($primitive)) {
          if(empty($value)) {
            $outarray[$key]["primitive"] = $primitive;
            $outarray[$key]["out"] = $thing->out->getValue();
          } else {
            $outarray[$key]["primitive"] = $primitive;
            $outarray[$key]["out"] = $value;
          }
        }

      // add graph handling
        $sparqldelete = "DELETE DATA { " ;
   
        $arr = $outarray[$key];
        $i=0;
        
        // is there a disamb?
        if($path->getDisamb() > 0 && isset($arr[($path->getDisamb()-2)*2])) {
          $i = ($path->getDisamb()-2)*2;
          
          $sparqldelete .= "<" . $arr[$i++] . "> ";
          $sparqldelete .= "<" . $arr[$i++] . "> ";
          $sparqldelete .= "<" . $arr[$i++] . "> ";
        } else { // no disamb - cut in the end!
          // -3 because out and primitive
          $maxi = count($arr)-3;
          
          $sparqldelete .= "<" . $arr[$maxi] . "> ";
          $sparqldelete .= "<" . $arr['primitive'] . "> ";
          $sparqldelete .= "'" . $this->escapeSparqlLiteral($arr['out']) . "' ";
        }
        
        $sparqldelete .= " } ";
        dpm($sparqldelete, "delete query");
        $result = $this->directUpdate($sparqldelete);
        
        $loc_count++;
      }*/
    }

  }

  /**
   * Create a new entity
   * @param $entity an entity object
   * @param $entity_id the eid to be set for the entity, if NULL and $entity dowes not have an eid, we will try to create one
   * @return the Entity ID
   */
  public function createEntity($entity,$entity_id=NULL) {
    #$uri = $this->getUri($this->getDefaultDataGraphUri());
#    dpm(func_get_args(),__FUNCTION__);
#    \Drupal::logger('WissKIsaveProcess')->debug(__METHOD__ . " with values: " . serialize(func_get_args()));
        
    $bundleid = $entity->bundle();

    $pbs = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::loadMultiple();
    
    $out = array();
    
    //might be empty, but we can use it later
    $eid = $entity->id() ? : $entity_id;
    $uri = NULL;
    //dpm($eid,$entity_id);
    //if there is an eid we try to get the entity URI form cache
    //if there is none $uri will be FALSE
    if (!empty($eid)) $uri = $this->getUriForDrupalId($eid);
    else $uri = $this->getUri($this->getDefaultDataGraphUri());
    // get the adapterid that was loaded
    // haha, this is the engine-id...
    //$adapterid = $this->getConfiguration()['id'];

#    \Drupal::logger('WissKIsaveProcess')->debug(__METHOD__ . " with values: " . serialize(func_get_args()) . " gets id: " . $eid . " and uri: " . $uri);
        
    foreach($pbs as $pb) {
      //drupal_set_message("a2: " . microtime());
      // if we have no adapter for this pb it may go home.
      if(empty($pb->getAdapterId()))
        continue;
        
      $adapter = \Drupal\wisski_salz\Entity\Adapter::load($pb->getAdapterId());

      // if we have not adapter, we may go home, too
      if(empty($adapter))
        continue;
      
      // if he didn't ask for us...    
      if($this->adapterId() !== $adapter->id())
        continue;
      
      //dpm('I can create',$adapter->id());
      $groups = $pb->getGroupsForBundle($bundleid);

      // for now simply take the first one.    
      if ($groups = current($groups)) {
        
        $triples = $this->generateTriplesForPath($pb, $groups, '', $uri, NULL, 0, ((count($groups->getPathArray())-1)/2), TRUE, NULL, 'group_creation');
        //dpm(array('eid'=>$eid,'uri'=>$uri,'group'=>$groups->getPathArray()[0],'result'=>$triples),'generateTriplesForPath');
        
        $sparql = "INSERT DATA { GRAPH <" . $this->getDefaultDataGraphUri() . "> { " . $triples . " } } ";
#        \Drupal::logger('WissKIsaveProcess')->debug('sparql writing in create: ' . htmlentities($sparql));
        
        $result = $this->directUpdate($sparql);
    
        if (empty($uri)) {
        
          // first adapter to write will create a uri for an unknown entity
          $uri = explode(" ", $triples, 2);
      
          $uri = substr($uri[0], 1, -1);  
        }
      }     
    }
#    dpm($groups, "bundle");
        
#    $entity->set('id',$uri);

    if (empty($eid)) {
      $eid = $this->getDrupalId($uri);
    }
#    dpm($eid,$adapter->id().' made ID');
    $entity->set('eid',$eid);
#    "INSERT INTO { GRAPH <" . $this->getDefaultDataGraphUri() . "> { " 
    return $eid;
  }

  public function getUri($prefix) {
    return uniqid($prefix);
  }
  
  /**
   * Generate the triple part for the statements (excluding any Select/Insert or
   * whatever). This should be used for any pattern generation. Everything else
   * is evil.
   *
   * @param $pb	a pathbuilder instance
   * @param $path the path as a path object of which the triple parts should be 
   *              generated. May also be a group.
   * @param $primitiveValue The primitive data value that should be stored or
   *              asked for in the query.
   * @param $subject_in If there should be any subject on a certain position 
   *              this could be encoded by using $subject_in and the 
   *              $startingposition parameter.
   * @param $object_in If there should be any object. The position of the object
   *              may be encoded in the disambposition.
   * @param $disambposition The position in the path where the object or the
   *              general disambiguation of this path lies. 0 means no disamb,
   *              1 means disamb on the first concept, 2 on the second concept
   *              and so on.
   * @param $startingposition From where on the path should be generated in means
   *              of concepts from the beginning (warning: not like disamb! 0 means start here!).
   * @param $write Is this a write or a read-request?
   * @param $op How should it be compared to other data
   * @param $mode defaults to 'field' - but may be 'group' or 'entity_reference' in special cases
   * @param $relative should it be relative to the other groups?
   * @param $variable_prefixes string|array if string, this will be used to prefix all variables
   *              if array, the variable of index i will be prefixed with the value of key i.
   *              The variable ?out will be prefixed with the key "out".
   */
  public function generateTriplesForPath($pb, $path, $primitiveValue = "", $subject_in = NULL, $object_in = NULL, $disambposition = 0, $startingposition = 0, $write = FALSE, $op = '=', $mode = 'field', $relative = TRUE, $variable_prefixes = array()) {
#     \Drupal::logger('WissKIsaveProcess')->debug('generate: ' . serialize(func_get_args()));
    // the query construction parameter
    $query = "";
    // if we disamb on ourself, return.
    if($disambposition == 0 && !empty($object_in)) return "";

    
    // we get the sub-section for this path
    $clearPathArray = array();
    if($relative) {
      // in case of group creations we just need the " bla1 a type " triple
      if($mode == 'group_creation') 
        $clearPathArray = $pb->getRelativePath($path, FALSE);
      else // in any other case we need the relative path
        $clearPathArray = $pb->getRelativePath($path);
    } else { // except some special cases.
      $clearPathArray = $path->getPathArray();
    }
     
    // the RelativePath will be keyed like the normal path array
    // meaning that it will not necessarily start at 0
        
 #   \Drupal::logger('WissKIsaveProcess')->debug('countdiff ' . $countdiff . ' cpa ' . serialize($clearPathArray) . ' generate ' . serialize(func_get_args()));
    
    // old uri pointer
    $olduri = NULL;
    // old key pointer
    $oldkey = NULL;
    $oldvar = NULL;
    
    // if the old uri is empty we assume there is no uri and we have to
    // generate one in write mode. In ask mode we make variable-questions
    
    // get the default datagraphuri    
    $datagraphuri = $this->getDefaultDataGraphUri();
    
    $first = TRUE;
    
    // iterate through the given path array
    foreach($clearPathArray as $key => $value) {
      
      if($first) {
        if($key > ($startingposition *2)) {
          drupal_set_message("Starting Position is set to a wrong value.", "error");
          \Drupal::logger('WissKIsaveProcess')->debug('ERROR: ' . serialize($clearPathArray) . ' generate ' . serialize(func_get_args()));
          \Drupal::logger('WissKIsaveProcess')->debug('ERROR: ' . serialize(debug_backtrace()[1]['function']) . ' and ' . serialize(debug_backtrace()[2]['function']));
        }
      }
      
      $first = false;
            
      // skip anything that is smaller than $startingposition.
      if($key < ($startingposition*2)) 
        continue;
      
      // basic initialisation
      $uri = NULL;
            
      // basic initialisation for all queries
      $localvar = "?" . (is_array($variable_prefixes) ? (isset($variable_prefixes[$key]) ? $variable_prefixes[$key] : "") : $variable_prefixes) . "x" . $key;
      if (empty($oldvar)) {
        // this is a hack but i don't get the if's below
        // and when there should be set $oldvar
        // TODO: fix this!
        $oldvar = "?" . (is_array($variable_prefixes) ? (isset($variable_prefixes[$key]) ? $variable_prefixes[$key] : "") : $variable_prefixes) . "x" . $key;
      }
      
      if($key % 2 == 0) {
        // if it is the first element and we have a subject_in
        // then we have to replace the first element with subject_in
        // and typically we don't do a type triple. So we skip the rest.
        if($key == ($startingposition*2) && !empty($subject_in)) {
          $olduri = $subject_in;
          
          $query .= "<$olduri> a <$value> . ";

          continue;
        }
        
        // if the key is the disambpos
        // and we have an object
        if($key == (($disambposition-1)*2) && !empty($object_in)) {
          $uri = $object_in;
        } else {
                  
          // if it is not the disamb-case we add type-triples        
          if($write) {
            // generate a new uri
            $uri = $this->getUri($datagraphuri);
            $query .= "<$uri> a <$value> . ";
          }
          else
            $query .= "$localvar a <$value> . ";
        }
        
        // magic function
        if($key > 0 && !empty($prop)) {
        
          if($write) {
              
            $query .= "<$olduri> <$prop> <$uri> . ";
          } else {
                      
            $inverse = $this->getInverseProperty($prop);
            // if there is not an inverse, don't do any unions
            if(empty($inverse)) {
              if(!empty($olduri))
                $query .= "<$olduri> ";
              else
                $query .= "$oldvar ";
          
              $query .= "<$prop> ";
                    
              if(!empty($uri))
                $query .= "<$uri> . ";
              else
                $query .= "$localvar . ";
            } else { // if there is an inverse, make a union
              $query .= "{ { ";
              // Forward query part
              if(!empty($olduri))
                $query .= "<$olduri> ";
              else
                $query .= "$oldvar ";
          
              $query .= "<$prop> ";
                    
              if(!empty($uri))
                $query .= "<$uri> . ";
              else
                $query .= "$localvar . ";
              
              $query .= " } UNION { ";

              // backward query part
          
              if(!empty($uri))
                $query .= "<$uri> ";
              else
                $query .= "$localvar "; 
          
              $query .= "<$inverse> ";

              if(!empty($olduri))
                $query .= "<$olduri> . ";
              else
                $query .= "$oldvar . ";
                            
              $query .= " } } . "; 
            }
          }
        }
         
        // if this is the disamb, we may break.
        if($key == (($disambposition-1)*2) && !empty($object_in)) {
          break;
        }
          
        $olduri = $uri;
        $oldkey = $localkey;
        $oldvar = $localvar;
      } else {
        $prop = $value;
      }
    }


#\Drupal::logger('testung')->debug($path->getID() . ":".htmlentities($query));
    // get the primitive for this path if any    
    $primitive = $path->getDatatypeProperty();

    if( (empty($primitive) || $primitive == "empty") && !$path->isGroup()) {
      drupal_set_message("There is no primitive Datatype for Path " . $path->id(), "error");
    }
    
    if(!empty($primitive) && !($primitive == "empty") && empty($object_in) && !$path->isGroup()) {
      if(!empty($olduri)) {
        $query .= "<$olduri> ";
      } else {
        // if we initialized with a nearly empty path oldvar is empty.
        // in this case we assume x at the startingposition
        if(empty($oldvar))
          $query .= "?" . (is_array($variable_prefixes) ? (isset($variable_prefixes[$localkey]) ? $variable_prefixes[$localkey] : "") : $variable_prefixes) . "x" . $startingposition;
        else
          $query .= "$oldvar ";
      }
      
      $query .= "<$primitive> ";

      $outvar = "?" . (is_array($variable_prefixes) ? (isset($variable_prefixes["out"]) ? $variable_prefixes["out"] : "") : $variable_prefixes) . "out";
      
      if(!empty($primitiveValue)) {
        
        // we have to escape it otherwise the sparql query may break
        $primitiveValue = $this->escapeSparqlLiteral($primitiveValue);

        if ($write) {
          $query .= "\"$primitiveValue\"";
        } else {

/* putting there the literal directly is not a good idea as 
  there may be problems with matching lang and datatype
        if($op == '=') 
          $query .= "'" . $primitiveValue . "' . ";
        else {
  Instead we compare it to the STR()-value 

*/

          $regex = null;
          if($op == '<>')
            $op = '!=';
          if($op == 'STARTS_WITH') {
            $regex = true;
            $primitiveValue = '^' . $this->escapeSparqlRegex($primitiveValue);
          }
          
          if($op == 'ENDS_WITH') {
            $regex = true;
            $primitiveValue = $this->escapeSparqlRegex($primitiveValue) . '$';
          }
          
          if($op == 'CONTAINS') {
            $regex = true;
            $primitiveValue = $this->escapeSparqlRegex($primitiveValue);
          }
          
        
          if($regex || $op == 'BETWEEN' || $op == 'IN' || $op == 'NOT IN') {
            $query .= " $outvar . FILTER ( regex ( $outvar, \"" . $primitiveValue . '", "i" ) ) . ';
          } else {
            // we have to use STR() otherwise we may get into trouble with
            // datatype and lang comparisons
            $query .= " $outvar . FILTER ( STR($outvar) " . $op . ' "' . $primitiveValue . '" ) . ';
          }
        }
      } else {
        $query .= " $outvar . ";
      }
    }
#    \Drupal::logger('WissKIsaveProcess')->debug('erg generate: ' . htmlentities($query));
    return $query;
  }
  
  public function addNewFieldValue($entity_id, $fieldid, $value, $pb) {
#    drupal_set_message("I get: " . $entity_id.  " with fid " . $fieldid . " and value " . $value . ' for pb ' . $pb->id());
#    drupal_set_message(serialize($this->getUri("smthg")));
    $datagraphuri = $this->getDefaultDataGraphUri();

    $pbarray = $pb->getPbEntriesForFid($fieldid);
    
    $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($pbarray['id']);

    if(empty($path))
      return;
      
    if($path->getDisamb()) {
      $sparql = "SELECT * WHERE { GRAPH ?g { ";

      // starting position one before disamb because disamb counts the number of concepts, startin position however starts from zero
      $sparql .= $this->generateTriplesForPath($pb, $path, $value, NULL, NULL, NULL, $path->getDisamb()-1, FALSE);
      $sparql .= " } }";
            
      $disambresult = $this->directQuery($sparql);
  
      if(!empty($disambresult))
        $disambresult = current($disambresult);      
    }
    
    // rename to uri
    $subject_uri = $this->getUriForDrupalId($entity_id);

    $sparql = "INSERT DATA { GRAPH <" . $datagraphuri . "> { ";

    // 1.) A -> B -> C -> D -> E (l: 9) and 2.) C -> D -> E (l: 5) is the relative, then
    // 1 - 2 is 4 / 2 is 2 - which already is the starting point.
    $start = ((count($path->getPathArray()) - (count($pb->getRelativePath($path))))/2);

    if($path->isGroup()) {
      $sparql .= $this->generateTriplesForPath($pb, $path, "", $subject_uri, $this->getUriForDrupalId($value), (count($path->getPathArray())+1)/2, $start, TRUE, '', 'entity_reference');
    } else {
      if(empty($path->getDisamb()))
        $sparql .= $this->generateTriplesForPath($pb, $path, $value, $subject_uri, NULL, NULL, $start, TRUE);
      else {
 #       drupal_set_message("disamb: " . serialize($disambresult) . " miau " . $path->getDisamb());
        if(empty($disambresult) || empty($disambresult->{"x" . ($path->getDisamb()-1)*2}) )
          $sparql .= $this->generateTriplesForPath($pb, $path, $value, $subject_uri, NULL, NULL, $start, TRUE);
        else
          $sparql .= $this->generateTriplesForPath($pb, $path, $value, $subject_uri, $disambresult->{"x" . ($path->getDisamb()-1)*2}->dumpValue("text"), (($path->getDisamb()-1)*2), $start, TRUE);
      }
    }
    $sparql .= " } } ";
#     \Drupal::logger('WissKIsaveProcess')->debug('sparql writing in add: ' . htmlentities($sparql));
       
    $result = $this->directUpdate($sparql);
    
    
#    drupal_set_message("I add field $field from entity $entity_id that currently has the value $value");
  }
  
  public function writeFieldValues($entity_id, array $field_values, $pathbuilder, $bundle_id=NULL,$old_values=array(),$force_new=FALSE) {
#    drupal_set_message(serialize("Hallo welt!") . serialize($entity_id) . " " . serialize($field_values) . ' ' . serialize($bundle));
#    dpm(func_get_args(), __METHOD__);    
#    \Drupal::logger('WissKIsaveProcess')->debug(__METHOD__ . " with values: " . serialize(func_get_args()));
    // tricky thing here is that the entity_ids that are coming in typically
    // are somewhere from a store. In case of rdf it is easy - they are uris.
    // In case of csv or something it is more tricky. So I don't wan't to 
    // simply go to the store and tell it "give me the bundle of this".
    // The field ids come in handy here - fields are typically attached
    // to a bundle anyway. so I just get the bundle from there. I think it is
    // rather stupid that this function does not load the field values per 
    // bundle - it is implicitely anyway like that.
    // 
    // so I ignore everything and just target the field_ids that are mapped to
    // paths in the pathbuilder.
    
    $out = array();
    
#    return $out;                    
      
    // here we should check if we really know the entity by asking the TS for it.
    // this would speed everything up largely, I think.
    $init_entity = $this->loadEntity($entity_id);
    
    // if there is nothing, continue.
    if (empty($init_entity)) {
#      dpm('empty entity',__FUNCTION__);
      if ($force_new) {
        $entity = new WisskiEntity(array('eid' => $entity_id,'bundle' => $bundle_id),'wisski_individual',$bundle_id);
        $this->createEntity($entity,$entity_id);
      } else return;
    }
    
    if(empty($entity) && !empty($init_entity))
      $entity = $init_entity;
    
    if (!isset($old_values) && !empty($init_entity)) {
      // it would be better to gather this information from the form and not from the ts
      // there might have been somebody saving in between...
      // @TODO !!!
      $old_values = $this->loadFieldValues(array($entity_id), array_keys($field_values), $bundle_id);
      
      if(!empty($old_values))
        $old_values = $old_values[$entity_id];
    }

    //drupal_set_message("the old values were: " . serialize($old_values));
#    dpm($old_values,'old values');
#    dpm($field_values,'new values');

    
    foreach($field_values as $field_id => $field_items) {
      #drupal_set_message("key: " . serialize($field_id) . " fieldvalue is: " . serialize($field_items)); 
      $path = $pathbuilder->getPbEntriesForFid($field_id);
      
      $old_value = isset($old_values[$field_id]) ? $old_values[$field_id] : array();
      #dpm($old_value, "old value");
      
      if(empty($path)) {
        //drupal_set_message("I leave here: $field_id");
        continue;
      }
        
#      drupal_set_message("I am still here: $field_id");

      $mainprop = $field_items['main_property'];
      
      unset($field_items['main_property']);
      
      // check if we have to delete some values
      // we go thru the old values and search for an equal value in the new 
      // values array
      // as we do this we also keep track of values that haven't changed so that we
      // do not have to write them again.

      $write_values = $field_items;
      
      // TODO $val is not set: iterate over fieldvalue!
      // if there are old values
      if (!empty($old_value)) {
        // we might want to delete some
        $delete_values = $old_value;
        
        // if it is not an array there are no values, so we can savely stop
        if (!is_array($old_value)) {
          $delete_values = array($mainprop => $old_value);
          
          // $old_value contains the value directly
          foreach ($field_items as $key => $new_item) {
            if (empty($new_item)) { // empty field item due to cardinality, see else branch
              unset($write_values[$key]);
              continue;
            }
            // if the old value is somwhere in the new item
            if ($old_value == $new_item[$mainprop]) {
              // we unset the write value at this key because this doesn't have to be written
              unset($write_values[$key]);
              // we reset the things we need to delete
              $delete_values = array();
            }
          }
        } else {
          // $old_value is an array of arrays resembling field list items and
          // containing field property => value pairs
          
          foreach ($old_value as $old_key => $old_item) {
            if (!is_array($old_item) || empty($old_item)) {
              // this may be the case if 
              // - it contains key "main_property"... (not an array)
              // - it is an empty field that is there because of the 
              // field's cardinality (empty)
              unset($delete_values[$old_key]);
              continue;
            }
            
            $maincont = FALSE;
            
            foreach ($write_values as $key => $new_item) {
              if (empty($new_item)) {
                unset($write_values[$key]);
                continue; // empty field item due to cardinality
              }
              if ($old_item[$mainprop] == $new_item[$mainprop]) {
                // if we find the item in the old values we don't have to write it.
                unset($write_values[$key]);
                // and we don't have to delete it
                unset($delete_values[$old_key]);
                
                $maincont = TRUE;
                break;
              }
            }
            
            // if we found something we continue in the old values
            if($maincont)
              continue;
          }
        }

        #dpm($delete_values, "we have to delete");
        if (!empty($delete_values)) {
          foreach ($delete_values as $key => $val) {
            #dpm($val, "delete");
            $this->deleteOldFieldValue($entity_id, $field_id, $val[$mainprop], $pathbuilder, $key);
          }
        }
      }
      
      #dpm($write_values, "we have to write");
      // now we write all the new values
      foreach ($write_values as $new_item) {
        $this->addNewFieldValue($entity_id, $field_id, $new_item[$mainprop], $pathbuilder); 
      }

      

      
/* --- WRITE LOOP: This whole write loop works incorrectly when it comes to the same thing in the fields */
      /*
      $remain_values = array();
      // TODO $val is not set: iterate over fieldvalue!
      if (!empty($old_value)) {
        $delete_values = array();
        if (!is_array($old_value)) {
          // $old_value contains the value directly
          $delete_values[] = $old_value;
          foreach ($field_items as $new_item) {
            if (empty($new_item)) continue; // empty field item due to cardinality, see else branch
            if ($old_value == $new_item[$mainprop]) {
              $remain_values[$new_item[$mainprop]] = $new_item[$mainprop];
              $delete_values = array();
              break;
            }
          }
        } else {
          // $old_value is an array of arrays resembling field list items and
          // containing field property => value pairs
          foreach ($old_value as $old_key => $old_item) {
            if (!is_array($old_item) || empty($old_item)) {
              // this may be the case if 
              // - it contains key "main_property"... (not an array)
              // - it is an empty field that is there because of the 
              // field's cardinality (empty)
              continue;
            }
            $delete = TRUE;
            
            foreach ($field_items as $key => $new_item) {
              if (empty($new_item)) continue; // empty field item due to cardinality
              if ($old_item[$mainprop] == $new_item[$mainprop]) {
                $remain_values[$new_item[$mainprop]] = $new_item[$mainprop];
                $delete = FALSE;
                break;
              }
            }
            if ($delete) {
              $delete_values[] = $old_item[$mainprop];
            }
          }
          if ($delete_values) {
            foreach ($delete_values as $val) {
              $this->deleteOldFieldValue($entity_id, $field_id, $val, $pathbuilder);
            }
          }
        }
      }
      
      // now we write all the new values
      foreach ($field_items as $new_item) {
        if (!isset($remain_values[$new_item[$mainprop]])) {
          $this->addNewFieldValue($entity_id, $field_id, $new_item[$mainprop], $pathbuilder); 
        }
      }
      */

/* --- WRITE LOOP: this whole loop does not seem to work correctly on changes
      foreach($field_items as $field_id2 => $val) {

        drupal_set_message(serialize($val[$mainprop]) . " new");
        #drupal_set_message(serialize($old_values[$field_id]) . " old");

        // check if there are any old values. If not, delete nothing.
        if(!empty($old_values)) {
     
#        dpm(array('popel','old_values' => $old_values, 'val' => $val, $mainprop, $field_id, $field_id2), 'popelchen', 'error');
/* this section doesn't handle multiple values correctly
          // if they are the same - skip
          // I don't know why this should be working, but I leave it here...
          if($val[$mainprop] == $old_values[$field_id]) 
            continue;
          
          // the real value comparison is this here:
          if($val[$mainprop] == $old_values[$field_id][$field_id2][$mainprop])
            continue;
          
          // if oldvalues are an array and the value is in there - skip
          if(is_array($old_values[$field_id]) && !empty($old_values[$field_id][$field_id2]) && in_array($val[$mainprop], $old_values[$field_id][$field_id2]))
            continue;
        
          // there comes a replacement:
*/      
/*
        // for each value we check all the old values if we find a corresponding one
        // this also handles the case where there is only a change in sequence
        
          if (is_array($old_values[$field_id])) {
            $skip = FALSE;
            foreach ($old_values[$field_id] as $field_id2 => $values) {
              if ($field_id2 == 'main_property') continue;
              if ($values[$mainprop] = $val[$mainprop]) {
#  dpm(array($val[$mainprop], $values[$mainprop], $val, $old_values), "skip", 'error');
                $skip = TRUE;
                break;
              }
            }
            if ($skip) {
              continue;
            }
          }

          
          // now write to the database
          
          drupal_set_message($entity_id . "I really write!" . serialize($val[$mainprop])  . " and " . serialize($old_values[$field_id]) );
          #return;
        
          // first delete the old values
          // Martin: do we always delete values???
          if(is_array($old_values[$field_id])) {
            $this->deleteOldFieldValue($entity_id, $field_id, $old_values[$field_id][$field_id2][$mainprop], $pathbuilder);
          } else {
            $this->deleteOldFieldValue($entity_id, $field_id, $old_values[$field_id], $pathbuilder);
          }

        }

#        dpm(array('$entity_id'=>$entity_id,'$field_id'=>$field_id,'value' => $val[$mainprop],'$pathbuilder'=>$pathbuilder),'try writing');
        // add the new ones
        $this->addNewFieldValue($entity_id, $field_id, $val[$mainprop], $pathbuilder); 
        
        #drupal_set_message("I would write " . $val[$mainprop] . " to the db and delete " . serialize($old_values[$field_id]) . " for it.");
        
      }          
    }
 --- END WRITE LOOP */
   
    }

#    drupal_set_message("out: " . serialize($out));

    return $out;

  }
  
  // -------------------------------- Ontologie thingies ----------------------

  public function addOntologies($iri = NULL) { 
 #   dpm($iri, "1");
    if (empty($iri)) {
      //load all ontologies
      $query = "SELECT ?ont WHERE {?ont a owl:Ontology}";
      $result = $this->directQuery($query);
     # if ($ok) {
        foreach ($result as $obj) {
          $this->addOntologies(strval($obj->ont));
        }
     /* } else {
        foreach ($result as $err) {
          drupal_set_message(t('Error getting imports of ontology %iri: @e', array('%ont' => $o, '@e' => $err)), 'error');
        }
      }
      */
      return;
    }

    // check if the Ontology is already there
    $result = $this->directQuery("ASK {<$iri> a owl:Ontology}");
#    dpm($result, "res");
   /* if (!$ok) { // we've got something weired.
      drupal_set_message("Store is not requestable.", 'error');
      return;
   */
    
  /*
     // this case will not work, result will never be empty because it always contains the 
     if(!empty($result)){ // if it is not false it is already there   
      drupal_set_message("$iri is already loaded.", 'error');
      return;
    }
*/

    // if we get here we may load the ontology
    $query = "LOAD <$iri> INTO GRAPH <$iri>";
#    dpm($query, "query");
    $result = $this->directUpdate($query);

    // everything worked?  
/*    if (!$ok) {
      foreach ($result as $err) {
        drupal_set_message(t('An error occured while loading the Ontology: ' . serialize($err)),'error');
      }
    } else { // or it worked
 */     
      drupal_set_message("Successfully loaded $iri into the Triplestore.");
   # }
  
    // look for imported ontologies
    $query = "SELECT DISTINCT ?ont FROM <$iri> WHERE { ?s a owl:Ontology . ?s owl:imports ?ont . }";
  #  list($ok, $results) = $this->directQuery($query);
    $results = $this->directQuery($query);
 
    // if there was nothing something is weired again.
  /*  if (!$ok) {
      foreach ($results as $err) {
        drupal_set_message(t('Error getting imports of ontology %iri: @e', array('%ont' => $o, '@e' => $err)), 'error');
      }
    } else { // if there are some we have to load them
      foreach ($results as $to_load) {
        $this->addOntologies(strval($to_load->ont));
      }
    }*/
    foreach ($results as $to_load) {
      $this->addOntologies(strval($to_load->ont));
    }
                
    // load the ontology info in internal parameters    
    // $this->loadOntologyInfo();
    
    // add namespaces to table
  
    $file = file_get_contents($iri);
    $format = \EasyRdf_Format::guessFormat($file, $iri); 
    if(empty($format)) {
      drupal_set_message("Could not initialize namespaces.", 'error');
    } else {
      if(stripos($format->getName(), 'xml') !== FALSE) {
        preg_match('/RDF[^>]*>/i', $file, $nse);
        
        preg_match_all('/xmlns:[^=]*="[^"]*"/i', $nse[0], $nsarray);
        
        $ns = array();
        $toStore = array();
        foreach($nsarray[0] as $newns) {
          preg_match('/xmlns:[^=]*=/', $newns, $front);
          $front = substr($front[0], 6, strlen($front[0])-7);
          preg_match('/"[^"]*"/', $newns, $end);
          $end = substr($end[0], 1, strlen($end[0])-2);
          $ns[$front] = $end;
        }
                
	preg_match_all('/xmlns="[^"]*"/i', $nse[0], $toStore);
	
	foreach($toStore[0] as $itemGot) {
          $i=0;
	  $key = 'base';
	
	  preg_match('/"[^"]*"/', $itemGot, $item);
	  $item	= substr($item[0], 1, strlen($item[0])-2);
	  
	  if(!array_key_exists($key, $ns)) {
	    if(substr($item, strlen($item)-1, 1) != '#')
	      $ns[$key] = $item . '#';
	    else
	      $ns[$key] = $item;
          } else {
	      $newkey = $key . $i;
	      while(array_key_exists($newkey, $ns)) {
		$i++;
		$newkey = $key . $i;
	      }
	      if(substr($item, strlen($item)-1, 1) != '#')
	 	$ns[$newkey] = $item . '#';
	      else
		$ns[$newkey] = $item;
          }
	}
	
	foreach($ns as $key => $value) {
  	  $this->putNamespace($key, $value);
  	} 
  	
  	global $base_url;
  	// @TODO: check if it is already in the ontology.
  	$this->putNamespace("local", $base_url . '/');
  	$this->putNamespace("data", $base_url . '/inst/');
      }
      
      
    }    
    
    // return the result
    return $result;   

 }  

  public function getOntologies($graph = NULL) {
    // get ontology and version uri
    if(!empty($graph)) {
      $query = "SELECT DISTINCT ?ont ?iri ?ver FROM $graph WHERE { ?ont a owl:Ontology . OPTIONAL { ?ont owl:ontologyIRI ?iri. ?ont owl:versionIRI ?ver . } }";
    } else
      $query = "SELECT DISTINCT ?ont (COALESCE(?niri, 'none') as ?iri) (COALESCE(?nver, 'none') as ?ver) (COALESCE(?ngraph, 'default') as ?graph) WHERE { ?ont a owl:Ontology . OPTIONAL { GRAPH ?ngraph { ?ont a owl:Ontology } } . OPTIONAL { ?ont owl:ontologyIRI ?niri. ?ont owl:versionIRI ?nver . } }";
     
    $results = $this->directQuery($query); 
  /*
  if (!$ok) {
    foreach ($results as $err) {
      drupal_set_message(t('Error getting imports of ontology %iri: @e', array('%ont' => $o, '@e' => $err)), 'error');
    }
  }
 */                              
    return $results;
}
     
  public function deleteOntology($graph, $type = "graph") {
 
    // get ontology and version uri
    if($type == "graph") {
      $query = "WITH <$graph> DELETE { ?s ?p ?o } WHERE { ?s ?p ?o }";
    } else
      $query = "DELETE { ?s ?p ?o } WHERE { ?s ?p ?o . FILTER ( STRSTARTS(STR(?s), '$graph')) }";
                         
    $results = $this->directUpdate($query);
                             
   /* if (!$ok) {
    // some useful error message :P~
      drupal_set_message('some error encountered:' . serialize($results), 'error');
    }
   */                                              
    return $results;
  }
  
  private function putNamespace($short_name,$long_name) {
    $result = db_select('wisski_salz_sparql11_ontology_namespaces','ns')
              ->fields('ns')
              ->condition('short_name',$short_name,'=')
              ->execute()
              ->fetchAssoc();
    if (empty($result)) {
      db_insert('wisski_salz_sparql11_ontology_namespaces')
              ->fields(array('short_name' => $short_name,'long_name' => $long_name))
              ->execute();
    } else {
     //      drupal_set_message('Namespace '.$short_name.' already exists in DB');
    }
  }
                                                                                                           
  public function getNamespaces() {
    $ns = array();
    $db_spaces = db_select('wisski_salz_sparql11_ontology_namespaces','ns')
                  ->fields('ns')
                  ->execute()
                  ->fetchAllAssoc('short_name');
    foreach ($db_spaces as $space) {
      $ns[$space->short_name] = $space->long_name;
    }
    return $ns;
  }

  private $super_properties = array();
  private $clean_super_properties = array();

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

#    $cids = array(
#      'properties',
#      'sub_properties',
#      'super_properties',
#      'inverse_properties',
#      'sub_classes',
#      'super_classes',
#      'domains',
#      'reverse_domains',
#      'ranges',
#      'reverse_ranges',
#    );
#    $results = array();
#    foreach ($cids as $cid) {
#      if ($cache = \Drupal::cache()->get('wisski_reasoner_'.$cid)) {
#        $results[$cid] = $cache->data;
#      }
#    }
#    dpm($results,'Results');

    $in_cache = $this->isCacheSet();

    $form = parent::buildConfigurationForm($form, $form_state);

    $button_label = $this->t('Start Reasoning');
    $emphasized = $this->t('This will take several minutes.');

    $form['reasoner'] = array(
      '#type' => 'details',
      '#title' => $this->t('Compute Type and Property Hierarchy and Domains and Ranges'),
      '#prefix' => '<div id="wisski-reasoner-block">',
      '#suffix' => '</div>',
      'description' => array(
        '#type' => 'fieldset',
        '#title' => $this->t('Read carefully'),
        'description_start' => array('#markup' => $this->t("Clicking the %label button will initiate a set of complex SPARQL queries computing",array('%label'=>$button_label))),
        'description_list' => array(
          '#theme' => 'item_list',
          '#items' => array(
            $this->t("the class hierarchy"),
            $this->t("the property hierarchy"),
            $this->t("the domains of all properties"),
            $this->t("the ranges of all properties"),
          ),
        ),
        'description_end' => array(
          '#markup' => $this->t(
            "in the specified triple store. <strong>%placeholder</strong> The pathbuilders relying on this adapter will become much faster by doing this.",
            array('%placeholder'=>$emphasized)
          ),
        ),
      ),
      'start_button' => array(
        '#type' => 'button',
        '#value' => $button_label,
        '#ajax' => array(
          'wrapper' => 'wisski-reasoner-block',
          'callback' => array($this,'startReasoning'),
        ),
        '#prefix' => '<div id="wisski-reasoner-start-button">',
        '#suffix' => '</div>',
      ),
    );
    if ($in_cache) {
      $form['reasoner']['start_button']['#disabled'] = !$form_state->getValue('flush_button');
      $form['reasoner']['flush_button'] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Re-Compute results'),
        '#default_value' => FALSE,
        '#description' => $this->t('You already have reasoning results in your cache'),
        '#ajax' => array(
          'wrapper' => 'wisski-reasoner-start-button',
          'callback' => array($this,'checkboxAjax'),
        ),
      );
      $classes_n_properties = ((array) $this->getClasses()) + ((array) $this->getProperties());
      if ($this->getClasses() === FALSE || $this->getProperties() === FALSE) {
        drupal_set_message($this->t('Bad class and property cache.'));
      }
      $form['reasoner']['tester'] = array(
        '#type' => 'details',
        '#title' => $this->t('Check reasoning results'),
        'selected_prop' => array(
          '#type' => 'select',
          '#options' => $classes_n_properties,
          '#empty_value' => 'empty',
          '#empty_option' => $this->t('select a class or property'),
          '#ajax' => array(
            'wrapper' => 'wisski-reasoner-check',
            'callback' => array($this,'checkTheReasoner'),
          ),
        ),
        'check_results' => array(
          '#type' => 'textarea',
          '#prefix' => '<div id="wisski-reasoner-check">',
          '#suffix' => '</div>',      
        ),
      );
    }
    return $form;
  }

  public function checkboxAjax(array $form, FormStateInterface $form_state) {
    return $form['reasoner']['start_button'];
  }
  
  public function checkTheReasoner(array $form, FormStateInterface $form_state) {
  
    $candidate = $form_state->getValue($form_state->getTriggeringElement()['#name']);
    if ($this->isAProperty($candidate)) {
      $stored = $this->getClassesFromStore($candidate);
      $cached = $this->getClassesFromCache($candidate);
    } else {
      $stored = $this->getPropertiesFromStore($candidate);
      $cached = $this->getPropertiesFromCache($candidate);
    }
    $more_stored = array_diff($stored,$cached);
    $more_cached = array_diff($cached,$stored);
    if (empty($more_stored) && empty($more_cached)) {
      $result = $this->t('Same results for cache and direct query');
      $full_results = $stored;
    } else {
      $stored_text = empty($more_stored) ? '' : $this->t('more in store:')."\n\t".implode("\n\t",$more_stored);
      $cached_text = empty($more_cached) ? '' : $this->t('more in cache:')."\n\t".implode("\n\t",$more_cached);
      $result = $this->t('Different results:')."\n".$stored_text."\n".$cached_text;
      $full_results = array_unique(array_merge($stored,$cached));
    }
    $form['reasoner']['tester']['check_results']['#value'] = $candidate."\n".$result."\n\n".$this->t('Full list of results')."\n\t".implode("\n\t",$full_results);
    return $form['reasoner']['tester']['check_results'];
  }

  public function startReasoning(array $form,FormStateInterface $form_state) {
    
    $this->doTheReasoning();
    $form_state->setRedirect('<current>');
    return $form['reasoner'];
  }
  
  public function doTheReasoning() {
  
    $properties = array();
    $super_properties = array();
    $sub_properties = array();
    
    //prepare database connection and reasoner tables
    //if there's something wrong stop working
    if ($this->prepareTables() === FALSE) return;
    
    //find properties
    $result = $this->directQuery("SELECT ?property WHERE {?property a owl:ObjectProperty.}");
    $insert = $this->prepareInsert('properties');
    foreach ($result as $row) {
      $prop = $row->property->getUri();
      $properties[$prop] = $prop;
      $insert->values(array('property' => $prop));
    }
    $insert->execute();
    //$cid = 'wisski_reasoner_properties';
    //\Drupal::cache()->set($cid,$properties);
    
    //find one step property hierarchy, i.e. properties that are direct children or direct parents to each other
    // no sub-generations are gathered
    $result = $this->directQuery(
      "SELECT ?property ?super WHERE {"
        ."?property a owl:ObjectProperty. "
        ."?property rdfs:subPropertyOf ?super. "
        ."FILTER NOT EXISTS {?mid_property rdfs:subPropertyOf+ ?super. ?property rdfs:subPropertyOf ?mid_property.}"
      ."}");
    foreach ($result as $row) {
      $prop = $row->property->getUri();
      $super = $row->super->getUri();
      $super_properties[$prop][$super] = $super;
      $sub_properties[$super][$prop] = $prop;
      if (!isset($properties[$prop])) $properties[$prop] = $prop;
    }

    //$cid = 'wisski_reasoner_sub_properties';
    //\Drupal::cache()->set($cid,$sub_properties);
    //$cid = 'wisski_reasoner_super_properties';
    //\Drupal::cache()->set($cid,$super_properties);

    //now lets find inverses
    $insert = $this->prepareInsert('inverses');
    $inverses = array();
    $results = $this->directQuery("SELECT ?prop ?inverse WHERE {{?prop owl:inverseOf ?inverse.} UNION {?inverse owl:inverseOf ?prop.}}");
    foreach ($results as $row) {
      $prop = $row->prop->getUri();
      $inv = $row->inverse->getUri();
      $inverses[$prop] = $inv;
      $insert->values(array('property' => $prop,'inverse'=>$inv));
    }
    $insert->execute();
    //$cid = 'wisski_reasoner_inverse_properties';
    //\Drupal::cache()->set($cid,$inverses);
    
    //now the same things for classes
    //find all classes
    $insert = $this->prepareInsert('classes');
    $classes = array();
    $results = $this->directQuery("SELECT ?class WHERE {?class a owl:Class.}");
    foreach ($results as $row) {
      $class = $row->class->getUri();
      $classes[$class] = $class;
      $insert->values(array('class'=>$class));
    }
    $insert->execute();
    //uksort($classes,'strnatcasecmp');
    //\Drupal::cache()->set('wisski_reasoner_classes',$classes);
    
    //find full class hierarchy
    $super_classes = array();
    $sub_classes = array();
    $results = $this->directQuery("SELECT ?class ?super WHERE {"
      ."?class rdfs:subClassOf+ ?super. "
      ."FILTER (!isBlank(?class)) "
      ."FILTER (!isBlank(?super)) "
      ."?super a owl:Class. "
    ."}");
    foreach ($results as $row) {
      $sub = $row->class->getUri();
      $super = $row->super->getUri();
      $super_classes[$sub][$super] = $super;
      $sub_classes[$super][$sub] = $sub;
    }
    
    //\Drupal::cache()->set('wisski_reasoner_sub_classes',$sub_classes);
    //\Drupal::cache()->set('wisski_reasoner_super_classes',$super_classes);
    
    //explicit top level domains
    $domains = array();
    
    $results = $this->directQuery(
      "SELECT ?property ?domain WHERE {"
        ." ?property rdfs:domain ?domain."
        // we only need top level domains, so no proper subClass of the domain shall be taken into account
        ." FILTER NOT EXISTS { ?domain rdfs:subClassOf+ ?super_domain. ?property rdfs:domain ?super_domain.}"
      ." }");
    foreach ($results as $row) {
      $domains[$row->property->getUri()][$row->domain->getUri()] = $row->domain->getUri();
    }
    
    //clear up, avoid DatatypeProperties
    $domains = array_intersect_key($domains,$properties);
    
    //explicit top level ranges
    $ranges = array();
    
    $results = $this->directQuery(
      "SELECT ?property ?range WHERE {"
        ." ?property rdfs:range ?range."
        // we only need top level ranges, so no proper subClass of the range shall be taken into account
        ." FILTER NOT EXISTS { ?range rdfs:subClassOf+ ?super_range. ?property rdfs:range ?super_range.}"
      ." }");
    foreach ($results as $row) {
      $ranges[$row->property->getUri()][$row->range->getUri()] = $row->range->getUri();
    }
    
    //clear up, avoid DatatypeProperties
    $ranges = array_intersect_key($ranges,$properties);    
    
    //take all properties with no super property
    $top_properties = array_diff_key($properties,$super_properties);

    $valid_definitions = TRUE;
    //check if they all have domains and ranges set
    $dom_check = array_diff_key($top_properties,$domains);
    if (!empty($dom_check)) {
      drupal_set_message('No domains for top-level properties: '.implode(', ',$dom_check),'error');
      $valid_definitions = FALSE;
    }
    $rng_check = array_diff_key($top_properties,$ranges);
    if (!empty($rng_check)) {
      drupal_set_message('No ranges for top-level properties: '.implode(', ',$rng_check),'error');
      $valid_definitions = FALSE;
    }
    
    //set of properties where the domains and ranges are not fully set
    $not_set = array_diff_key($properties,$top_properties);
    
    //while there are unchecked properties cycle throgh them, gather domain/range defs from all super properties and inverses
    //and include them into own definition
    $runs = 0;
    while ($valid_definitions && !empty($not_set)) {
      
      $runs++;
      //take one of the properties
      $prop = array_shift($not_set);
      //check if all super_properties have their domains/ranges set
      $supers = $super_properties[$prop];
      $invalid_supers = array_intersect($supers,$not_set);
      if (empty($invalid_supers)) {
        //take all the definitions of super properties and add them here
        $new_domains = isset($domains[$prop]) ? $domains[$prop] : array();
        $new_ranges = isset($ranges[$prop]) ? $ranges[$prop] : array();
        foreach ($supers as $super_prop) {
          $new_domains += $domains[$super_prop];
          $new_ranges += $ranges[$super_prop];
        }
        $new_domains = array_unique($new_domains);
        $new_ranges = array_unique($new_ranges);
        
        $remove_domains = array();
        foreach ($new_domains as $domain_1) {
          foreach ($new_domains as $domain_2) {
            if ($domain_1 !== $domain_2) {
              if (isset($super_classes[$domain_1]) && in_array($domain_2,$super_classes[$domain_1])) {
                $remove_domains[] = $domain_2;
              }
            }
          }
        }
        $new_domains = array_diff($new_domains,$remove_domains);
        
        $domains[$prop] = array_combine($new_domains,$new_domains);
        
        $remove_ranges = array();
        foreach ($new_ranges as $range_1) {
          foreach ($new_ranges as $range_2) {
            if ($range_1 !== $range_2) {
              if (isset($super_classes[$range_1]) && in_array($range_2,$super_classes[$range_1])) {
                $remove_ranges[] = $range_2;
              }
            }
          }
        }
        $new_ranges = array_diff($new_ranges,$remove_ranges);
        
        $ranges[$prop] = array_combine($new_ranges,$new_ranges);
        
      } else {
        //append this property to the end of the list to be checked again later-on
        array_push($not_set,$prop);
      }
    }
    drupal_set_message('Definition checkup runs: '.$runs);
    //remember sub classes of domains are domains, too.
    //if a property has exactly one domain set, we can add all subClasses of that domain
    //if there are multiple domains we can only add those being subClasses of ALL of the domains
    foreach ($properties as $property) {
      if (isset($domains[$property])) {
        $add_up = array();
        foreach ($domains[$property] as $domain) {
          if (isset($sub_classes[$domain]) && $sub_domains = $sub_classes[$domain]) {
            $add_up = empty($add_up) ? $sub_domains : array_intersect_key($add_up,$sub_domains);
          }
        }
        $domains[$property] = array_merge($domains[$property],$add_up);
      }
      if (isset($ranges[$property])) {
        $add_up = array();
        foreach ($ranges[$property] as $range) {
          if (isset($sub_classes[$range]) && $sub_ranges = $sub_classes[$range]) {
            $add_up = empty($add_up) ? $sub_ranges : array_intersect_key($add_up,$sub_ranges);
          }
        }
        $ranges[$property] = array_merge($ranges[$property],$add_up);
      }
    }
    
    $insert = $this->prepareInsert('domains');
    foreach ($domains as $prop => $classes) {
      foreach ($classes as $class) $insert->values(array('property'=>$prop,'class'=>$class));
    }
    $insert->execute();
    $insert = $this->prepareInsert('ranges');
    foreach ($ranges as $prop => $classes) {
      foreach ($classes as $class) $insert->values(array('property'=>$prop,'class'=>$class));
    }
    $insert->execute();
    
//    //for the pathbuilders to work correctly, we also need inverted search
//    $reverse_domains = array();
//    foreach ($domains as $prop => $classes) {
//      foreach ($classes as $class) $reverse_domains[$class][$prop] = $prop;
//    }
//    $reverse_ranges = array();
//    foreach ($ranges as $prop => $classes) {
//      foreach ($classes as $class) $reverse_ranges[$class][$prop] = $prop;
//    }
//    $cid = 'wisski_reasoner_domains';
//    \Drupal::cache()->set($cid,$domains);
//    $cid = 'wisski_reasoner_ranges';
//    \Drupal::cache()->set($cid,$ranges);
//    $cid = 'wisski_reasoner_reverse_domains';
//    \Drupal::cache()->set($cid,$reverse_domains);
//    $cid = 'wisski_reasoner_reverse_ranges';
//    \Drupal::cache()->set($cid,$reverse_ranges);
  }
  
  public function getInverseProperty($property_uri) {

  /* cache version
    $inverses = array();
    $cid = 'wisski_reasoner_inverse_properties';
    if ($cache = \Drupal::cache()->get($cid)) {
      $inverses = $cache->data;
      if (isset($properties[$property_uri])) return $inverses[$property_uri];
    }
    */
    
    //DB version
    $inverse = $this->retrieve('inverses','inverse','property',$property_uri);
    if (!empty($inverse)) return current($inverse);
    $results = $this->directQuery("SELECT ?inverse WHERE {{<$property_uri> owl:inverseOf ?inverse.} UNION {?inverse owl:inverseOf <$property_uri>.}}");
    $inverse = '';
    foreach ($results as $row) {
      $inverse = $row->inverse->getUri();
    }
    $inverses[$property_uri] = $inverse;
//    \Drupal::cache()->set($cid,$inverses);
    return $inverse;
  }
  
  protected function isPrepared() {
    try {
      return !empty(\Drupal::service('database')->select($this->adapterId().'_classes','c')->fields('c')->range(0,1)->execute());
    } catch (\Exception $e) {
      return FALSE;
    }
  }
  
  protected function prepareTables() {
    
    try {
      $database = \Drupal::service('database');
      $schema = $database->schema();
      $adapter_id = $this->adapterId();
      foreach (self::getReasonerTableSchema() as $type => $table_schema) {
        $table_name = $adapter_id.'_'.$type;
        if ($schema->tableExists($table_name)) {
          $database->truncate($table_name);
        } else {
          $schema->createTable($table_name,$table_schema);
        }
      }
      return TRUE;
    } catch (\Exception $ex) {}
    return FALSE;
  }
  
  private function prepareInsert($type) {
    
    $fieldS = array();
    foreach (self::getReasonerTableSchema()[$type]['fields'] as $field_name => $field) {
      if ($field['type'] !== 'serial') $fields[] = $field_name;
    }
    $table_name = $this->adapterId().'_'.$type;
    return \Drupal::service('database')->insert($table_name)->fields($fields);
  }
  
  public function retrieve($type,$return_field=NULL,$condition_field=NULL,$condition_value=NULL) {
    
    $table_name = $this->adapterId().'_'.$type;
    $query = \Drupal::service('database')
              ->select($table_name,'t')
              ->fields('t');
    if (!is_null($condition_field) && !is_null($condition_value)) {
      $query = $query->condition($condition_field,$condition_value);
    }
    try {
      $result = $query->execute();
      if (!is_null($return_field)) {
        $result = array_keys($result->fetchAllAssoc($return_field));
        usort($result,'strnatcasecmp');
        return array_combine($result,$result);
      }
      return $result->fetchAll();
    } catch (\Exception $e) {
      return FALSE;
    }
  }
  
  /**
   * implements hook_schema()
   */
  public static function getReasonerTableSchema() {

    $schema['classes'] = array(
      'description' => 'hold information about triple store classes',
      'fields' => array(
        'num' => array(
          'description' => 'the Serial Number for this class',
          'type' => 'serial',
          'size' => 'normal',
          'not null' => TRUE,
        ),
        'class' => array(
          'description' => 'the uri of the class',
          'type' => 'varchar',
          'length' => '2048',
          'not null' => TRUE,
        ),
      ),
      'primary key' => array('num'),
    );
    
    $schema['properties'] = array(
      'description' => 'hold information about triple store properties',
      'fields' => array(
        'num' => array(
          'description' => 'the Serial Number for this property',
          'type' => 'serial',
          'size' => 'normal',
          'not null' => TRUE,
        ),
        'property' => array(
          'description' => 'the uri of the property',
          'type' => 'varchar',
          'length' => '2048',
          'not null' => TRUE,
        ),
      ),
      'primary key' => array('num'),
    );    
    
    $schema['domains'] = array(
      'description' => 'hold information about domains of triple store properties',
      'fields' => array(
        'num' => array(
          'description' => 'the Serial Number for this pairing',
          'type' => 'serial',
          'size' => 'normal',
          'not null' => TRUE,
        ),
        'property' => array(
          'description' => 'the uri of the property',
          'type' => 'varchar',
          'length' => '2048',
          'not null' => TRUE,
        ),
        'class' => array(
          'description' => 'the uri of the domain class',
          'type' => 'varchar',
          'length' => '2048',
          'not null' => TRUE,
        ),
      ),
      'primary key' => array('num'),
    );
    
    $schema['ranges'] = array(
      'description' => 'hold information about ranges of triple store properties',
      'fields' => array(
        'num' => array(
          'description' => 'the Serial Number for this pairing',
          'type' => 'serial',
          'size' => 'normal',
          'not null' => TRUE,
        ),
        'property' => array(
          'description' => 'the uri of the property',
          'type' => 'varchar',
          'length' => '2048',
          'not null' => TRUE,
        ),
        'class' => array(
          'description' => 'the uri of the range class',
          'type' => 'varchar',
          'length' => '2048',
          'not null' => TRUE,
        ),
      ),
      'primary key' => array('num'),
    );
    
    $schema['inverses'] = array(
      'description' => 'hold information about ranges of triple store properties',
      'fields' => array(
        'num' => array(
          'description' => 'the Serial Number for this pairing',
          'type' => 'serial',
          'size' => 'normal',
          'not null' => TRUE,
        ),
        'property' => array(
          'description' => 'the uri of the property',
          'type' => 'varchar',
          'length' => '2048',
          'not null' => TRUE,
        ),
        'inverse' => array(
          'description' => 'the uri of the inverse property',
          'type' => 'varchar',
          'length' => '2048',
          'not null' => TRUE,
        ),
      ),
      'primary key' => array('num'),
    );

    return $schema;
  }
  
}
