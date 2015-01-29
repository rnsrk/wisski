<?php

module_load_include('php', 'wisski_salz', "interface/AdapterInterface");
module_load_include('php', 'wisski_salz', "adapters/sparql11/wisski_easyrdf.php");
include "sites/all/libraries/easyrdf/lib/EasyRdf/Sparql/Client.php";

class SPARQL11Adapter extends EasyRdf_Sparql_Client implements AdapterInterface {


  /**
  * The following settings are currently supported:
  *
  * query_endpoint: The URL to connect to for read operations
  * update_endpoint: The URL to connect to for write operations
  */
  private $settings = array();
  
  public function __construct($queryUri = null, $updateUri = null) {
    
    $this->settings['query_endpoint'] = $queryUri;
    if ($updateUri) {
      $this->settings['update_endpoint'] = $updateUri;
    } else {
      $this->settings['update_endpoint'] = $queryUri;
    }
  }


  /**
  * Internal function to make an HTTP request to SPARQL endpoint
  * copy from original EasyRdf_Client with Sesame-specific overrides
  * this is NOT the function to be called from outside @see requestSPARQL
  *
  * @ignore
  */
  protected function request($type, $query) {
    
    // Check for undefined prefixes
    $prefixes = '';
    $this->updateNamespaces();
    foreach (EasyRdf_Namespace::namespaces() as $prefix => $uri) {
      if (strpos($query, "$prefix:") !== false and strpos($query, "PREFIX $prefix:") === false) {
        $prefixes .=  "PREFIX $prefix: <$uri>\n";
      }
    }

    $client = EasyRdf_Http::getDefaultHttpClient();
    $client->resetParameters();

    // Tell the server which response formats we can parse
    $accept = EasyRdf_Format::getHttpAcceptHeader(
      array(
          'application/sparql-results+json' => 1.0,
              'application/sparql-results+xml' => 0.8
            )
        );
        $client->setHeaders('Accept', $accept);

        if ($type == 'update') {
            $client->setMethod('POST');
            $client->setUri($this->settings['update_endpoint']);
//Begin Dorian
/*            $encodedQuery = 'update='.urlencode($prefixes . $query);
            $client->setRawData($encodedQuery);
            $client->setHeaders('Content-Type', 'application/x-www-form-urlencoded');
 */	    
//End Dorian

	    //Begin Old            
	    $client->setRawData($prefixes . $query);
	    //End Old            
	    $client->setHeaders('Content-Type', /*'application/sparql-update'*/'application/x-www-form-urlencoded');
            
        } elseif ($type == 'query') {
            // Use GET if the query is less than 2kB
            // 2046 = 2kB minus 1 for '?' and 1 for NULL-terminated string on server
            $encodedQuery = 'query='.urlencode($prefixes . $query);
            if (strlen($encodedQuery) + strlen($this->settings['query_endpoint']) <= 2046) {
                $client->setMethod('GET');
                $client->setUri($this->settings['query_endpoint'].'?'.$encodedQuery);
            } else {
                // Fall back to POST instead (which is un-cacheable)
                $client->setMethod('POST');
                $client->setUri($this->settings['query_endpoint']);
                $client->setRawData($encodedQuery);
                $client->setHeaders('Content-Type', 'application/x-www-form-urlencoded');
            }
        }
//        dpm((array)$client);
//        watchdog('wisski_SPARQL_request_uri',$client->getUri());
        $response = $client->request();
        if ($response->getStatus() == 204) {
            // No content
            return $response;
        } elseif ($response->isSuccessful()) {
            list($type, $params) = EasyRdf_Utils::parseMimeType(
                $response->getHeader('Content-Type')
            );
            if (strpos($type, 'application/sparql-results') === 0) {
                return new EasyRdf_Sparql_Result($response->getBody(), $type);
            } else {
                return new EasyRdf_Graph($this->settings['query_endpoint'], $response->getBody(), $type);
            }
        } else {
            throw new EasyRdf_Exception(
                "HTTP request for SPARQL query failed: ".$response->getBody()
            );
        }
    }
  

  /*
  public function __construct($settings_input) {
    $this->settings = $settings_input;
  }
  
  
  public function __construct($query_endpoint,$update_endpoint) {
    $this->settings['query_endpoint'] = $query_endpoint;
    $this->settings['update_edpoint'] = $update_endpoint;
  }
*/

  public function getName() {
    return get_class($this);
  }

  public function getType() {
    return "SPARQL 1.1";
  }


