<?php

module_load_include('php', 'wisski_salz', "interface/AdapterInterface");


class SPARQL10Adapter implements AdapterInterface {


  /**
  * The following settings are currently supported:
  *
  * query_endpoint: The URL to connect to for read operations
  * update_endpoint: The URL to connect to for write operations
  */
  private $settings = array();

  public function getName() {
    return get_class($this);
  }

public function getType() {
    return "SPARQL 1.0";
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

/* verschoben nach sparql10_adapter.module
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


public function sparql10_form_submit($form, &$form_state){
 //drupal_set_message("hallo welt " . serialize($form_state));
 drupal_set_message("\$this: " . serialize($this));

 sparql10_adapter_wisski_add_store_instances($this);

 $store_instances = sparql11_adapter_wisski_get_store_instances();
 drupal_set_message("\$store_instances: " . serialize($store_instances));

 menu_rebuild();

 foreach($store_instances as $key => $store_instance) {
  $form_state['redirect'] = 'admin/config/wisski/salz/' . arg(4) . '/' . $key;
  drupal_set_message("\$key: " . serialize($key));
 }

 $varname1 = "sparql10_query_endpoint_" . $key;
 $varname2 = "sparql10_update_endpoint_" . $key;
 variable_set($varname1, $form_state['values']['query_endpoint']);
 variable_set($varname2, $form_state['values']['update_endpoint']);
}


public function sparql10_edit_form($form, &$form_state){
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
