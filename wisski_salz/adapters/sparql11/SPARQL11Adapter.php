<?php

module_load_include('php', 'wisski_salz', "interface/AdapterInterface");


class SPARQL11Adapter implements AdapterInterface {


  /**
  * The following settings are currently supported:
  *
  * query_endpoint: The URL to connect to for read operations
  * update_endpoint: The URL to connect to for write operations
  */
  private $settings = array();
  
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

  }


  public function getSettings($name = NULL) {
    drupal_set_message("\$this in getSettings: " . serialize($this));
    if ($name === NULL) return $this->settings;
    return $this->settings[$name];
  }

/* verschoben nach sparql11_adapter.module
  public function settings_page($store) {
    $form['query_endpoint'] = array(
      '#type' => 'textfield',
      '#title' => t('Query Endpoint'),
      '#default_value' => isset($store->settings['query_endpoint']) ? $store->settings['query_endpoint'] : '',
    );
    $form['update_endpoint'] = array(
      '#type' => 'textfield',
      '#title' => t('Update Endpoint'),
      '#default_value' => isset($store->settings['update_endpoint']) ? $store->settings['update_endpoint'] : '',
    );
    $form['ontologies_loaded'] = array(
      '#type' => 'fieldset',
      '#title' => t('Loaded ontologies'),
      '#collapsible' => TRUE,
    );

     $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
      '#submit' => array('wisski_salz_save_store')
    );

    $i = 0;
    if(!empty($store->settings['ontologies_loaded'])) {
        foreach ($store->settings['ontologies_loaded'] as $ont) {
          $i++;
        $form['ontologies_loaded']["ont$i"] = array(
          '#type' => 'markup',
          '#value' => ''
        );
      }
    }

    return $form;

  }


public function sparql11_form_submit($form, &$form_state){
 //drupal_set_message("hallo welt " . serialize($form_state));
 drupal_set_message("\$this: " . serialize($this));

 sparql11_adapter_wisski_add_store_instances($this);

 $store_instances = sparql11_adapter_wisski_get_store_instances();
 drupal_set_message("\$store_instances: " . serialize($store_instances));

 menu_rebuild();

 foreach($store_instances as $key => $store_instance) {
  $form_state['redirect'] = 'admin/config/wisski/salz/' . arg(4) . '/' . $key;
  drupal_set_message("\$key: " . serialize($key));
 }

 $varname1 = "sparql11_query_endpoint_" . $key;
 $varname2 = "sparql11_update_endpoint_" . $key;
 variable_set($varname1, $form_state['values']['query_endpoint']);
 variable_set($varname2, $form_state['values']['update_endpoint']);
}


public function sparql11_edit_form($form, &$form_state){
    $this->setSettings('query_endpoint', variable_get("sparql11_query_endpoint_" . arg(5)));
    $this->setSettings('update_endpoint', variable_get("sparql11_update_endpoint_" . arg(5)));

    return $this->settings_page();
}
*/

  public function pb_definition_settings_page($path_steps = array()) {

  }


  public function query($path_definition, $subject = NULL, $disamb = array(), $value = NULL) {

  }

  
  public function querySPARQL($query) {
    return $this->request('query',$query);
  }


  public function updateSPARQL($update) {
    return $this->request('update',$update);
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
  private function request($type,$query = NULL) {
    
    $ok = FALSE;
    $results = array();
    try {
      if (easyrdf()) {
          if (isset($this->settings['query_endpoint'])) {
            if (isset($this->settings['update_endpoint'])) {
              $sparql = new EasyRdf_Sparql_Client($this->settings['query_endpoint'],$this->settings['update_endpoint']);
            } else {
              $sparql = new EasyRdf_Sparql_Client($this->settings['query_endpoint']);
            }
            if ($type == 'query' && !is_null($query)) {
              $results = $sparql->query($query);
              $ok = TRUE;
            } elseif ($type == 'update'  && !is_null($query)) {
              $results = $sparql->update($query);
              $ok = TRUE;
            }
          }
      } else drupal_set_message("EasyRdf is not installed");
    } catch (Exception $e) {
        drupal_set_message("SPARQL1.1 $type request failed.<br>Query was '".htmlentities($query)."'<br>Error Message:<br>".get_class($e)."<br>".$e->getMessage());
//        throw $e;
    }
    drupal_set_message("SPARQL1.1 $type request successfull.<br>Query was '".htmlentities($query)."'");
    return array($ok,$results);
  }

  public function test() {
 
    drupal_set_message("Running SPARQL test");
/*
//    $this->settings['ontologies_pending'][] = $this->settings['query_endpoint'];
//    $this->addOntologies();
    list($ok,$results) = $this->updateSPARQL('INSERT {?ont rdf:type_ins owl:Ontology} WHERE {?ont rdf:type owl:Ontology}');
//    list($ok,$results) = $this->querySPARQL('SELECT * WHERE {?s (rdfs:subClassOf)+ owl:Thing.} LIMIT 100');
    if ($ok) {
      drupal_set_message($results->dump());
    } else {
      throw new Exception("Test failed");
    }
*/  
    do {
      $num_rows = db_select('wisski_salz_ontologies','ont')->fields('ont')->countQuery()->execute()->fetchField();
      db_delete('wisski_salz_ontologies')->execute();
    } while ($num_rows > 0);
    db_truncate('wisski_salz_ontologies');
    
    list($ok,$result) = $this->querySPARQL('SELECT * WHERE {?o a owl:Ontology.} GROUP BY ?o');
    if ($ok) {
      foreach ($result as $ont) {
        db_insert('wisski_salz_ontologies')->fields(array('sid' => $this->settings['sid'],'iri' => $ont->o, 'pending' => 1))->execute();
      }
    }
    $this->addOntologies();
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

  private function addOntologies($iri = NULL) {
    
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
      $query = "SELECT DISTINCT ?ont ?iri ?ver FROM $tmpgraph WHERE { ?ont a owl:Ontology . OPTIONAL { ?ont owl:ontologyIRI ?iri. ?ont owl:versionIRI ?ver . } }";
      list($ok, $results) = $this->querySPARQL($query);

      if (!$ok) {
        foreach ($results as $err) {
          drupal_set_message(t('Error importing ontology %iri from %ont: @e', array('%ont' => $o, '@e' => $err)), 'error');
        }
        continue;
      }

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

  public function getExternalLinkURL($uri) {
    //TODO  
  }
  
}