  public function setSettings($name, $value = NULL) {
    
    if (is_array($name)) {
      $this->settings = $name;
    } elseif (is_string($name) || is_integer($name)) {
      $this->settings[$name] = $value;
    }
    if (isset($this->settings['query_endpoint']) && !isset($this->settings['update_endpoint'])) {
        $this->settings['update_endpoint'] = $this->settings['query_endpoint'];
    }
//    drupal_set_message(serialize($this));
    if (!empty($this->settings['do_ontologies_add'])) {
      if (empty($this->settings['ontologies_pending'])) {
        $this->settings['ontologies_pending'] = $this->settings['do_ontologies_add'];
      } else {
        $this->settings['ontologies_pending'] = array_merge($this->settings['ontologies_pending'], $this->settings['do_ontologies_add']);
      }
      unset($this->settings['do_ontologies_add']);

      $this->addOntologies();
    }

    $this->putNamespace('nso',  'http://erlangen-crm.org/120111/');
    $this->putNamespace('ecrm',  'http://erlangen-crm.org/140617/');  
    $this->putNamespace('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
    $this->putNamespace('swrl', 'http://www.w3.org/2003/11/swrl#');
    $this->putNamespace('protege', 'http://protege.stanford.edu/plugins/owl/protege#');
    $this->putNamespace('xsp', 'http://www.owl-ontologies.com/2005/08/07/xsp.owl#');
    $this->putNamespace('owl', 'http://www.w3.org/2002/07/owl#');
    $this->putNamespace('xsd', 'http://www.w3.org/2001/XMLSchema#');
    $this->putNamespace('swrlb', 'http://www.w3.org/2003/11/swrlb#');
    $this->putNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
    $this->putNamespace('skos', 'http://www.w3.org/2004/02/skos/core#');
 
  }

  public function getSettings($name = NULL) {
    drupal_set_message("\$this in getSettings: " . serialize($this));
    if ($name === NULL) return $this->settings;
    return $this->settings[$name];
  }

  public function __get($name) {
    
    return isset($this->settings[$name]) ? $this->settings[$name] : FALSE;
  }
  
  public function __set($name,$value) {
    
    $this->settings[$name] = $value;
  }

  public function querySPARQL($query) {
    return $this->requestSPARQL('query',$query);
  }


  public function updateSPARQL($update) {
    return $this->requestSPARQL('update',$update);
  }
  
  /**
  * Performs a SPARQL 1.1 query or update.
  * The SPARQL query endpoint must be set in $this->settings['query_endpoint']
  * @param $query The SPARQL 1.1 query or update as a string
  * @return list($ok,$results)
  * returns a list consisting of
  * a boolean value $ok that is true iff the query was correctly performed
  * and the result list $results as an assocative array containing result rows as arrays keyed by the variable from the query
  * @author domerz
  */
  private function requestSPARQL($type,$query = NULL) {
        
    $requestcount = &drupal_static('wisski_request_count');
    if (!isset($requestcount)) $requestcount = 0;
    $requestcount++;
    $ok = FALSE;
    $results = array();
    try {
      if (easyrdf()) {
//    	  $this->updateNamespaces();
/*
        if (isset($this->settings['query_endpoint'])) {
            if (isset($this->settings['update_endpoint'])) {
                $sparql = new Wisski_EasyRdf_Sparql_Client($this->settings['query_endpoint'],$this->settings['update_endpoint']);
            } else {
                $sparql = new Wisski_EasyRdf_Sparql_Client($this->settings['query_endpoint']);
            }
      
            if ($type == 'query' && !is_null($query)) {
              $results = $sparql->query($query);
              $ok = TRUE;
            } elseif ($type == 'update'  && !is_null($query)) {
              $results = $sparql->update($query);
              $ok = TRUE;
            }
          }
*/
        $results = $this->request($type,$query);
        $ok = TRUE;
      } else trigger_error("EasyRdf is not installed",E_USER_ERROR);
    } catch (Exception $e) {
      watchdog('wisski_SPARQL_'.$type.'_fail',"Request: ".$query."\nError Message: ".get_class($e)."\n".$e->getMessage());
//        throw $e;
    }
//    drupal_set_message("SPARQL1.1 $type request successfull.<br>Query was '".htmlentities($query)."'");
    return array($ok,$results);
  }
  
  public function testSPARQL() {
    drupal_set_message("Running SPARQL test");

//    $this->settings['ontologies_pending'][] = $this->settings['query_endpoint'];
//    $this->addOntologies();
    list($ok,$results) = $this->updateSPARQL('INSERT {?ont rdf:type_ins owl:Ontology} WHERE {?ont rdf:type owl:Ontology}');
//    list($ok,$results) = $this->querySPARQL('SELECT * WHERE {?s (rdfs:subClassOf)+ owl:Thing.} LIMIT 100');
    if ($ok) {
      drupal_set_message($results->dump());
    } else {
      throw new Exception("Test failed");
    }
  }

  private function updateNamespaces() {
    $spaces_set = &drupal_static(__FUNCTION__);
    if (!isset($spaces_set) || !$spaces_set) {
//      trigger_error("updating Namespaces",E_USER_NOTICE);
      $spaces_set = FALSE;
      $db_spaces = db_select('wisski_salz_sparql11_ontology_namespaces','ns')
                ->fields('ns')
                ->execute()
                ->fetchAllAssoc('id');
      foreach ($db_spaces as $space) {
        EasyRdf_Namespace::set($space->short_name,$space->long_name);
      }
      $spaces_set = TRUE;
    }
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
  
  private function makeDrupalName($entity,$prefix) {
    $pre_len = strlen($prefix);
    return $prefix.preg_replace('/[^a-z0-9_]/u','_',substr(strtolower($entity),0,32-$pre_len));
  }  


  public function pb_definition_settings_page($path_steps = array()) {

  }

/*
  public function query($path_definition, $subject = NULL, $disamb = array(), $value = NULL) {

  }
*/
  
  public function pbQuery($individual_uri,$starting_concept,$path_array,$datatype_property) {

    $query = "SELECT DISTINCT ?data WHERE{ $individual_uri rdf:type/rdfs:subClassOf* $starting_concept .";
    $count = 0;
    if (empty($path_array)) {
      $query .= " $individual_uri $datatype_property ?data. }";
    } else {
      while(!empty($path_array)) {
        $query .= ($count == 0) ? "$individual_uri " : "?individual$count ";
        $query .= array_shift($path_array);
        $count++;
        $query .= " ?individual$count. ";
        $query .= " ?individual$count rdf:type/rdfs:subClassOf* ".array_shift($path_array).". ";
      }
      $query .= " ?individual$count $datatype_property ?data. }";
    }
//    dpm($query);
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok) {
      $out = array();
      foreach ($result as $obj) {
        $out[] = $obj->data->getValue();
      }
//      dpm($out);
      return $out;
    }
    return FALSE;
  }

  public function pbMultiQuery(array $individual_uris,$starting_concept,$path_array,$datatype_property) {
    
    $query = "SELECT DISTINCT ?ind ?data WHERE{ VALUES ?ind { ";
    $query .= implode(' ',$individual_uris);
    $query .= " } ?ind rdf:type/rdfs:subClassOf* $starting_concept .";
    $count = 0;
    if (empty($path_array)) {
      $query .= " ?ind $datatype_property ?data. }";
    } else {
      while(!empty($path_array)) {
        $query .= ($count == 0) ? "?ind " : "?individual$count ";
        $query .= array_shift($path_array);
        $count++;
        $query .= " ?individual$count. ";
        $query .= " ?individual$count rdf:type/rdfs:subClassOf* ".array_shift($path_array).". ";
      }
      $query .= " ?individual$count $datatype_property ?data. }";
    }
//    dpm($query);
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok) {
      $out = array();
      foreach ($result as $obj) {
        $out[$obj->ind->dumpValue('text')][] = $obj->data->getValue();
      }
//      dpm($out);
      return $out;
    }
    return FALSE;
  }

