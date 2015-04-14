<?php

module_load_include('php', 'wisski_salz', "interface/AdapterInterface");
module_load_include('php', 'wisski_salz', "adapters/sparql11/wisski_easyrdf");
libraries_load('easyrdf');
// include_once "sites/all/libraries/easyrdf/lib/EasyRdf/Sparql/Client.php";

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
            $encodedQuery = 'update='.urlencode($prefixes . $query);
            $client->setRawData($encodedQuery);
            $client->setHeaders('Content-Type', 'application/x-www-form-urlencoded');
	    
//End Dorian
/*
	    //Begin Old            
//	    $client->setRawData($prefixes . $query);
	    //End Old            
	    $client->setHeaders('Content-Type', 'application/rdf+xml;charset=UTF-8'/*'text/plain' /*'application/sparql-update','application/x-www-form-urlencoded');
	    */           
        } elseif ($type == 'query') {
            // Use GET if the query is less than 2kB
            // 2046 = 2kB minus 1 for '?' and 1 for NULL-terminated string on server
            $encodedQuery = 'query='.urlencode($prefixes . $query);
            if (strlen($encodedQuery) + strlen($this->settings['query_endpoint']) <= 2046) {
                $client->setMethod('GET');
                $client->setUri($this->settings['query_endpoint'].'?'.$encodedQuery);
            } else {
//                dpm(array('query' => $query, 'encoded' => $this->settings['query_endpoint'].'?'.$encodedQuery));
//                trigger_error('Query size > 2048. Switch to POST mode',E_USER_NOTICE);
                // Fall back to POST instead (which is un-cacheable)
                $client->setMethod('POST');
                $client->setUri($this->settings['query_endpoint']);
                $client->setRawData($encodedQuery);
                $client->setHeaders('Content-Type', 'application/x-www-form-urlencoded');
            }
        }
