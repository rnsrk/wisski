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

  
  public function setSettings($name, $value = NULL) {
    if (is_array($name)) {
      $this->settings = $name;
    } elseif (is_string($name) || is_integer($name)) {
      $this->settings[$name] = $value;
    }

    if ($this->settings['do_ontologies_add']) {
      if (!$this->settings['ontologies_pending']) {
        $this->settings['ontologies_pending'] = $this->settings['do_ontologies_add'];
      } else {
        $this->settings['ontologies_pending'] = array_merge($this->settings['ontologies_pending'], $this->settings['do_ontologies_add']);
      }
      unset($this->settings['do_ontologies_add']);

      $this->addOntologies();
    }

  }


  public function getSettings($name = NULL) {
    if ($name === NULL) return $this->settings;
    return $this->settings[$name];
  }

  
  public function settings_page() {
    $form['query_endpoint'] = array(
      '#type' => 'textfield',
      '#title' => t('Query Endpoint'),
      '#default_value' => isset($this->settings['query_endpoint']) ? $this->settings['query_endpoint'] : '',
    );
    $form['update_endpoint'] = array(
      '#type' => 'textfield',
      '#title' => t('Update Endpoint'),
      '#default_value' => isset($this->settings['update_endpoint']) ? $this->settings['update_endpoint'] : '',
    );
    $form['ontologies_loaded'] = array(
      '#type' => 'fieldset',
      '#title' => t('Loaded ontologies'),
      '#collapsible' => TRUE,
    );
    $i = 0;
    foreach ($this->settings['ontologies_loaded'] as $ont) {
      $i++;
      $form['ontologies_loaded']["ont$i"] = array(
        '#type' => 'markup',
        '#value' => ''
      );



    } 

    return $form;

  }


  public function pb_definition_settings_page($path_steps = array()) {
    
  }


  public function query($path_definition, $subject = NULL, $disamb = array(), $value = NULL) {
    
  }


  public function querySPARQL($query) {
  }


  public function updateSPARQL($update) {
  }



  private function addOntologies() {
    
    global $base_url;
    $tmpgraph = "<$base_url/tmp/wisski/add_ontology>";

    while (isset($this->settings['ontologies_pending']) && !empty($this->settings['ontologies_pending'])) {
      
      $o = array_shift($this->settings['ontologies_pending']);
      
      //quick and easy test if ontology is loaded already. this does not detect all loaded onts
      if (isset($this->settings['ontologies_loaded'][$o])) continue;

      // OWL2 conformant import: support ontology versions and cyclic imports
      // implements CP 1,2 from http://www.w3.org/TR/owl2-syntax/#Ontology_IRI_and_Version_IRI

      // we first load the file into a dummy graph to inspect it...
      list($ok, $errors) = $this->updateSPARQL("DROP $tmpgraph");

      list($ok, $errors) = $this->updateSPARQL("LOAD $o INTO GRAPH $tmpgraph");
      
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

      $result = $results[0];
      
      $iri = isset($result['iri']) ? $result['iri'] : $result['ont'];
      $ver = isset($result['ver']) ? $result['ver'] : '';

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
      
      //import it: move it from temporal grph to ontology iri
      list($ok, $errors) = $this->updateSPARQL("MOVE GRAPH $tmpgraph TO GRAPH <$iri>");
      
      $this->settings['ontologies_loaded'][$iri] = array(
        'iri' => $iri,
        'version' => $ver,
        'source' => $o,
      );
      
      unset($this->settings['ontologies_pending'][$o]);

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
        $this->settings['ontologies_pending'][] = $result['ont'];
      }

    }
  
  }
  





  public function getExternalLinkURL($uri){}



}