  public function pbUpdate($individual_uri,$individual_name,$starting_concept,$path_array,$datatype_property,$new_data,$delete_old) {
    
    global $base_url;
    $graph_name = variable_get('wisski_graph_name','<'.$base_url.'/wisski_graph>');
    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE{ GRAPH $graph_name {?s ?p ?o}} LIMIT 1");
    if ($ok) {
      if (empty($result)) {
        $this->updateSPARQL("CREATE GRAPH $graph_name");
        variable_set('wisski_graph_name',$graph_name);
      }
    }
    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE{ $individual_uri rdf:type/rdfs:subClassOf* $starting_concept. }");
    if ($ok && !current($result)) {
      $insertion = "\"".preg_replace('/[\"\']/','',utf8_decode($individual_name))."\"";
      list($ok,$result) = $this->updateSPARQL("INSERT{GRAPH $graph_name { $individual_uri rdf:type $starting_concept . $individual_uri rdf:type owl:Individual . $individual_uri rdf:note $insertion .}} WHERE {?s ?p ?o .}");
      if (!$ok) return FALSE;
    }
    $new_individuals = array();
    $switch = FALSE;
    $individual = $individual_uri;
    $class = $starting_concept;
    // we check for the existence of all individuals on the path
    // and if it does not exist we introduce a new owl:Individual and a new wisski_core_entity
    while(!empty($path_array)) {
      if ($switch = !$switch) {
        //even steps are properties
        $property = array_shift($path_array);
        list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE { $individual $property ?other. }");
        if(!$ok) {
          trigger_error("Errors while inserting data",E_USER_ERROR);
          return FALSE;
        } else {
          if (!current($result)) {
            $new_individual = $this->createNewIndividual($property,'',TRUE);
            list($ok_ok,$ok_result) = $this->updateSPARQL("INSERT {GRAPH $graph_name { $individual $property $new_individual .}} WHERE { ?s ?p ?o .}");
            if(!$ok_ok) {
              trigger_error("Errors while inserting data: ",E_USER_ERROR);
              return FALSE;
            } else $individual = $new_individual;
          } else {
            $individual = current($result)->other->dumpValue('text');
          }
        }
      } else {
        //odd steps are classes
        $class = array_shift($path_array);
        list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE { $individual rdf:type/rdfs:subClassOf* $class . }");
        if(!$ok) {
          trigger_error("Errors while inserting data",E_USER_ERROR);
          return FALSE;
        } else {
          if (!current($result)) {
            list($ok_ok,$ok_result) = $this->updateSPARQL("INSERT {GRAPH $graph_name { $individual rdf:type $class . $individual rdf:type owl:Individual .}} WHERE {?s ?p ?o .}");
            if(!$ok_ok) {
              trigger_error("Errors while inserting data: ",E_USER_ERROR);
              return FALSE;
            } else $new_individuals[$class][] = $individual;
          }
        }
      }
    }
    if ($delete_old) {
      list($ok,$result) = $this->updateSPARQL("DELETE WHERE {GRAPH $graph_name { $individual $datatype_property ?data.}}");
      if (!$ok) {
        trigger_error("Errors while inserting data: ",E_USER_ERROR);
        return FALSE;
      }
    }
    $insertion = "\"".preg_replace('/[\"\']/','',utf8_decode($new_data))."\"";
    list($ok,$result) = $this->updateSPARQL("INSERT {GRAPH $graph_name { $individual $datatype_property $insertion .}} WHERE {?s ?p ?o .}");
    if (!$ok) {
      trigger_error("Errors while inserting data: ",E_USER_ERROR);
      return FALSE;
    }
    //returns set of newly introduced uris
    return $new_individuals;
  }
  
  public function insertIndividual($entity_uri,$bundle_uri,$comment = FALSE) {
    
    global $base_url;
    $graph_name = variable_get('wisski_graph_name','<'.$base_url.'/wisski_graph>');
    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE{ GRAPH $graph_name {?s ?p ?o}} LIMIT 1");
    if ($ok) {
      if (empty($result)) {
        $this->updateSPARQL("CREATE GRAPH $graph_name");
        variable_set('wisski_graph_name',$graph_name);
      }
    }
    $insert_string = " $entity_uri a $bundle_uri .";
    $insert_string .= $comment ? " $entity_uri rdfs:comment $comment ." : '';
    list($ok,$result) = $this->updateSPARQL("INSERT {GRAPH $graph_name { $insert_string }} WHERE {?s ?p ?o.}");
    return $ok;
  }

