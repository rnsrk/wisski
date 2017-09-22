<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\Plugin\wisski_salz\Engine\Sparql11RdfEngineWithPB.
 */

namespace Drupal\wisski_adapter_rdf\Plugin\wisski_salz\Engine;

use Drupal\Core\Form\FormStateInterface;
use Drupal\wisski_salz\Plugin\wisski_salz\Engine\Sparql11Engine;
use Drupal\wisski_pathbuilder\PathbuilderEngineInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\wisski_salz\AdapterHelper;
use Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB;

use Drupal\wisski_core\Entity\WisskiEntity;

use Drupal\wisski_adapter_sparql11_pb\Query\Query;
use \EasyRdf;

/**
 * Wiki implementation of an external entity storage client.
 *
 * @Engine(
 *   id = "sparql11_rdf_with_pb",
 *   name = @Translation("Sparql 1.1 RDF With Pathbuilder"),
 *   description = @Translation("Provides access to a SPARQL 1.1 endpoint with RDF and is configurable via a Pathbuilder")
 * )
 */
class Sparql11RdfEngineWithPB extends Sparql11EngineWithPB implements PathbuilderEngineInterface  {


  /******************* BASIC Pathbuilder Support ***********************/
  
  /**
   * @{inheritdoc}
   */
  public function getPrimitiveMapping($step) {
    
    $info = [];

    // this might need to be adjusted for other standards than rdf/owl
    $query = 
      "SELECT DISTINCT ?property "
      ."WHERE {  {"
        ." { ?property rdfs:range rdfs:Literal . } UNION { ?property a owl:DatatypeProperty . } . "
#        ."?property a owl:DatatypeProperty. "
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
        ."{ <$step> rdfs:subClassOf* ?d_def_class. } UNION { <$step> a* ?d_def_class. } UNION { <$step> a* ?inter . ?inter rdfs:subClassOf* ?d_def_class. }"
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
        ."}}}";

    $result = $this->directQuery($query);
#    dpm($query, 'res');

    if (count($result) == 0) return array();
    
    $output = array();
    foreach ($result as $obj) {
      $prop = $obj->property->getUri();
      $output[$prop] = $prop;
    }
    uksort($output,'strnatcasecmp');
    return $output;
  } 
  
}