#        dpm((array)$client);
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
    /*
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
    */
    
    $namespaces = $this->getNamespaces();

    if(!empty($namespaces)) { 	   
      foreach($this->getNamespaces() as $key => $value) {
        $this->putNamespace($key, $value);
      }
    } else { // @TODO: this is not good
      $this->putNamespace('nso',  'http://erlangen-crm.org/120111/');
      $this->putNamespace('ns1',  'http://erlangen-crm.org/140220/');
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
//    watchdog('wisski_sparql_update',$update);
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
    
    $ok = FALSE;
    $results = array();
    try {
      if (easyrdf()) {
//        wisski_core_tick('SPARQLAdapter: begin query '.$requestcount);
        $results = $this->request($type,$query);
//        wisski_core_tick('SPARQLAdapter: end query '.$requestcount);
        if (get_class($results) == 'EasyRdf_Sparql_Result' && $results->numRows() > 0) {
          //this is as expected
        } else return array(TRUE,array());
        $ok = TRUE;
      } else trigger_error("EasyRdf is not installed",E_USER_ERROR);
    } catch (Exception $e) {
      watchdog('wisski_SPARQL_'.$type.'_fail',"Request: ".$query."\nError Message: ".get_class($e)."\n".$e->getMessage());
      if (variable_get('wisski_throw_exceptions',FALSE)) {
        $trace = debug_backtrace();
        $i = 0;
        $subtrace = array();
        while (!empty($trace) && $i < 5) {
          $tr = array_shift($trace);
          if ($i > 1) $subtrace[] = $tr;
          $i++;
        }
        dpm($subtrace);
        throw $e;
      }
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
                ->fetchAllAssoc('short_name');
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
  /**
   * @param starting_concept string representing an owl:Class, common start for all given paths
   *
   * @param paths is an array of associative arrays that may contain
   * the following entries:
   * $key								| $value
   * ------------------------------------------------------------
   * 'path_array' 			| array of strings representing owl:ObjectProperties 
   *                    | and owl:Classes in alternating order
   * 'datatype_property'| string representing an owl:DatatypeProperty
   * 'required'					| boolean, TRUE if the path must have to return
   *										| at least one data result
   * 'maximum'					| int specifying the maximum number of returned
   *										| data values for this path, unlimited if unspecified
   * 'id'								| ID of the path, matching the key of the result data
   *										| in the output array
   *
   * @param settings is an associative array that may contain the following 
   * entries:
   * $key 			| $value
   * ------------------------------------------------------------
   * 'limit'		| int setting the SPARQL query LIMIT
   * 'offset'		| int setting the SPARQL query OFFSET
   * 'order'		| string containing 'ASC' or 'DESC' (or 'RAND')
   * 'qualifier'| SPARQL data qualifier e.g. 'STR'
   * 'matches'	| array of strings, at least one of the data must match
   * 'uris'			| array of strings representing owl:Individuals on which the
   *						| query is triggered
   */
  public function pbQuery($starting_concept,array $paths,array $settings = array()) {
    
//    dpm(func_get_args(),__FUNCTION__);
    if (empty($starting_concept)) {
      ddebug_backtrace();
      throw new InvalidArgumentException('you must specify a starting concept');
    }
    $query = "SELECT DISTINCT";
    if (!isset($settings['uris']) || count($settings['uris']) > 1) $query .= " ?ind";
    $datas = array();
    for ($i = 0; $i < count($paths); $i++) {
      $datas[] = "?data$i";
      $query .= " ?data$i";
      $query .= " ?tar$i";
    }
    $query .= " WHERE{";
    $ind = "?ind";
    $single_ind = FALSE;
    if (isset($settings['uris'])) {
      if(count($settings['uris']) > 1) $query .= " VALUES ?ind {".implode(' ',$settings['uris'])."}";
      else {
        $ind = current($settings['uris']);
        $single_ind = TRUE;
      }
    }
    $query .= " $ind rdf:type $starting_concept .";
    $i = 0;
    $ids = array();
    foreach($paths as $key => $path) {
      if (isset($path['id'])) $ids[$i] = $path['id'];
      else $ids[$i] = $key;
      if (!isset($path['path_array']) || !is_array($path['path_array'])) {
        dpm($path,'wrong path');
        ddebug_backtrace();
        throw new Exception('path_array');
      }
      $path_array = $path['path_array'];
      $datatype_property = $path['datatype_property'];
      $count = 0;
      $disamb = isset($path['disamb']) && $path['disamb'] > 0 ? $path['disamb'] : count($path_array);
      $disamb = floor($disamb/2);
      if (count($paths) > 1 || (isset($path['maximum']) && $path['maximum'] > 0)) {
        $query .= " OPTIONAL {SELECT DISTINCT ".($single_ind ? '' : "?ind")." ?data$i ?tar$i WHERE {";
      }
      if (empty($path_array)) {
        if (!empty($datatype_property)) {
          $query .= " BIND($ind AS ?tar$i)";
          $query .= " $ind $datatype_property ?data$i .";
        }
      } else {
        $switch = FALSE;
        while(!empty($path_array)) {
          if ($switch = !$switch) {
            $query .= ($count == 0) ? "$ind " : "?p".$i."c$count ";
            $query .= array_shift($path_array);
            $count++;
            $query .= " ?p".$i."c$count. ";
          } else {
            $concept = array_shift($path_array);
//          $query .= " ?$count rdf:type/rdfs:subClassOf* ".array_shift($path_array).". ";
            $query .= " ?p".$i."c$count rdf:type ".$concept.". ";
            if ($count == $disamb) {
              $query .= " BIND(?p".$i."c$count AS ?tar$i)";
            }
          }
        }
        if (!empty($datatype_property)) {
          $query .= " ?p".$i."c$count $datatype_property ?data$i .";  
        } else {
          $query .= " BIND(?p".$i."c$count AS ?data$i)";
        }
      }
      if (count($paths) > 1 || (isset($path['maximum']) && $path['maximum'] > 0)) {
        $query .= "}";
        if (isset($path['maximum']) && $path['maximum'] > 0) $query .=  " LIMIT ".$path['maximum'];
        $query .= " }";//close sub-SELECT
        if (isset($path['required']) && $path['required']) $query .= " FILTER(BOUND(?data$i))";
      }
      $i++;
    }
    if (isset($settings['matches'])) {
      foreach ($settings['matches'] as $match) {
        $query .= " FILTER(";
        $data = next($datas);
        if (!empty($data)) $query .= "CONTAINS($data,\"$match\")";
        while ($data = next($datas)) {
          $query .= " || CONTAINS($data,\"$match\")";
        }
        $query .= ")";
      }
    }
    $query .= " }"; // close WHERE
    if (isset($settings['order']) && !empty($datas)) {
      $query .= " ORDER BY";
      foreach($datas as $data) {
        $query .= " DESC(BOUND($data)) ";
        if (isset($settings['qualifier'])) $data = $settings['qualifier']."($data)";
        $query .= $settings['order']."($data)";
      }
    }
    if (isset($settings['limit']) && $settings['limit'] > 0) {
      $query .= " LIMIT ".$settings['limit'];
    }    
    if (isset($settings['offset'])) {
      $query .= " OFFSET ".$settings['offset'];
    }
    wisski_core_tick('start PB query');
//    throw new Exception('STOP');
    list($ok,$result) = $this->querySPARQL($query);
    wisski_core_tick('end PB query');
    if ($ok) {
      //generate output array, key order is:
      //[individual uri][target uri][data][0..?]
      $out = array();      
      foreach ($result as $obj) {
        if (!$single_ind) $ind = $obj->ind->dumpValue('text');
        if (!isset($out[$ind])) {
          $out[$ind] = array();
        }
        //make sure we have a numerically keyed output array for every target individual
        $deltas = array();
        for ($i = 0; $i < count($paths); $i++) {
          if(property_exists($obj,'tar'.$i)) {
            $target_uri = $obj->{'tar'.$i}->dumpValue('text');
            $key = array_search($target_uri,$deltas);
            if ($key === FALSE) {
              $key = count($deltas);
              $deltas[] = $target_uri;
            }
            if (!isset($out[$ind][$key])) $out[$ind][$key] = array();
            if (property_exists($obj,'data'.$i)) {
              $obj_data = $obj->{'data'.$i};
              if (method_exists($obj_data,'getValue')) $value = $obj_data->getValue();
              else $value = $obj_data->dumpValue('text');
//WATCH OUT:	here we assume to have at most one entry per target individual
              $out[$ind][$key][$ids[$i]] = $value;
              //hack to have the target_uri in the answer set
              //remember we cannot save this as the key, because drupal needs
              //numerically keyed $delta
              $out[$ind][$key]['target_uri'] = $target_uri;
            }
          }
        }
      }
//      dpm(func_get_args()+array('query' => $query,'result' => $out),__FUNCTION__);
      return $out;
    }
    return FALSE;
  }
  
  public function pbQuerySingle($individual_uri,$starting_concept,$path_array,$datatype_property,$limit = NULL,$offset = 0,$order = FALSE,$asc = TRUE,$qualifier = 'STR') {

    $path = array();
    if (isset($path_array) || isset($datatype_property)) {
      $path = array(
        array(
          'path_array' => $path_array,
          'datatype_property' => $datatype_property,
        ),
      );
    }
    $settings = array();
    if (isset($limit)) $settings['limit'] = $limit;
    if ($offset > 0) $settings['offset'] = $offset;
    if ($order) {
      $settings['order'] = $asc ? 'ASC' : 'DESC';
    }
    $settings['qualifier'] = $qualifier;
    $settings['uris'] = array($individual_uri);
    return $this->pbQuery($starting_concept,$path,$settings);
  }

  public function pbQueryAll($starting_concept,$path_array,$datatype_property,$limit = NULL,$offset = 0,$order = FALSE,$asc = TRUE,$qualifier = 'STR') {

    $path = array();
    if (isset($path_array) || isset($datatype_property)) {
      $path = array(
        array(
          'path_array' => $path_array,
          'datatype_property' => $datatype_property,
        ),
      );
    }
    $settings = array();
    if (isset($limit)) $settings['limit'] = $limit;
    if ($offset > 0) $settings['offset'] = $offset;
    if ($order) {
      $settings['order'] = $asc ? 'ASC' : 'DESC';
    }
    $settings['qualifier'] = $qualifier;
    return $this->pbQuery($starting_concept,$path,$settings);
  }

  public function pbQuerySingleMultipath($individual_uri,$starting_concept,$paths) {
    $settings['uris'] = array($individual_uri);
    return $this->pbQuery($starting_concept,$paths,$settings);
  }
  
  public function pbQueryMultiPath($starting_concept,$paths,$limit = NULL,$offset = 0,$order = FALSE,$asc = TRUE,$qualifier = 'STR',$contained_strings = array()) {
  
    $settings = array();
    if (isset($limit)) $settings['limit'] = $limit;
    if ($offset > 0) $settings['offset'] = $offset;
    if ($order) {
      $settings['order'] = $asc ? 'ASC' : 'DESC';
    }
    $settings['qualifier'] = $qualifier;
    if(!empty($contained_strings)) $settings['matches'] = $contained_strings;    
    return $this->pbQuery($starting_concept,$paths,$settings);
  }


  public function pbTitleQuery($uris,$starting_concept,$paths) {

    $settings = array('uris' => $uris);
    return $this->pbQuery($starting_concept,$paths,$settings);
  }

  public function pbMultiQuery(array $individual_uris,$starting_concept,$path_array,$datatype_property,$single_result = FALSE) {
    
    $path = array();
    if (isset($path_array) || isset($datatype_property)) {
      $path = array(
        array(
          'path_array' => $path_array,
          'datatype_property' => $datatype_property,
        ),
      );
    }
    if ($single_result) $path['maximum'] = 1;
    $settings = array('uris' => $individual_uris);
    return $this->pbQuery($starting_concept,$path,$settings);
  }

  /** 
   * Escapes a string according to http://www.w3.org/TR/rdf-sparql-query/#rSTRING_LITERAL.
   * @author Martin Scholz
   */
  function wisski_store_escape_sparql_literal($literal, $escape_backslash = TRUE) {
    $sic  = array("\\",   '"',   "'",   "\b",  "\f",  "\n",  "\r",  "\t");
    $corr = array($escape_backslash ? "\\\\" : "\\", '\\"', "\\'", "\\b", "\\f", "\\n", "\\r", "\\t");
    #$literal = wisski_store_mb_str_replace($sic, $corr, $literal);
    $literal = str_replace($sic, $corr, $literal);
    return $literal;
  }

  public function pbUpdate($individual_uri,$individual_name,$starting_concept,$path_array,$datatype_property,$disamb,$new_data,$delete_old) {
    dpm(func_get_args(),__FUNCTION__);
    if (is_array($new_data)) {
      $inds = array();
      foreach($new_data as $nd) {
        $new_inds = $this->pbUpdate($individual_uri,$individual_name,$starting_concept,$path_array,$datatype_property,$disamb,$nd,$delete_old);
        if ($new_inds !== FALSE)
          $inds += $new_inds;
        else return FALSE;
      }
      return $inds;
    }
    $new_data = $this->wisski_store_escape_sparql_literal($new_data);
    global $base_url;
    $graph_name = variable_get('wisski_graph_name','<'.$base_url.'/wisski_graph>');
    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE{ GRAPH $graph_name {?s ?p ?o}} LIMIT 1");
    if ($ok) {
      if (empty($result)) {
        $this->updateSPARQL("CREATE GRAPH $graph_name");
        variable_set('wisski_graph_name',$graph_name);
      }
    }
    // check and if neccessary insert individual information
    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE{ $individual_uri rdf:type/rdfs:subClassOf* $starting_concept. }");
    if ($ok && empty($result)) {
      $insertion = "\"".preg_replace('/[\"\']/','',utf8_decode($individual_name))."\"";
      $query = "INSERT{"
        ."GRAPH $graph_name {"
          ." $individual_uri rdf:type $starting_concept ."
          ." $individual_uri rdf:type owl:Individual ."
          ." $individual_uri rdf:note $insertion ."
        ."}"
      ."} WHERE {?s ?p ?o .}";
      list($ok,$result) = $this->updateSPARQL($query);
      if (!$ok) return FALSE;
    }
    $new_individuals = array();
    $switch = FALSE;
    $individual = $individual_uri;
    $class = $starting_concept;
    // we check for the existence of all individuals on the path
    // and if it does not exist we introduce a new owl:Individual
    while(!empty($path_array)) {
      if ($switch = !$switch) {
        //even steps are properties
        $property = array_shift($path_array);
        list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE { $individual $property ?other. }");
        if(!$ok) {
          trigger_error("Errors while inserting data",E_USER_ERROR);
          return FALSE;
        } else {
          if (empty($result)) {
            $new_individual = $this->createNewIndividual($property,'',TRUE,'show_errors');
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
        if (!empty($disamb) && $disamb === $class) {
          $other_new_individuals = $this->pbUpdate($individual,$individual,$disamb,$path_array,$datatype_property,'',$new_data,$delete_old);
          if ($other_new_individuals !== FALSE)
            return $new_individuals + $other_new_individuals;
          else return FALSE;
        }
        list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE { $individual rdf:type/rdfs:subClassOf* $class . }");
        if(!$ok) {
          trigger_error("Errors while inserting data",E_USER_ERROR);
          return FALSE;
        } else {
          if (empty($result)) {
            list($ok_ok,$ok_result) = $this->updateSPARQL("INSERT {GRAPH $graph_name { $individual rdf:type $class . $individual rdf:type owl:Individual .}} WHERE {?s ?p ?o .}");
            if(!$ok_ok) {
              trigger_error("Errors while inserting data: ",E_USER_ERROR);
              return FALSE;
            } else $new_individuals[$class][] = $individual;
          }
        }
      }
    }
    if (isset($datatype_property)) {
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

  public function uriExists($uri) {
    list($ok,$result) = $this->querySPARQL("SELECT DISTINCT * WHERE {{ $uri ?p ?o .} UNION {?s ?p $uri .}} LIMIT 1");
    return $ok && !empty($result);
  }

  public function createNewIndividual($property,$name_part = '',$checked = FALSE) {
    
    //just to have some info we take the namespace prefix form the property
    $prefix = strstr($property,':',TRUE);
    
    //aim at uniqueness
    $suffix = md5(time().$property.rand());
    
    
    //ensure uniqueness
    $name = substr($prefix.":".preg_replace('/[^a-zA-Z0-9_]/u','_',$name_part).$suffix,0,32);
    if (count(func_get_args()) === 4) {
      dpm(func_get_args()+array('result'=>$name),'FAIL: '.__FUNCTION__);
//      throw new Exception('Could not create new URI');
    }
    if ($checked) {
      if ($this->uriExists($name)) return $this->createNewIndividual($property,$name_part,TRUE);
    }
    return $name;
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

  public function nextClasses($property,$property_after = NULL) {
  
    $query = 
      "SELECT DISTINCT ?class "
      ."WHERE { "
        ."$property rdfs:subPropertyOf* ?r_super_prop. "
        ."?r_super_prop rdfs:range ?r_super_class. "
        ."FILTER NOT EXISTS { "
          ."?r_sub_prop rdfs:subPropertyOf+ ?r_super_prop. "
          ."$property rdfs:subPropertyOf* ?r_sub_prop. "
          ."?r_sub_prop rdfs:range ?r_any_class. "
        ."} "
        ."?class rdfs:subClassOf* ?r_super_class. ";
    if (isset($property_after)) {
      $query .= "$property_after rdfs:subPropertyOf* ?d_super_prop. "
        ."?d_super_prop rdfs:domain ?d_super_class. "
        ."FILTER NOT EXISTS { "
          ."?d_sub_prop rdfs:subPropertyOf+ ?d_super_prop. "
          ."$property_after rdfs:subPropertyOf* ?d_sub_prop. "
          ."?d_sub_prop rdfs:domain ?d_any_class. "
        ."} "
        ."?class rdfs:subClassOf* ?d_super_class. ";
    }
    $query .= "}";
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok) {
      $output = array();
      foreach ($result as $obj) {
        $class = $obj->class->dumpValue('text');
        $output[$class] = $class;
      }
      natsort($output);
      dpm($output,$query);
      return $output;   
    }
    return array();
  }
  
  public function nextPropertiesHierarchy($class,$class_after = NULL) {
    
    $query = 
      "SELECT DISTINCT ?property ?d_superclass "
      ."WHERE { "
        ."?property a owl:ObjectProperty. "
        ."?d_super_prop rdfs:domain ?d_superclass. "
        ."?property rdfs:subPropertyOf* ?d_super_prop. "
        ."$class rdfs:subClassOf* ?d_superclass. "
        ."FILTER NOT EXISTS { "
          ."?d_sub_prop rdfs:subPropertyOf+ ?d_super_prop. "
          ."?property rdfs:subPropertyOf* ?d_sub_prop. "
          ."?d_sub_prop rdfs:domain ?d_any_class. "
        ."} ";
    if (isset($class_after)) {
      $query .= 
        "?r_super_prop rdfs:range ?r_superclass. "
        ."?property rdfs:subPropertyOf* ?r_super_prop. "
        ."$class_after rdfs:subClassOf* ?r_superclass. "
        ."FILTER NOT EXISTS { "
          ."?r_sub_prop rdfs:subPropertyOf+ ?r_super_prop. "
          ."?property rdfs:subPropertyOf* ?r_sub_prop. "
          ."?r_sub_prop rdfs:range ?r_any_class. "
        ."} ";
    }
    $query .= "}";
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok) {
      if (empty($result)) return array();
      $output = array();
      foreach ($result as $obj) {
        $prop = $obj->property->dumpValue('text');
        $output[$obj->d_superclass->dumpValue('text')][$prop] = $prop;
      }
      $keys = array_keys($output);
      if (!in_array($class,$keys)) $keys[] = $class.' ('.t('empty').')';
      natsort($keys);
      $real_output = array_fill_keys($keys,array());
      foreach($output as $key => $props) {
        natsort($props);
        $real_output[$key] = $props;
      }
      dpm($real_output,$query);
      return $real_output;
    }
    return array();
  }
  
  public function nextDatatypeProperties($class) {
    
    list($ok,$result) = $this->querySPARQL(
      "SELECT DISTINCT ?property "
      ."WHERE { "
        ."?property a owl:DatatypeProperty. "
        ."?property rdfs:subPropertyOf*/rdfs:domain/(^rdfs:subClassOf)* $class. "
      ."}"
    );
    if ($ok) {
      $output = array();
      foreach ($result as $obj) {
        $prop = $obj->property->dumpValue('text');
        $output[$prop] = $prop;
      }
      natsort($output);
      dpm($output,'queried datatype props for '.$class);
      return $output;
    }
    return array();
  }
  
  public function nextSteps($node,$node_after = NULL) {
    
    list($ok,$result) = $this->querySPARQL(
      "SELECT DISTINCT ?type "
      ."WHERE { "
        ."$node a ?type."
        .(isset($node_after) ? " $node_after a ?type." : '')
      ."}"
    );
    if ($ok) {
      foreach($result as $obj) {
        if ($obj->type->dumpValue('text') == 'owl:Class') return $this->nextPropertiesHierarchy($node,$node_after);
        if ($obj->type->dumpValue('text') == 'owl:ObjectProperty') return $this->nextClasses($node,$node_after);
      }
    }
    return array();
  }

  public function getClassesAndComments($entity_uri) {
  
    return array($this->getClasses($entity_uri),$this->getComments($entity_uri));
  }

  public function getAllTriplesForURI($uris) {
    
    if (is_array($uris)) {
      $uris = implode(' ',$uris);
    }
    $query = "SELECT ?s ?p ?o WHERE {
      VALUES ?x { $uris }
      {?x ?p ?o. BIND(?x AS ?s)}
      UNION {?s ?x ?o. BIND(?x AS ?p)}
      UNION {?s ?p ?x. BIND(?x AS ?o)}
      }";
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok) {
      return $result;
    } else return FALSE;
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
  
  public function doesClassExist($class_uri) {
    list($ok,$result) = $this->querySPARQL(
      "ASK {"
      ." { ?ind a $class_uri .}"
      ." UNION { $class_uri a ?class. ?class rdfs:subClassOf* owl:CLass.}"
      ."}"
    );
    if ($ok) {
      return $result[0]->getValue();
    }
    return FALSE;
  }
  
  public function getIndCount($class_uri = NULL) {
    
    if (empty($class_uri)) {
      list($ok,$result) = $this->querySPARQL("SELECT DISTINCT (COUNT(?ind) AS ?count) WHERE {?ind a/a ?type}");
    } else {
      list($ok,$result) = $this->querySPARQL("SELECT DISTINCT (COUNT(?ind) AS ?count) WHERE {?ind a $class_uri .}");
    }
    if ($ok) return current($result)->count->getValue();
    return FALSE;
  }
  
  public function getClassesWithIndCount() {
        
    list($ok,$result) = $this->querySPARQL(
      "SELECT ?class (COUNT(?ind) as ?count)"
      ." WHERE {SELECT DISTINCT ?class ?ind WHERE {?class a owl:Class. ?ind a ?class.}}"  
      ." GROUP BY ?class"
    ); 
    if ($ok) {
      $out = array();
      foreach ($result as $obj) {
        if (!empty($obj->class)) $out[$obj->class->dumpValue('text')] = $obj->count->getValue();
      }
      return $out;
    }  
    return FALSE;
  }
  
  public function getMatchingInds($match,$limit=0) {
  
    $query = "SELECT DISTINCT ?ind ?class"
      ." WHERE {"
        ." ?ind a ?class."
        ." ?class a ?type."
        ." FILTER(CONTAINS(STR(?ind),\"$match\"))"
      ."}";
    if ($limit > 0) $query .= " LIMIT $limit";
    list($ok,$result) = $this->querySPARQL($query);
    if ($ok) {
      $out = array();
      foreach($result as $obj) {
        $uri = $obj->ind->dumpValue('text');
        $out[$obj->class->dumpValue('text')][$uri] = $uri;
      }
      dpm(func_get_args()+array('query'=>$query,'result'=>$out),__FUNCTION__);
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
          $query->entityCondition('entity_type', 'wisski_individual');
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
          $entity = entity_create('wisski_individual',$info);
          entity_save('wisski_individual',$entity);
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
          $entity = entity_create('wisski_individual',$info);
          entity_save('wisski_individual',$entity);
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
    $wrapper = entity_metadata_wrapper('wisski_individual',$entity);
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
              'entity_types' => array('wisski_individual'),
              'settings' => array(
                'target_type' => 'wisski_individual',
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
              'entity_type' => 'wisski_individual',
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
              'entity_types' => array('wisski_individual'),
              'settings' => array(),
            );
          }
          if(!array_key_exists($class_name,$instances) || !array_key_exists($field_name,$instances[$class_name])) {
            $instances[$class_name][$field_name] = array(
              'field_name' => $field_name,
              'label' => t($field_label),
              'bundle' => $class_name,
              'entity_type' => 'wisski_individual',
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
        if (field_info_instance('wisski_individual',$instance['field_name'],$bundle_name) == NULL) {
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
      uksort($classes,'strnatcasecmp');
      foreach($classes as $class) {
        try {
          $class['bundle of'] = 'wisski_individual';
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
/*
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
   # if (!in_array($tmpgraph)) $this->updateSPARQL("CREATE GRAPH $tmpgraph");
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
*/
/*
      $this->settings['ontologies_loaded'][$iri] = array(
        'iri' => $iri,
        'version' => $ver,
        'source' => $o,
      );

      unset($this->settings['ontologies_pending'][$o]);
*/
/*
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
*/

    // check if the Ontology is already there
    list($ok,$result) = $this->querySPARQL("ASK {<$iri> a owl:Ontology}");

    if (!$ok) { // we've got something weired.
      drupal_set_message("Store is not requestable.", 'error');
      return;
    } else if(!empty($result)){ // if it is not false it is already there
      drupal_set_message("$iri is already loaded.", 'error');
      return;
    }

    // if we get here we may load the ontology
    $query = "LOAD <$iri> INTO GRAPH <$iri>";
   list($ok, $result) = $this->updateSPARQL($query);

    // everything worked?  
    if (!$ok) {
      foreach ($result as $err) {
        drupal_set_message(t('An error occured while loading the Ontology: ' . serialize($err)),'error');
      }
    } else { // or it worked
      drupal_set_message("Successfully loaded $iri into the Triplestore.");
    }
  
    // look for imported ontologies
    $query = "SELECT DISTINCT ?ont FROM <$iri> WHERE { ?s a owl:Ontology . ?s owl:imports ?ont . }";
    list($ok, $results) = $this->querySPARQL($query);

    // if there was nothing something is weired again.
    if (!$ok) {
      foreach ($results as $err) {
        drupal_set_message(t('Error getting imports of ontology %iri: @e', array('%ont' => $o, '@e' => $err)), 'error');
      }
    } else { // if there are some we have to load them
      foreach ($results as $to_load) {
        $this->addOntologies(strval($to_load->ont));
      }
    }

    // load the ontology info in internal parameters    
    // $this->loadOntologyInfo();
    
    // add namespaces to table
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

    list($ok, $results) = $this->querySPARQL($query);
    
    if (!$ok) {
      foreach ($results as $err) {
        drupal_set_message(t('Error getting imports of ontology %iri: @e', array('%ont' => $o, '@e' => $err)), 'error');
      }
    }
    
    return $results;   
  }
  
  public function deleteOntology($graph, $type = "graph") {

    // get ontology and version uri
    if($type == "graph") {
      $query = "WITH <$graph> DELETE { ?s ?p ?o } WHERE { ?s ?p ?o }";
    } else
      $query = "DELETE { ?s ?p ?o } WHERE { ?s ?p ?o . FILTER ( STRSTARTS(STR(?s), '$graph')) }";

    list($ok, $results) = $this->updateSPARQL($query);
    
    if (!$ok) {
      // some useful error message :P~
      drupal_set_message('some error encountered:' . serialize($results), 'error');
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