  public function createNewIndividual($property,$name_part = '',$checked = FALSE) {
    
    //just to have some info we take the namespace prefix form the property
    $prefix = strstr($property,':',TRUE);
    //aim at uniqueness
    $suffix = md5(time().$property.rand());
    //ensure uniqueness
    $name = substr($prefix.":".preg_replace('/[^a-zA-Z0-9_]/u','_',$name_part).$suffix,0,32);
    if ($checked) {
      list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE {{ $name ?p ?o .} UNION {?s ?p $name .}} LIMIT 1");
      if (!$ok) return FALSE;
      return empty($result) ? $name : $this->createNewIndividual($property);
    } else return $name;
  }
  
  public function deleteAllTriples($uri) {
  
    $graph_name = variable_get('wisski_graph_name');
    list($ok,$result) = $this->updateSPARQL("DELETE WHERE {GRAPH $graph_name {{ $uri ?p1 ?o1. } UNION {?s1 ?p2 $uri . } UNION {?s2 $uri ?o2}}");
    if (!$ok) {
      trigger_error("Errors while inserting data: ",E_USER_ERROR);
      return FALSE;
    }
    return $ok;
  }

  public function nextClasses($property) {
  
    list($ok,$result) = $this->querySPARQL(
      "SELECT DISTINCT *"
      ."WHERE {"
        ."$property a owl:ObjectProperty. "
        ."$property rdfs:subPropertyOf* ?other. "
        ."?other rdfs:range/(^rdfs:subClassOf)* ?class. "
        ."FILTER NOT EXISTS { "
          ."?other_sub rdfs:subPropertyOf* ?other. "
          ."$property rdfs:subPropertyOf+ ?other_sub. "
          ."?other_sub rdfs:range/(^rdfs:subClassOf)* ?class. "
        ."} "
        ."?class a owl:Class. "
      ."}"
    );
    if ($ok) {
      $output = array();
      foreach ($result as $obj) {
        $output[] = $obj->class->dumpValue('text');
      }
      natsort($output);
      return $output;
    }
    return array();
  }
  
  public function nextProperties($class) {
    
    list($ok,$result) = $this->querySPARQL(
      "SELECT DISTINCT *"
      ."WHERE {"
        ."$class a owl:Class. "
        ."?property a owl:ObjectProperty. "
        ."{"
          ."?property rdfs:subPropertyOf*/rdfs:domain/(^rdfs:subClassOf)* $class. "
        ."}"
/*        ."UNION"
        ."{"
          ."?property owl:inverseOf ?inverse. "
          ."?inverse rdfs:range/(^rdfs:subClassOf)*  $class. "
        ."}"*/
      ."}"
    );
    if ($ok) {
      $output = array();
      foreach ($result as $obj) {
        $output[] = $obj->property->dumpValue('text');
      }
      natsort($output);
      return $output;
    }
    return array();
  }
  
  public function nextPropertiesHierarchy($class) {
    
    list($ok,$result) = $this->querySPARQL(
      "SELECT DISTINCT *"
      ."WHERE {"
        ."$class a owl:Class. "
        ."?property a owl:ObjectProperty. "
        ."?property rdfs:domain ?superclass. "
//        ."?property rdfs:subPropertyOf*/rdfs:domain ?superclass. "
        ."$class rdfs:subClassOf* ?superclass. "
      ."}"
    );
    if ($ok) {
      if (empty($result)) return array();
      $output = array();
      foreach ($result as $obj) {
        $output[$obj->superclass->dumpValue('text')][] = $obj->property->dumpValue('text');
      }
      $keys = array_keys($output);
      natsort($keys);
      $real_output = array_combine($keys,$keys);
      foreach($output as $key => $props) {
        natsort($props);
        $real_output[$key] = $props;
      }
      return $real_output;
    }
    return array();
  }
  
  public function nextDatatypeProperties($class) {
    
    list($ok,$result) = $this->querySPARQL(
      "SELECT DISTINCT *"
      ."WHERE {"
        ."$class a owl:Class. "
        ."?property a owl:DatatypeProperty. "
        ."?property rdfs:subPropertyOf*/rdfs:domain/(^rdfs:subClassOf)* $class. "
      ."}"
    );
    if ($ok) {
      $output = array();
      foreach ($result as $obj) {
        $output[] = $obj->property->dumpValue('text');
      }
      natsort($output);
      return $output;
    }
    return array();
  }
  
  public function nextSteps($node) {
    
    list($ok,$result) = $this->querySPARQL(
      "SELECT DISTINCT * "
      ."WHERE {"
        ."$node a ?type"
      ."}"
    );
    if ($ok) {
      foreach($result as $obj) {
        if ($obj->type->dumpValue('text') == 'owl:Class') return $this->nextPropertiesHierarchy($node);
        if ($obj->type->dumpValue('text') == 'owl:ObjectProperty') return $this->nextClasses($node);
      }
    }
    return array();
  }

  public function getClassesAndComments($entity_uri) {
  
    return array($this->getClasses($entity_uri),$this->getComments($entity_uri));
  }

  public function getComments($entity_uri) {
    
    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE { $entity_uri rdfs:comment ?comment .}");
    if ($ok) {
      $out = array();
      foreach ($result as $obj) {
        $out[] = $obj->comment->dumpValue('text');
      }
      return $out;
    }
    return FALSE;
  }
  
  public function getClasses($entity_uri) {
    
    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE { $entity_uri rdf:type ?class .}");
    if ($ok) {
      if (count($result) > 0) {
        $out = array();
        foreach ($result as $obj) {
          $out[] = $obj->class->dumpValue('text');
        }
        return $out;
      }
    }
    return FALSE;
  }
  
