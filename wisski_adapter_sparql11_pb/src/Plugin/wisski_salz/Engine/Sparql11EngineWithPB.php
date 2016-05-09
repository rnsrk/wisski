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

use Drupal\wisski_adapter_sparql11_pb\Query\Query;

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

  /**
   * @{inheritdoc}
   */
  public function getPathAlternatives($history = [], $future = []) {
    
    if (empty($history) && empty($future)) {
      
      return $this->getClasses();

    } elseif (!empty($history)) {
      
      $last = array_pop($history);
      $next = empty($future) ? NULL : $future[0];

      if ($this->isaProperty($last)) {
        return $this->nextClasses($last, $next);
      } else {
        return $this->nextProperties($last, $next);
      }

    } else {
      return [];
    }

    
  }
  
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
        ."?property rdfs:domain ?d_superclass. "
        ."<$step> rdfs:subClassOf* ?d_superclass. }"
      ;

    $result = $this->directQuery($query);

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
  
    $query = "SELECT DISTINCT ?class WHERE { ?class a owl:Class . }";  
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


  public function nextProperties($class,$class_after = NULL) {
    //old name, but no hierarchy anymore
    $query = 
      "SELECT DISTINCT ?property "
      ."WHERE { "
        ."?property a owl:ObjectProperty. "
        ."?property rdfs:domain ?d_superclass. "
        ."<$class> rdfs:subClassOf* ?d_superclass. "
      ;
    if (isset($class_after)) {
      $query .= 
        "?property rdfs:range ?r_superclass. "
        ."<$class_after> rdfs:subClassOf* ?r_superclass. "
      ;
    }
    $query .= "}";
    $result = $this->directQuery($query);
    
    if (count($result) == 0) return array();
    
    $output = array();
    foreach ($result as $obj) {
      $prop = $obj->property->getUri();
      $output[$prop] = $prop;
    }
    uksort($output,'strnatcasecmp');
    return $output;
  }



  public function nextClasses($property,$property_after = NULL) {
  
    $query = 
      "SELECT DISTINCT ?class "
      ."WHERE { "
        ."<$property> rdfs:subPropertyOf* ?r_super_prop. "
        ."?r_super_prop rdfs:range ?r_super_class. "
        ."FILTER NOT EXISTS { "
          ."?r_sub_prop rdfs:subPropertyOf+ ?r_super_prop. "
          ."<$property> rdfs:subPropertyOf* ?r_sub_prop. "
          ."?r_sub_prop rdfs:range ?r_any_class. "
        ."} "
        ."?class rdfs:subClassOf* ?r_super_class. ";
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

  // copy from yaml-adapter - likes camels.
  
  private $entity_info;

  /**
   *
   *
   *
   */
  public function getBundleIdsForEntityId($entityid) {
    $pb = $this->getPbForThis();
    
    $uri = str_replace('\\', '/', $entityid);

#    drupal_set_message("parse url: " . serialize(parse_url($uri)));

    $url = parse_url($uri);

    if(!empty($url["scheme"]))
      $query = "SELECT ?class WHERE { <" . $uri . "> a ?class }";
    else
      $query = "SELECT ?class WHERE { " . $entityid . " a ?class }";
    
    $result = $this->directQuery($query);
    
#    drupal_set_message(serialize($result));
    
   $out = array();
    foreach($result as $thing) {
    
      // ask for a bundle from the pb that has this class thing in it
      $groups = $pb->getAllGroups();
      
      foreach($groups as $group) {
        // this does not work for subgroups
        #$path_array = $group->getPathArray();
                
        $path_array = $this->getClearPathArray($group, $pb);
        
        if(empty($group) || empty($path_array))
          continue;
        
        if($path_array[ count($path_array)-1] == $thing->class->dumpValue("text")) {
          $pbpaths = $pb->getPbPaths();
          
#          drupal_set_message(serialize($pbpaths[$group->id()]));
          
          if(!empty($pbpaths[$group->id()]))
            $out[$pbpaths[$group->id()]['bundle']] = $pbpaths[$group->id()]['bundle'];
        }
      }
    }

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
        continue;
      
      // -1 because we don't want to cut our own concept
      $parentcnt = count($parentpath->getPathArray())-1;
      
      for($i=0; $i<$parentcnt; $i++) {
        unset($patharraytoget[$i]);
      }
      
      $patharraytoget = array_values($patharraytoget);
      
      // we have to cut away everything that is in $cleargrouparray
      // so we take the whole length and subtract that as a starting point
      // and go up from there
      for($i=(count($patharraytoget)-count($clearGroupArray)+1);$i<count($patharraytoget);$i++)
        unset($patharraytoget[$i]);
      
      $patharraytoget = array_values($patharraytoget);      
      
    }
    return $patharraytoget;    
  }
  
  /**
   * Gets the common part of a group or path
   * that is clean from subgroup-fragments
   */
  public function getClearPathArray($path, $pb) {
    // We have to modify the path-array in case of subgroups.
    // Usually if we have a subgroup path x0 y0 x1 we have to skip x0 y0 in
    // the paths of the group.
     
    $patharraytoget = $path->getPathArray();
    $allpbpaths = $pb->getPbPaths();
    $pbarray = $allpbpaths[$path->id()];
    
    // first we detect if the field is in a subgroup
    // is it in a group?
    if($pbarray['parent'] > 0) {

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
        
        if($pbparentarray['parent'] > 0) {
          // only do something if it is a path in a subgroup, not in a main group  
          
          // in that case we have to remove the subgroup-part, however minus one, as it is the       
          $pathcnt = count($parentpath->getPathArray()) - count($this->getClearPathArray($parentpath, $pb));
        
        
          for($i=0; $i< $pathcnt; $i++) {
            unset($patharraytoget[$i]);
          }
        }
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
  public function loadIndividualsForBundle($bundleid, $pathbuilder) {
    
    // there should be someone asking for more than one...
    $groups = $pathbuilder->getGroupsForBundle($bundleid);
     
    // no group defined in this pb - return   
    if(empty($groups)) {
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
      
    // build the query
    $query = "SELECT ?x0 WHERE {";
       
    foreach($grouppath as $key => $pathpart) {
      if($key % 2 == 0)
        $query .= " ?x" . $key . " a <". $pathpart . "> . ";
      else
        $query .= " ?x" . ($key-1) . " <" . $pathpart . "> ?x" . ($key+1) . " . "; 
    }
    
    $query .= "}";

    // ask for the query
    $result = $this->directQuery($query);

    $outarr = array();

    // for now simply take the first element
    // later on we need names here!
    foreach($result as $thing) {
      $uri = $thing->x0->dumpValue("text");
      $uri = str_replace('/','\\',$uri);
      
      // store the bundleid to the bundle-cache as it might be important
      // for subsequent queries.
      
      $pathbuilder->setBundleIdForEntityId($uri, $bundleid);
      
      $outarr[$uri] = array('eid' => $uri, 'bundle' => $bundleid, 'name' => $uri);
    }

    return $outarr;
  }

  public function load($id) {
        
    $out = array();
    $uri = str_replace('\\', '/', $id);

#    drupal_set_message("parse url: " . serialize(parse_url($uri)));

    $url = parse_url($uri);

    if(!empty($url["scheme"]))
      $query = "SELECT * WHERE { { <$uri> ?p ?o } UNION { ?s ?p <$uri> } }"; 
    else
      $query = 'SELECT * WHERE { ?s ?p "' . $id . '" }';  
    
    $result = $this->directQuery($query);
        
    foreach($result as $thing) {
#      $uri = $thing->s->dumpValue("text");
#      $uri = str_replace('/','\\',$uri);
      $out = array('eid' => $id, 'bundle' => 'e21_person', 'name' => 'frizt');

#      $out[$uri] = array('eid' => $uri, 'bundle' => 'e21_person', 'name' => 'frizt');#$thing->s->dumpValue("text"), 'bundle' => 'e21_person', 'name' => 'frizt');
#      $i++;
    }
    
#    drupal_set_message("load single");
    
    return $out;
  }
  
  public function loadMultiple($ids = NULL) {
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
      $uri = str_replace('/','\\',$uri);
      
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
    
    $ent = $this->load($entity_id);
    return !empty($ent);
  }
 
  public function groupToReturnValue($patharray, $primitive = NULL, $eid = NULL) {
    $sparql = "SELECT DISTINCT * WHERE { ";
    foreach($patharray as $key => $step) {
      if($key % 2 == 0) 
        $sparql .= "?x$key a <$step> . ";
      else
        $sparql .= '?x' . ($key-1) . " <$step> ?x" . ($key+1) . " . ";    
    }
    
    if(!empty($primitive)) {
      $sparql .= "?x$key <$primitive> ?out . ";
    }
    
    if(!empty($eid)) {
      $eid = str_replace("\\", "/", $eid);
      $url = parse_url($eid);
      
      if(!empty($url["scheme"]))
        $sparql .= " FILTER (?x0 = <$eid> ) . ";
      else
        $sparql .= " FILTER (?x0 = \"$eid\" ) . ";
    }
    
    $sparql .= " } ";

    
#    drupal_set_message("spq: " . serialize($sparql));
#    drupal_set_message(serialize($this));
    
    $result = $this->directQuery($sparql);
    
#    drupal_set_message(serialize($result));
    
    $out = array();
    foreach($result as $thing) {
 #     drupal_set_message("we got something!");
      $name = 'x' . (count($patharray)-1);
      if(!empty($primitive))
        $out[] = $thing->out->getValue();
      else
        $out[] = $thing->$name->dumpValue("text");
    }
    
    return $out;
  
  }

  public function pathToReturnValue($patharray, $primitive = NULL, $eid = NULL) {
    $sparql = "SELECT DISTINCT * WHERE { ";
    foreach($patharray as $key => $step) {
      if($key % 2 == 0) 
        $sparql .= "?x$key a <$step> . ";
      else
        $sparql .= '?x' . ($key-1) . " <$step> ?x" . ($key+1) . " . ";    
    }
    
    if(!empty($primitive)) {
      $sparql .= "?x$key <$primitive> ?out . ";
    }
    
    if(!empty($eid)) {
      $eid = str_replace("\\", "/", $eid);
      $url = parse_url($eid);
      
      if(!empty($url["scheme"]))
        $sparql .= " FILTER (?x0 = <$eid> ) . ";
      else
        $sparql .= " FILTER (?x0 = \"$eid\" ) . ";
    }
    
    $sparql .= " } ";

    
#    drupal_set_message("spq: " . serialize($sparql));
#    drupal_set_message(serialize($this));
    
    $result = $this->directQuery($sparql);
    
#    drupal_set_message(serialize($result));
    
    $out = array();
    foreach($result as $thing) {
 #     drupal_set_message("we got something!");
      $name = 'x' . (count($patharray)-1);
      if(!empty($primitive))
        $out[] = $thing->out->getValue();
      else
        $out[] = $thing->$name->dumpValue("text");
    }
    
    return $out;
    
  }
  
  /**
   * Gets the PB object for a given adapter id
   * @return a pb object
   */
  public function getPbForThis() {
    $pbs = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::loadMultiple();
    
    foreach($pbs as $pb) {
      // if there is no adapter set for this pb  
      if(empty($pb->getAdapterId()))
        continue;
        
      $adapter = \Drupal\wisski_salz\Entity\Adapter::load($pb->getAdapterId());

      // if we have not adapter, we may go home, too
      if(empty($adapter))
        continue;
      
      // if he didn't ask for us...    
      if($this->getConfiguration()['id'] != $adapter->getEngine()->getConfiguration()['id'])
        continue;
        
      // if we get here we have our pathbuilder
      return $pb;
      
    }
  }

  /**
   * @inheritdoc
   */
  public function loadFieldValues(array $entity_ids = NULL, array $field_ids = NULL, $language = LanguageInterface::LANGCODE_DEFAULT) {

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
              
      foreach($entity_ids as $eid) {
        
        // here we should check if we really know the entity by asking the TS for it.
        // this would speed everything up largely, I think.
        $entity = $this->load($eid);
        
        // if there is nothing, continue.
        if(empty($entity))
          continue;

        foreach($field_ids as $key => $fieldid) {
  
          if($fieldid == "eid") {
            $out[$eid][$fieldid] = $eid;
            continue;
          }
          
          
          if($fieldid == "name") {
            // tempo hack
            $out[$eid][$fieldid] = $eid;
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
          if($fieldid == "bundle") {
            // get all the bundles for the eid from us
            $bundles = $this->getBundleIdsForEntityId($eid);
                        
            if(!empty($bundles)) {
              // for now we simply take the first one
              // that might be not so smart
              // who knows @TODO:
              foreach($bundles as $bundle) {
                $out[$eid]['bundle'] = $bundle;
                break;
              }
              continue;
            }
          }

          // every other field is an array, we guess
          // this might be wrong... cardinality?          
          if(!isset($out[$eid][$fieldid]))
            $out[$eid][$fieldid] = array();

          // set the bundle
          // @TODO: This is a hack and might break for multi-federalistic stores
          $pbarray = $pb->getPbEntriesForFid($fieldid);
            
          // if there is no data about this path - how did we get here in the first place?
          // fields not in sync with pb?
          if(empty($pbarray["id"]))
            continue;

          $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($pbarray["id"]);

          // if there is no path we can skip that
          if(empty($path))
            continue;

          // the easy assumption - there already is a bundle.
          $bundle = $out[$eid]['bundle'];

          // if there is no bundle we have to ask the system for the typical bundle
          if(empty($bundle)) {
            
            // we try to get it from cache
            $bundle = $pb->getBundleIdForEntityId($eid);
            
            // nothing was set up to now - so we use the field and ask the field for the typical bundle
            if(empty($bundle)) {
              $bundle = $pb->getBundle($pbarray["id"]);
              // and store it to the entity.
              $out[$eid]['bundle'] = $bundle;

              $pb->setBundleIdForEntityId($eid, $bundle);

            }
          }

          // we ask for the bundle
          $bundle = $pb->getBundle($pbarray["id"]);
          
          // and compare it to the bundle of the entity - if this is not the same, 
          // we don't have to ask for data.
          // @TODO: this is a hack - when the engine asks for the correct 
          // things right away we can remove that here
          if($bundle != $out[$eid]['bundle']) {
            continue;
          }
          
          $clearPathArray = $this->getClearPathArray($path, $pb);
          
          if(!empty($path)) {
            // if this is question for a subgroup - handle it otherwise
            if($pbarray['parent'] > 0 && $path->isGroup()) {
#              drupal_set_message("I am asking for: " . serialize($this->getClearGroupArray($path, $pb)));
              $out[$eid][$fieldid] = array_merge($out[$eid][$fieldid], $this->pathToReturnValue($this->getClearGroupArray($path, $pb), NULL, $eid));
               
            } else // it is a field?
              $out[$eid][$fieldid] = array_merge($out[$eid][$fieldid], $this->pathToReturnValue($clearPathArray, $path->getDatatypeProperty(), $eid));
          }
        }
      }
    }

#    drupal_set_message("out: " . serialize($out));

    return $out;

  }

  /**
   * @inheritdoc
   * The Yaml-Adapter cannot handle field properties, we insist on field values being the main property
   */
  public function loadPropertyValuesForField($field_id, array $property_ids, array $entity_ids = NULL, $language = LanguageInterface::LANGCODE_DEFAULT) {
    drupal_set_message("2");
    
    $main_property = \Drupal\field\Entity\FieldStorageConfig::loadByName($entity_type, $field_name)->getItemDefinition()->mainPropertyName();
    if (in_array($main_property,$property_ids)) {
      return $this->loadFieldValues($entity_ids,array($field_id),$language);
    }
    return array();
  }
  
  public function getQueryObject(EntityTypeInterface $entity_type,$condition,array $namespaces) {
  
    return new Query($entity_type,$condition,$namespaces,$this);
  }
  
  public function deleteOldFieldValue($entity_id, $fieldid, $value, $pb) {
    // get the pb-entry for the field
    // this is a hack and will break if there are several for one field
    $pbarray = $pb->getPbEntriesForFid($fieldid);
    
    $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($pbarray['id']);

    $clearPathArray = $this->getClearPathArray($path, $pb);
    
#    $group = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($pbarray['parent']);
    
#    $path_array = $path->getPathArray();
    
    $sparql = "SELECT DISTINCT * WHERE { GRAPH ?g {";
    foreach($clearPathArray as $key => $step) {
      if($key % 2 == 0) 
        $sparql .= "?x$key a <$step> . ";
      else
        $sparql .= '?x' . ($key-1) . " <$step> ?x" . ($key+1) . " . ";    
    }
    
    if(!empty($primitive)) {
      $sparql .= "?x$key <$primitive> ?out . ";
    }
    
    if(!empty($eid)) {
      $eid = str_replace("\\", "/", $eid);
      $url = parse_url($eid);
      
      if(!empty($url["scheme"]))
        $sparql .= " FILTER (?x0 = <$eid> ) . ";
      else
        $sparql .= " FILTER (?x0 = \"$eid\" ) . ";
    }
    
    $sparql .= " } }";

    $result = $this->directQuery($sparql);

    $outarray = array();

    foreach($result as $key => $thing) {
      $outarray[$key] = array();
      
      for($i=0;$i<count($clearPathArray);$i+=2) {
        $name = "x" . $i; 
        $outarray[$key][$name] = $thing->$name->getValue();
      }
   #     drupal_set_message("we got something!");
  #    $name = 'x' . (count($clearPathArray)-1);
      if(!empty($primitive))
        $outarray[$key]["out"] = $thing->out->getValue();
     # else
     #   $out[] = $thing->$name->dumpValue("text");
    }

    drupal_set_message(serialize($outarray));
    
    drupal_set_message("spq: " . serialize($sparql));
#    drupal_set_message(serialize($this));
    
        
    // add graph handling
    $sparqldelete = "DELETE WHERE { " ;
    
    drupal_set_message(serialize($clearPathArray));
    
    drupal_set_message("I delete field $field from entity $entity_id that currently has the value $value");
  }
  
  public function addNewFieldValue($entity_id, $fieldid, $value, $pb) {
    drupal_set_message("I add field $field from entity $entity_id that currently has the value $value");
  }
  
  public function writeFieldValues($entity_id,array $field_values) {
    drupal_set_message(serialize("Hallo welt!") . serialize($entity_id) . " " . serialize($field_values));
    
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
              
#      foreach($entity_ids as $eid) {
        
        // here we should check if we really know the entity by asking the TS for it.
        // this would speed everything up largely, I think.
        $entity = $this->load($entity_id);
        
        // if there is nothing, continue.
        if(empty($entity))
          continue;
        
        // it would be better to gather this information from the form and not from the ts
        // there might have been somebody saving in between...
        // @TODO !!!
        $old_values = $this->loadFieldValues(array($entity_id), array_keys($field_values));

        if(!empty($old_values))
          $old_values = $old_values[$entity_id];

        drupal_set_message("the old values were: " . serialize($old_values));

        foreach($field_values as $key => $fieldvalue) {
          drupal_set_message("key: " . serialize($key) . " fieldvalue is: " . serialize($fieldvalue)); 
  
          if($key == "eid") {
            // we skip this for now
            continue;
          }
          
          
          if($key == "name") {
            // tempo hack
            continue;
          }
          
          if($key == "bundle") {
            continue;
          }
          
          $mainprop = $fieldvalue['main_property'];
          
          unset($fieldvalue['main_property']);
          
          foreach($fieldvalue as $key2 => $val) {
            // if they are the same - skip
            if($val[$mainprop] == $old_values[$key]) 
              continue;
              
            // if oldvalues are an array and the value is in there - skip
            if(is_array($old_values[$key]) && in_array($val[$mainprop], $old_values[$key]))
              continue;
              
            // now write to the database
            
            // first delete the old values
            if(is_array($old_values[$key]))
              $this->deleteOldFieldValue($entity_id, $key, $old_values[$key][$key2], $pb);
            else
              $this->deleteOldFieldValue($entity_id, $key, $old_values[$key], $pb);
            
            // add the new ones
            $this->addNewFieldValue($entity_id, $key, $val[$mainprop], $pb); 
            
#            drupal_set_message("I would write " . $val[$mainprop] . " to the db and delete " . serialize($old_values[$key]) . " for it.");
            
          }

          
/*          
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
          if($fieldid == "bundle") {
            // get all the bundles for the eid from us
            $bundles = $this->getBundleIdsForEntityId($eid);
                        
            if(!empty($bundles)) {
              // for now we simply take the first one
              // that might be not so smart
              // who knows @TODO:
              foreach($bundles as $bundle) {
                $out[$eid]['bundle'] = $bundle;
                break;
              }
              continue;
            }
          }

          // every other field is an array, we guess
          // this might be wrong... cardinality?          
          if(!isset($out[$eid][$fieldid]))
            $out[$eid][$fieldid] = array();

          // set the bundle
          // @TODO: This is a hack and might break for multi-federalistic stores
          $pbarray = $pb->getPbEntriesForFid($fieldid);
            
          // if there is no data about this path - how did we get here in the first place?
          // fields not in sync with pb?
          if(empty($pbarray["id"]))
            continue;

          $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($pbarray["id"]);

          // if there is no path we can skip that
          if(empty($path))
            continue;

          // the easy assumption - there already is a bundle.
          $bundle = $out[$eid]['bundle'];

          // if there is no bundle we have to ask the system for the typical bundle
          if(empty($bundle)) {
            
            // we try to get it from cache
            $bundle = $pb->getBundleIdForEntityId($eid);
            
            // nothing was set up to now - so we use the field and ask the field for the typical bundle
            if(empty($bundle)) {
              $bundle = $pb->getBundle($pbarray["id"]);
              // and store it to the entity.
              $out[$eid]['bundle'] = $bundle;

              $pb->setBundleIdForEntityId($eid, $bundle);

            }
          }

          // we ask for the bundle
          $bundle = $pb->getBundle($pbarray["id"]);
          
          // and compare it to the bundle of the entity - if this is not the same, 
          // we don't have to ask for data.
          // @TODO: this is a hack - when the engine asks for the correct 
          // things right away we can remove that here
          if($bundle != $out[$eid]['bundle']) {
            continue;
          }
          
          $clearPathArray = $this->getClearPathArray($path, $pb);
          
          if(!empty($path)) {
            // if this is question for a subgroup - handle it otherwise
            if($pbarray['parent'] > 0 && $path->isGroup()) {
#              drupal_set_message("I am asking for: " . serialize($this->getClearGroupArray($path, $pb)));
              $out[$eid][$fieldid] = array_merge($out[$eid][$fieldid], $this->pathToReturnValue($this->getClearGroupArray($path, $pb), NULL, $eid));
               
            } else // it is a field?
              $out[$eid][$fieldid] = array_merge($out[$eid][$fieldid], $this->pathToReturnValue($clearPathArray, $path->getDatatypeProperty(), $eid));
          }
        */
        }
      #}
    }

#    drupal_set_message("out: " . serialize($out));

    return $out;

  }
  
  // -------------------------------- Ontologie thingies ----------------------

  public function addOntologies($iri = NULL) {
    drupal_set_message('iri: ' . $iri);
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
 /* 
    $file = file_get_contents($iri);
    $format = EasyRdf_Format::guessFormat($file, $iri);

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
   */ 
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
    drupal_set_message('results?' . serialize($results)); 
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
                                                                                                                                                         

}
