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

  public function load($id) {
#    $entity_info = &$this->entity_info;
#    if (isset($entity_info[$id])) return $entity_info[$id];
#    $entity_info = Yaml::parse($this->entity_string);
#    if (isset($entity_info[$id])) return $entity_info[$id];
#    return array();

    // do something here    
    #$query = "SELECT ?s WHERE { ?s a/a owl:Class } LIMIT 10";
    
    $out = array();
    $uri = str_replace('\\', '/', $id);

#    drupal_set_message("parse url: " . serialize(parse_url($uri)));

    $url = parse_url($uri);

    if(!empty($url["scheme"]))    
      $query = "SELECT * WHERE { { <$uri> ?p ?o } UNION { ?s ?p <$uri> } }"; 
    else
      $query = 'SELECT * WHERE { ?s ?p "' . $id . '" }';  
    
    $result = $this->directQuery($query);
    
#    drupal_set_message(serialize($result));
    
#    $out = array();
#    $i = 999;
    
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
    $query = "SELECT ?s WHERE { ?s a/a owl:Class} LIMIT 10";
    
    $result = $this->directQuery($query);
    
#    drupal_set_message(serialize($result));
    
    $out = array();
    $i = 999;
    foreach($result as $thing) {
      
      $uri = $thing->s->dumpValue("text");
      $uri = str_replace('/','\\',$uri);
      
#      drupal_set_message("my uri is: " . htmlentities($uri));
      
      $out[$uri] = array('eid' => $uri, 'bundle' => 'e21_person', 'name' => 'frizt');
      $i++;
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

  public function pathToReturnValue($patharray, $eid = NULL) {
    $sparql = "SELECT DISTINCT * WHERE { ";
    foreach($patharray as $key => $step) {
      if($key % 2 == 0) 
        $sparql .= "?x$key a <$step> . ";
      else
        $sparql .= '?x' . ($key-1) . " <$step> ?x" . ($key+1) . " . ";    
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
      $out[] = $thing->$name->dumpValue("text");
    }
    
    return $out;
    
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
    
    foreach($pbs as $pb) {
      foreach($field_ids as $key => $fieldid) {
        foreach($entity_ids as $eid) {
         
          if($fieldid == "eid") {
            $out[$eid][$fieldid] = $eid;
            continue;
          }

          $path = $pb->getPathForFid($fieldid);
          
          if($fieldid == "bundle") {
            // tempo hack
            $out[$eid][$fieldid] = "e21_person";
            continue;
          }
          
          if($fieldid == "name") {
            // tempo hack
            $out[$eid][$fieldid] = $eid;
            continue;
          }
          
          if(!isset($out[$eid][$fieldid]))
            $out[$eid][$fieldid] = array();

#        if(!empty($path))
#        drupal_set_message("PA: " . serialize($path->getPathArray()));

          if(!empty($path)) {
            $out[$eid][$fieldid] = array_merge($out[$eid][$fieldid], $this->pathToReturnValue($path->getPathArray(), $eid));
          }       
        }
     #   drupal_set_message('we got: ' . serialize($out));
      }
    }
    
#    drupal_set_message("my return out is: " . serialize($out));

    return $out;

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