  public function getIndsWithComments($class_uri) {
  
    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE {?ind rdf:type $class_uri . OPTIONAL {?ind rdfs:comment ?comment. }}");
    if ($ok) {
      $out = array();
      foreach ($result as $obj) {
        $ind_uri = $obj->ind->dumpValue('text');
        if (!isset($out[$ind_uri])) $out[$ind_uri] = array();
        if (property_exists($obj,'comment')) $out[$ind_uri][] = $obj->comment->dumpValue('text');
      }
      return $out;
    }
    return FALSE;
  }
  
  public function getIndividuals($class_uri) {
    
    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE { ?ind rdf:type $class_uri .}");
    if ($ok) {
      $out = array();
      foreach ($result as $obj) {
        $out[] = $obj->ind->dumpValue('text');
      }
      return $out;
    }
    return FALSE;
  }
  
  public function getIndCount($class_uri) {
    
    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT (COUNT(?ind) AS ?count) WHERE {?ind a $class_uri .}");
    if ($ok) return current($result)->count->getValue();
    return FALSE;
  }
  
  public function getClassesWithIndCount() {
        
    list($ok,$result) = $this->querySPARQL(
      "SELECT DISTINCT ?class (COUNT(?ind) as ?count)"
      ." WHERE {?class a owl:Class. ?ind a ?class.}"  
      ." GROUP BY ?class"
    ); 
    if ($ok) {
      $out = array();
      foreach ($result as $obj) {
        $out[$obj->class->dumpValue('text')] = $obj->count->getValue();
      }
      return $out;
    }  
    return FALSE;
  }
                                                                                
  
  public function createEntitiesForBundle($bundle) {
    
    drupal_set_message("Creating entities for bundle");
    $bundle_label = $bundle->label;
    $bundle_uri = $bundle->uri;
    $now = time();
    $result = array();
    list($ok,$result) 
      = $this->querySPARQL(
        "SELECT DISTINCT ?ind "
        ."WHERE "
        ."{ "
          ."$bundle_uri a owl:Class. "
          ."?ind a $bundle_uri. "
          ."FILTER NOT EXISTS { "
            ."?n (rdfs:subClassOf)+ $bundle_uri. "
            ."?ind a ?n. "
         ."} "
      ."} "
//  	."LIMIT 1 "
    );
    $then = time();
    trigger_error("Query took ".$this->makeTimeString($then-$now)." seconds",E_USER_NOTICE);
    $now = time();
    if ($ok) {
      foreach($result as $obj) {
        if(isset($obj->ind)) {
          $ind = $obj->ind->dumpValue('text');
          $ind_pref = substr(strstr($bundle_label,':'),1,4);
          $ind_label = $ind;
          $ind_name = $this->makeDrupalName($ind_pref.$ind_label,'ind_');
          $query = new EntityFieldQuery();
          $query->entityCondition('entity_type', 'wisski_core_entity');
          $query->propertyCondition('name',$ind_name,'=');
          $results = $query->execute();            
          if (!empty($results)) continue;
          if (in_array($ind_name,$results)) continue;
          $info = array(
            'type' => $bundle->type,
            'name' => $ind_name,  
            'title' => $ind_label,
            'same_individuals' => array($ind_name),
            'timestamp' => 0, //ensures update on first view
          );
          $entity = entity_create('wisski_core_entity',$info);
          entity_save('wisski_core_entity',$entity);
        }
      }
    }
    $then = time();
    trigger_error("Rest of Setup took ".$this->makeTimeString($then-$now),E_USER_NOTICE);
  }
  
  public function createEntities() {
    
    $now = time();
    $result = array();
    list($ok,$result) 
      = $this->querySPARQL(
        "SELECT DISTINCT ?ind ?class "
        ."WHERE "
        ."{ "
          ."?class a owl:Class. "
          ."?ind a ?class. "
          ."FILTER NOT EXISTS { "
            ."?n (rdfs:subClassOf)+ ?class. "
            ."?ind a ?n. "
         ."} "
      ."} "
//  	."LIMIT 1 "
    );
    $then = time();
    trigger_error("Query took ".$this->makeTimeString($then-$now)." seconds",E_USER_NOTICE);
    $now = time();
    if ($ok) {
      $individuals = array();
      foreach($result as $obj) {
        if(isset($obj->ind) && isset($obj->class)) {
          $individuals[$obj->ind->dumpValue('text')][] = $obj->class->dumpValue('text');
        }
      }
      foreach ($individuals as $ind => $classes) {
        $same_inds = array();
        foreach ($classes as $class) {
          $class_label = $class;
          $ind_pref = substr(strstr($class_label,':'),1,4);
          $ind_label = $ind;
          $ind_name = $this->makeDrupalName($ind_pref.$ind_label,'ind_');
          $same_inds[] = $ind_name;
        }
        foreach ($classes as $class) {
          $class_label = $class;
          $class_name = $this->makeDrupalName($class_label,'');
          $ind_pref = substr(strstr($class_label,':'),1,4);
          $ind_label = $ind;
          $ind_name = $this->makeDrupalName($ind_pref.$ind_label,'ind_');
          $info = array(
            'type' => $class_name,
            'name' => $ind_name,  
            'title' => $ind_label,
            'uri' => $ind_label,
            'timestamp' => 0, //ensures update on first view
            'same_individuals' => $same_inds,
          );
          $entity = entity_create('wisski_core_entity',$info);
          entity_save('wisski_core_entity',$entity);
        if(count($same_inds) > 1) dpm($entity);
        }//foreach ... $class
      }//foreach ... $ind
    }
    $then = time();
    trigger_error("Rest of Setup took ".$this->makeTimeString($then-$now),E_USER_NOTICE);
  }
  

