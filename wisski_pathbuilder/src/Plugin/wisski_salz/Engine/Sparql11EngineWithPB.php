<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\Plugin\wisski_salz\Engine\Sparql11EngineWithPB.
 */

namespace Drupal\wisski_pathbuilder\Plugin\wisski_salz\Engine;

use Drupal\Core\Form\FormStateInterface;
use Drupal\wisski_salz\Plugin\wisski_salz\Engine\Sparql11Engine;
use Drupal\wisski_pathbuilder\PathbuilderEngineInterface;

/**
 * Wiki implementation of an external entity storage client.
 *
 * @Engine(
 *   id = "sparql11_with_pb",
 *   name = @Translation("Sparql 1.1 With Pathbuilder"),
 *   description = @Translation("Provides access to a SPARQL 1.1 endpoint and is configurable via a Pathbuilder")
 * )
 */
class Sparql11EngineWithPB extends Sparql11Engine implements PathBuilderEngineInterface  {

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


}
