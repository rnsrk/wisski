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
  public function getBundleIdForEntityId($entityid) {
    $pb = $this->getPbForThis();

    $query = "SELECT ?class WHERE { <" . $entityid . "> a ?class }";
    
    $result = $this->directQuery($query);
    
#    drupal_set_message(serialize($result));
    
#    $out = array();
    foreach($result as $thing) {
    
      // ask for a bundle from the pb that has this class thing in it
      $groups = $pb->getAllGroups();
      
      foreach($groups as $group) {
        $path_array = $group->getPathArray();
        if($path_array['x' . count($path_array)-1] == $thing->class->dumpValue("text")) {
          $pbpaths = $pb->getPbPaths();
          
          if(!empty($pbpaths[$group->id()]))
            return $pbpaths[$group->id()];
        }
      }
    }

    return FALSE;    
    
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
    $grouppath = $group->getPathArray();    
   
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
    return empty($ent);
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
    
#    drupal_set_message("beginning the call");

    // this approach will be not fast enough in the future...
    // the pbs have to have a better mapping of where and how to find fields
    $pbs = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::loadMultiple();
    
 #   drupal_set_message("eid: " . serialize($entity_ids));
 #   drupal_set_message("fid: " . serialize($field_ids));
    
#    drupal_set_message("my pbs: " . serialize($pbs));

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
#          drupal_set_message("bla: " . serialize($field_ids));
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
            $bundle = $pb->getBundleIdForEntityId($eid);
                        
            if(!empty($bundle)) {
              $out[$eid]['bundle'] = $bundle;
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
          
#          drupal_set_message($fieldid . " - " . serialize($pbarray));

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

          if(!empty($path)) {
            $out[$eid][$fieldid] = array_merge($out[$eid][$fieldid], $this->pathToReturnValue($path->getPathArray(), $path->getDatatypeProperty(), $eid));
          }
        }
      }
    }

#    drupal_set_message("out: " . serialize($out));

    return $out;
/*
    if (is_null($entity_ids)) {
      $ents = $this->loadMultiple();
      if (is_null($field_ids)) return $ents;
      $field_ids = array_flip($field_ids);
      return array_map(function($array) use ($field_ids) {return array_intersect_key($array,$field_ids);},$ents);
    }
    $result = array();
    foreach ($entity_ids as $entity_id) {
      $ent = $this->load($entity_id);
      drupal_set_message('miau ' . serialize($ent));
      if (!is_null($field_ids)) {
        $ent = array_intersect_key($ent,array_flip($field_ids));
      }
      drupal_set_message('miau2 ' . serialize($ent));
      
      $result[$entity_id] = $ent;
    }
    drupal_set_message("result is: " . serialize($result));
    return $result;
  */
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
  

}