  public function updateEntityInfo(&$entity) {
  
    $ind_uri = $entity->uri;
    $entity_name = $entity->name;
    $gather = array();
    $result = array();
    list($ok,$result)
      = $this->querySPARQL(
        "SELECT DISTINCT ?property ?target "
        ."WHERE "
        ."{ "
          ."?class a owl:Class. "
          ."$ind_uri a ?class. "
          ."?property a owl:ObjectProperty. "
          ."$ind_uri ?property ?target. "
        ."} "
      );
    if ($ok) {
      foreach($result as $obj) {
        if(isset($obj->property) && isset($obj->target))
          $gather['object'][$obj->property->dumpValue('text')][] = $obj->target->dumpValue('text');
      }
    }
    $result = array();
    list($ok,$result)
      = $this->querySPARQL(
        "SELECT DISTINCT ?property ?data "
        ."WHERE "
        ."{ "
          ."?class a owl:Class. "
          ."$ind_uri a ?class. "
          ."?property a owl:DatatypeProperty. "
          ."$ind_uri ?property ?data. "
        ."} "
      );
    if ($ok) {
      foreach($result as $obj) {
        if(isset($obj->property) && isset($obj->data)) {
          $data = $obj->data->dumpValue('text');
          $data = preg_replace('/["\']/','',substr($data,0,strpos($data,'^^')));
          if (strlen($data) > 255) $data = substr($data,0,251)." ...";
          $gather['data'][$obj->property->dumpValue('text')][] = $data;
        }
      }
    }
    $wrapper = entity_metadata_wrapper('wisski_core_entity',$entity);
/*
        if (isset($gather['object'])) {
          foreach($gather['object'] as $prop => $values) {
            $object_property_name = $this->makeDrupalName($prop,'wsk_');
            foreach($values as $target) {
              $target_name = $this->makeDrupalName($ind_pref.$target,'ind_');
              $wrapper->$object_property_name->set($target_name);
            }
          }
        }
*/        
    if (isset($gather['data'])) {
      foreach($gather['data'] as $prop => $values) {
        $data_property_name = $this->makeDrupalName($prop,'wsk_');
        $type = $wrapper->$data_property_name->type();
        if($type == 'list<text>') {
          $wrapper->$data_property_name->set($values);  
        } elseif ($type == 'text') {
          $wrapper->$data_property_name->set(current($values));
        } else  trigger_error("Wrong metadata type $type for $data_property_name in $entity_name",E_USER_WARNING);
      }
    }
    $wrapper->save();
    $entity->timestamp = time();
    $entity->save();
  }

  public function updateClassInfo(&$class) {
/*  
    $fields = array();
    $instances = array();
    $class_name = $class->type;
    $class_label = $class->label;
    
    $now = time();
    list($ok,$result) = $this->querySPARQL(
      "SELECT ?property ?target "
      ."WHERE {"
        ."?property a owl:ObjectProperty. "
        ."{"
          ."{"																		//Object Properties with our class as a domain
            ."?property rdfs:domain $class_label. "	//may also be specified via their inverse having
            ."?property rdfs:range ?target. "			//it as range
          ."}"
          ." UNION "
          ."{"
            ."?p owl:inverseOf ?property. "
            ."?p rdfs:range $class_label. "
            ."?p rdfs:domain ?target. "
          ."}"
        ."}"
        ." UNION "
        ."{"												//here we ensure that forgotten range specifications
          ."?ind a $class_label. "		//are taken into account, too
          ."?ind ?property ?p2. "
        ."}"
      ."}"
    );
    $then = time();
    $query_time = $then - $now;
    if ($ok) {
      foreach($result as $obj) {
        if (isset($obj->target) && isset($obj->property)) {
          $target_label = $obj->target->dumpValue('text');
          $target_name = $this->makeDrupalName($target_label,'');
          $field_label = $obj->property->dumpvalue('text');
          $field_name = $this->makeDrupalName($field_label,'wsk_');
          if (!array_key_exists($field_name,$fields)) {
            $fields[$field_name] = array(
              'field_name' => $field_name,
              'type' => 'entityreference',
              'cardinality' => -1,
              'entity_types' => array('wisski_core_entity'),
              'settings' => array(
                'target_type' => 'wisski_core_entity',
                'handler_settings' => array(
                  'target_bundles' => array($target_name => $target_name),
                ),
              ),
            );
          } else {
            if (!array_key_exists($target_name,$fields[$field_name]['settings']['handler_settings']['target_bundles'])) {
              $fields[$field_name]['settings']['handler_settings']['target_bundles'][$target_name] = $target_name;
            }
          }
          if(!array_key_exists($class_name,$instances) || !array_key_exists($field_name,$instances[$class_name])) {
            $instances[$class_name][$field_name] = array(
              'field_name' => $field_name,
              'label' => t($field_label),
              'bundle' => $class_name,
              'entity_type' => 'wisski_core_entity',
              'widget' => array(
                'type' => 'options_select',
                'module' => 'options',
              ),
              'display' => array(
                'default' => array(
                  'label' => 'inline',
                  'type' => 'entityreference_label',
                  'module' => 'entityreference',
                  'settings' => array(
                    'link' => TRUE,
                  ),
                ),
              ),
            );
          }
        }//if(isset...
      }//foreach...
    }//if($ok)
    $now = time();
    $setup_time = $now - $then;
    list($ok,$result) = $this->querySPARQL(
      "SELECT ?property "
      ."WHERE {"
        ."?data a owl:DatatypeProperty."
        ."{"
        ."?data rdfs:domain/(^rdfs:subClassOf)* $class_label."
        ."}"
        ." UNION "
        ."{"
          ."?ind a $class_label. "
          ."?ind ?property ?p. "
        ."}"
      ."}"
    );
    $then = time();
    $query_time += $then - $now;
    if ($ok) {
      foreach($result as $obj) {
        if (isset($obj->property)) {
          $field_label = $obj->property->dumpValue('text');
          $field_name = $this->makeDrupalName($field_label,'wsk_');
          if (!array_key_exists($field_name,$fields)) {
            $fields[$field_name] = array(
              'field_name' => $field_name,
              'type' => 'text',
              'cardinality' => -1,
              'entity_types' => array('wisski_core_entity'),
              'settings' => array(),
            );
          }
          if(!array_key_exists($class_name,$instances) || !array_key_exists($field_name,$instances[$class_name])) {
            $instances[$class_name][$field_name] = array(
              'field_name' => $field_name,
              'label' => t($field_label),
              'bundle' => $class_name,
              'entity_type' => 'wisski_core_entity',
              'widget' => array(
                'type' => 'text_textfield',
                'module' => 'text',
              ),
              'display' => array(
                'default' => array(
                  'label' => 'inline',
                  'type' => 'text_default',
                  'module' => 'text',
                  'settings' => array(
                  ),
                ),
              ),
            );
          }
        }//if(isset...
      }//foreach...
    }//if($ok)
    drupal_set_message("Fields: ".count($fields)."<br>Instances: ".count($instances));
    foreach($fields as $field) {
      if (field_info_field($field['field_name']) == NULL) {
          field_create_field($field);
      } else {
          field_update_field($field);
      }
    }
    foreach($instances as $bundle_name => $inst_class) {
      foreach($inst_class as $instance) {
        if (field_info_instance('wisski_core_entity',$instance['field_name'],$bundle_name) == NULL) {
          field_create_instance($instance);
        } else {
          field_update_instance($instance);    
        }
      }
    }
    $class->timestamp = time();
    $class->save();
*/
    $this->createEntitiesForBundle($class);
/*    $now = time();
    $setup_time += $now - $then;
    drupal_set_message("Class Update including Entity Creation took ".$this->makeTimeString($query_time)." of query time and ".$this->makeTimeString($setup_time)." for the rest of the setup");
*/
  }

