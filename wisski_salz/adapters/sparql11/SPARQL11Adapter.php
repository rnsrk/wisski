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
   // $this->putNamespace('ecrm',  'http://erlangen-crm.org/120111/');
    $this->putNamespace('behaim_inst', 'http://faui8184.informatik.uni-erlangen.de/birkmaier/content/');
    $this->putNamespace('behaim', 'http://wwwdh.cs.fau.de/behaim/voc/');
    $this->putNamespace('behaim_image', 'http://faui8184.informatik.uni-erlangen.de/behaim/ontology/images/');
   
    $this->putNamespace('ecrm',  'http://erlangen-crm.org/140617/');
    $this->putNamespace('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
    $this->putNamespace('swrl', 'http://www.w3.org/2003/11/swrl#');
    $this->putNamespace('protege', 'http://protege.stanford.edu/plugins/owl/protege#');
    $this->putNamespace('xsp', 'http://www.owl-ontologies.com/2005/08/07/xsp.owl#');
    $this->putNamespace('owl', 'http://www.w3.org/2002/07/owl#');
    $this->putNamespace('xsd', 'http://www.w3.org/2001/XMLSchema#');
    $this->putNamespace('swrlb', 'http://www.w3.org/2003/11/swrlb#');
    $this->putNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
    //$this->putNamespace('skos', 'http://www.w3.org/2004/02/skos/core#');
    
    
    
    
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

  public function nextClasses($property) {
  
    list($ok,$result) = $this->querySPARQL(
      "SELECT DISTINCT *"
      ."WHERE {"
        ."$property a owl:ObjectProperty. "
        ."$property rdfs:range/rdfs:subClassOf* ?class. "
        ."?class a owl:Class. "
      ."}"
    );
    if ($ok) {
      $output = array();
      foreach ($result as $obj) {
        $output[] = $obj->class->dumpValue('text');
      }
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
        ."?property rdfs:domain/(^rdfs:subClassOf)* $class. "
      ."}"
    );
    if ($ok) {
      $output = array();
      foreach ($result as $obj) {
        $output[] = $obj->property->dumpValue('text');
      }
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
        if ($obj->type->dumpValue('text') == 'owl:Class') return $this->nextProperties($node);
        if ($obj->type->dumpValue('text') == 'owl:ObjectProperty') return $this->nextClasses($node);
      }
    }
    return array();
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
          $this->updateNamespaces();
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
/*
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
*/

  }

  private function updateNamespaces() {
    $spaces = db_select('wisski_salz_sparql11_ontology_namespaces','ns')
                ->fields('ns')
                ->execute()
                ->fetchAllAssoc('id');
    foreach($spaces as $space) {
      EasyRDF_Namespace::set($space->short_name,$space->long_name);
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
      drupal_set_message('Namespace '.$short_name.' already exists in DB');
    }
  }
  
  private function makeDrupalName($entity,$prefix) {
    $pre_len = strlen($prefix);
    return $prefix.preg_replace('/[^a-z0-9_]/u','_',substr(strtolower($entity),0,32-$pre_len));
  }  
  
  public function createEntitiesForBundle($bundle) {
    
    drupal_set_message("Creating entities for bundle");
    $bundle_label = $bundle->label;
    $now = time();
    $result = array();
    list($ok,$result) 
      = $this->querySPARQL(
        "SELECT DISTINCT ?ind "
        ."WHERE "
        ."{ "
          ."$bundle_label a owl:Class. "
          ."?ind a $bundle_label. "
          ."FILTER NOT EXISTS { "
            ."?n (rdfs:subClassOf)+ $bundle_label. "
            ."?ind a ?n. "
         ."} "
      ."} "
//  	."LIMIT 1 "
    );
    $then = time();
    trigger_error("Query took ".($then-$now)." seconds",E_USER_NOTICE);
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
    $secs = ($then - $now) % 60;
    $mins = ($then - $now - $secs) / 60;
    trigger_error("Rest of Setup took $mins:$secs min",E_USER_NOTICE);
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
    trigger_error("Query took ".($then-$now)." seconds",E_USER_NOTICE);
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
    $secs = ($then - $now) % 60;
    $mins = ($then - $now - $secs) / 60;
    trigger_error("Rest of Setup took $mins:$secs min",E_USER_NOTICE);
  }
  

  public function updateEntityInfo(&$entity) {
  
    $ind_label = $entity->title;
    $entity_name = $entity->name;
    $gather = array();
    $result = array();
    list($ok,$result)
      = $this->querySPARQL(
        "SELECT DISTINCT ?property ?target "
        ."WHERE "
        ."{ "
          ."?class a owl:Class. "
          ."$ind_label a ?class. "
          ."?property a owl:ObjectProperty. "
          ."$ind_label ?property ?target. "
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
          ."$ind_label a ?class. "
          ."?property a owl:DatatypeProperty. "
          ."$ind_label ?property ?data. "
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
  
    $fields = array();
    $instances = array();
    $class_name = $class->type;
    $class_label = $class->label;
    
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
    $this->createEntitiesForBundle($class);
  }

  public function loadClasses() {
   
    //    $inferrer = "/(^rdfs:subClassOf)*"; //add to rdfs:domain
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
    trigger_error("Query took ".($then-$now)." seconds",E_USER_NOTICE);
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
    $secs = ($then - $now) % 60;
    $mins = ($then - $now - $secs) / 60;
    trigger_error("Rest of Setup took $mins:$secs min",E_USER_NOTICE);
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