  public function loadClasses() {
   
    $now = time();
    list($ok,$result) 
      = $this->querySPARQL(
        "SELECT DISTINCT ?class"
          ." WHERE {"
            ."?class (rdfs:subClassOf)+ owl:Thing."
          ."}"
//        ." LIMIT 20"
      );
    $then = time();
    trigger_error("Query took ".$this->makeTimeString($then-$now)." seconds",E_USER_NOTICE);
    $now = time();
    if ($ok) {
      $errors = array();
      $classes = array();
      foreach($result as $obj) {
        $class_label = $obj->class->dumpValue('text');
        $class_name = $this->makeDrupalName($class_label,'');
        $class_title = substr($class_label,strpos($class_label,':')+1);
        if (!array_key_exists($class_name,$classes)) {
          $classes[$class_name] = array(
            'type' => $class_name,
            'label' => $class_label,
            'uri' => $class_label,
            'title' => $class_title,
            'weight' => 0,
            'description' => 'retrieved from ontology',
            'timestamp' => 0, //ensures update on first view
            'rdf_mapping' => array(),
          );
        }
      }
      foreach($classes as $class) {
        try {
          $class['bundle of'] = 'wisski_core_entity';
          $entity = entity_create('wisski_core_bundle',$class);
          entity_save('wisski_core_bundle',$entity);
        } catch (PDOException $ex) {
          $errors['PDOException'][] = $ex->getMessage();
        }
      }
    }
    $then = time();
    trigger_error("Rest of Setup took ".$this->makeTimeString($then-$now),E_USER_NOTICE);
    if (!empty($errors)) {
      $out = '';
      // $err is still an array.
      foreach($errors as $err) $out .= $err[0] ."<br>";
      trigger_error('There were exceptions during the setup: '.$out,E_USER_ERROR);

    }
  }

  private function loadOntologyInfo() {
    
    $this->settings['ontologies_loaded'] = array();
    $this->settings['ontologies_pending'] = array();
    $result = db_select('wisski_salz_ontologies','ont')
                ->fields('ont')
                ->condition('sid',$this->settings['sid'],'=')
                ->execute()->fetchAllAssoc('oid');
    foreach ($result as $row) {
      $ont = (array) $row;
      if ($ont['added'] == 1) $this->settings['ontologies_loaded'][$ont['oid']] = $ont;
      elseif ($ont['pending'] == 1) $this->settings['ontologies_pending'][$ont['oid']] = $ont;
    }
  }  

  public function addOntologies($iri = NULL) {
    
    if ($iri != NULL) {
      db_insert('wisski_salz_ontologies')->fields(array('sid' => $this->settings['sid'],'iri' => $iri,'pending' => 1,))->execute(); 
    }
    
    global $base_url;
    $tmpgraph = '<'.$base_url.'/tmp/wisski/add_ontology>';
    $this->loadOntologyInfo();
    list($ok,$result) = $this->querySPARQL('SELECT DISTINCT ?g WHERE {GRAPH ?g {?s ?p ?o}}');
    $knowngraphs = array();
    if (!$ok) {
      foreach($result as $row) $knowngraphs[] = $row->g;
    }
    //if the dummy does not exist, we create it
    if (!in_array($tmpgraph)) $this->updateSPARQL("CREATE GRAPH $tmpgraph");
    while (isset($this->settings['ontologies_pending']) && !empty($this->settings['ontologies_pending'])) {
      
      $o = array_shift($this->settings['ontologies_pending']);
      $load_iri = $o['iri'];
      
      list($ok,$result) = $this->querySPARQL("ASK {<$load_iri> a owl:Ontology}");
      if (!$ok || $result->isFalse()) {
        drupal_set_message("$load_iri is not an ontology");
        continue;
      }
      drupal_set_message("Adding ontology $load_iri");      
      db_update('wisski_salz_ontologies')
        ->fields(array('pending' => 0))
        ->condition('oid',$o['oid'],'=')
        ->execute();
        
      //quick and easy test if ontology is loaded already. this does not detect all loaded onts
      if (isset($this->settings['ontologies_loaded'][$o['oid']])) continue;

      // OWL2 conformant import: support ontology versions and cyclic imports
      // implements CP 1,2 from http://www.w3.org/TR/owl2-syntax/#Ontology_IRI_and_Version_IRI

      // we first load the file into a dummy graph to inspect it...
      list($ok, $errors) = $this->updateSPARQL("DROP GRAPH $tmpgraph");
      
      list($ok, $errors) = $this->updateSPARQL("LOAD <$load_iri> INTO GRAPH $tmpgraph");

      // get ontology and version uri
      $results = $this->getOntologies($tmpgraph);

      if (empty($results)) {
        continue;
      }

      $result = current($results);

//      dpm($result->ont);

      $iri = property_exists($result->iri) ? $result->iri : $result->ont;
      $ver = property_exists($result->ver) ? $result->ver : '';

      // check if it was loaded already and if there are version clashes
      if (isset($this->settings['ontologies_loaded'][$iri])) {
        $loaded = $this->settings['ontologies_loaded'][$iri];
        if ($loaded['version'] == $ver) {
          continue;
        } else {
          drupal_set_message(t('Error importing ontology %iri from %ont: Import version %vernew differs from imported version %verold.', array('%iri' => $iri, '%ont' => $o, '%vernew' => $ver, '%verold' => $loaded['version'])), 'error');
          break;
        }
      }

      //import it: move it from temporal graph to ontology iri
      list($ok, $errors) = $this->updateSPARQL("MOVE GRAPH $tmpgraph TO GRAPH <$iri>");

/*
      $this->settings['ontologies_loaded'][$iri] = array(
        'iri' => $iri,
        'version' => $ver,
        'source' => $o,
      );

      unset($this->settings['ontologies_pending'][$o]);
*/

      db_update('wisski_salz_ontologies')
        ->fields(array('added' => 1, 'version' => $ver))
        ->condition('oid',$o['oid'],'=')
        ->execute();
      
      // get imports
      // get ontology and version uri
      $query = "SELECT DISTINCT ?ont FROM <$iri> WHERE { ?s a owl:Ontology . ?s owl:imports ?ont . }";
      list($ok, $results) = $this->querySPARQL($query);

      if (!$ok) {
        foreach ($results as $err) {
          drupal_set_message(t('Error getting imports of ontology %iri: @e', array('%ont' => $o, '@e' => $err)), 'error');
        }
      }

      foreach ($results as $result) {
//        $this->settings['ontologies_pending'][] = $result['ont'];
        $isin = db_select('wisski_salz_ontologies','onto')
                  ->fields('onto')
                  ->condition('iri',$o['iri'],'=')
                  ->execute();
        if (empty($isin)) {
          db_insert('wisski_salz_ontologies')
            ->fields(array('pending' => 1,'iri' => $result['ont'], 'source' => $o['oid'], 'sid' => $this->settings['sid']))
            ->execute();
        } else {
          db_update('wisski_salz_ontologies')
            ->fields(array('pending' => 1,'iri' => $result['ont'], 'source' => $o['oid'], 'sid' => $this->settings['sid']))
            ->condition('oid',$o['oid'],'=')
            ->execute();
        }
       }
      $this->loadOntologyInfo();
    }

  }
  
  public function getOntologies($graph = NULL) {
    // get ontology and version uri
    if(!empty($graph)) {
      $query = "SELECT DISTINCT ?ont ?iri ?ver FROM $graph WHERE { ?ont a owl:Ontology . OPTIONAL { ?ont owl:ontologyIRI ?iri. ?ont owl:versionIRI ?ver . } }";
    } else
      $query = "SELECT DISTINCT ?ont ?iri ?ver ?graph WHERE { GRAPH ?graph { ?ont a owl:Ontology . OPTIONAL { ?ont owl:ontologyIRI ?iri. ?ont owl:versionIRI ?ver . } }}";

    list($ok, $results) = $this->querySPARQL($query);
    
    if (!$ok) {
      foreach ($results as $err) {
        drupal_set_message(t('Error getting imports of ontology %iri: @e', array('%ont' => $o, '@e' => $err)), 'error');
      }
    }
    
    return $results;   
  }

  public function getExternalLinkURL($uri) {
    //TODO  
  }
  
  private function makeTimeString($time_in_secs) {
    
    if ($time_in_secs < 60) return $time_in_secs.' second(s)';
    $secs = $time_in_secs % 60;
    $sec_string = $secs < 10 ? '0'.$secs : $secs;
    $mins = ($time_in_secs - $secs) / 60;
    if ($mins < 60) return $mins.':'.$sec_string.' minutes';
    $sub_mins = $mins % 60;
    $min_string = $sub_mins < 10 ? '0'.$sub_mins : $sub_mins;
    $hours = ($mins - $sub_mins) / 60;
    return $hours.':'.$min_string.':'.$sec_string.' hours';
  }
}
