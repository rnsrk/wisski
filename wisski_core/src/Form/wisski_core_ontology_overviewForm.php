<?php
/**
 * @file
 *
 */
   
namespace Drupal\wisski_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
   
   
/**
 * Overview form for ontology handling
 *
 * @return form
 *   Form for the ontology handling menu
 * @author Mark Fichtner
 */
class wisski_core_ontology_overviewForm extends FormBase {
  
  /**
   * {@inheritdoc}.
   * The Id of every WissKI form is the name of the form class except that
   * 'Form' is added with '_form'
   */
  public function getFormId() {
    return 'wisski_core_ontology_overview_form';
  }
                        
  public function buildForm(array $form, FormStateInterface $form_state) {
    //drupal_set_message("muahah");
    
/*    $form['my_text_field'] = array(
      '#type' => 'textfield',
      '#title' => 'Example',
    );
    return $form;                          
 */ 
  
    $form = array();
    // in wisski d8 there will be no local stores anymore, 
    // we assume that every store could load an ontology
    // we load all store entities and 
    // have to choose for which store we want to load an ontology     
    #$local_store = wisski_salz_invoke_local_store();
    $adapters = \Drupal\wisski_salz\Entity\Adapter::loadMultiple();      
    #drupal_set_message(serialize($adapters));
    #dpm($adapters);
    $adapterlist = array();
     
    // create a list of all adapters to choose from
    foreach($adapters as $adapter) {
      // if an adapter is not writable, it should not be allowed to load an ontology for that store 
      if($adapter->getEngine()->isWritable()){
        
        $adapterlist[$adapter->id()] = $adapter->label();
      }  
    }
    #drupal_set_message(serialize($adapterlist));                   
    // we have to rewrite the functions of local_store, 
    // look at wisski d7 wisski_salz/adapters/sparql11/SPARQL11Adapter.php for more details
    // old d7 form can be found in wisski_core/wisski_core.admin.inc   
    
   
    // check if there is a selected store
    $selected_store = "";
   # if(empty($form_state->getValue('select_store'))) {
   # if(empty($selected_store)) {
      // give out a message that there is no local store currently - the user should select one
    $form['nothing_here'] = array(
      '#type' => 'item',
      '#markup' => '<b>No store is specified currently.</b><br/> Please select a store below.',
    );
                                                
    $selected_store = !empty($form_state->getValue('select_store')) ? $form_state->getValue('select_store') : "0";
    drupal_set_message('selected: ' . $selected_store); 
      
    $form['select_store'] = array(
      '#type' => 'select',
      '#title' => $this->t('Select the store for which you want to load an ontology.'),
      '#default_value' => $selected_store,
      '#options' => array_merge(array("0" => 'Please select.'), $adapterlist),
      '#ajax' => array(
        'callback' => 'Drupal\wisski_core\Form\wisski_core_ontology_overviewForm::ajaxStores',
        'wrapper' => 'select_store_div',
        'event' => 'change',
        #'effect' => 'slide',
           
      ),                                               
    );  
      
    $form['stores'] = array(
      '#type' => 'markup',
      // The prefix/suffix provide the div that we're replacing, named by
      // #ajax['wrapper'] below.
      '#prefix' => '<div id="select_store_div">',
      '#suffix' => '</div>',
      '#value' => "",
    );
      
    /*  for ($i = 0; $i <= $selected_store; $i++) {
        $form['stores']["checkbox$i"] = array(
          '#type' => 'item',
          '#markup' => "Hello $selected_store",
         # '#title' => "Load ontology for $i",
        );
      }
    */                         
   # return $form;
    
    if(!empty($form_state->getValue('select_store'))) {
 # } else {
       
     // if there is a selected store - check if there is an ontology in the store
        
      $selected_id = $form_state->getValue('select_store');
      $selected_name= $adapterlist[$selected_id];
      // load the store adapter entity object by means of the id of the selected store
      $selected_adapter = \Drupal\wisski_salz\Entity\Adapter::load($selected_id);      
      drupal_set_message('Current selected adapter: ' . serialize($selected_adapter));
      // load the engine of the adapter
      $engine = $selected_adapter->getEngine();
      #drupal_set_message('hello engine ' . $engine->getPluginId());
 
       
      // if the engine is of type sparql11_with_pb we can load the existing ontologies 
      if($engine->getPluginId() === 'sparql11_with_pb' ) {
       
        #drupal_set_message('Engine: ' . serialize($engine));
        #drupal_set_message('Type: ' . $engine->getPluginId());
        $infos = $engine->getOntologies();
        #drupal_set_message(serialize($infos));
        dpm($infos);
      
        // there already is an ontology
        if(!empty($infos) && count($infos) > 0 ) {
          #drupal_set_message('!!!');
          $form['stores']['header'] = array(
            '#type' => 'item',
            '#markup' => '<b>Currently loaded Ontology:</b><br/>',
          );
          
          $table = "<table><tr><th>Name</th><th>Iri</th><th>Version</th><th>Graph</th></tr>";
          foreach($infos as $ont) {
           // $table .= "<tr><td>" . $ont->ont . "</td><td>" . $ont->iri . "</td><td>" . $ont->ver . "</td><td>" . $ont->graph . "</td></tr>";
          $table .= "<tr><td>" . $ont->ont . "</td><td>" . $ont->iri . "</td><td>" . $ont->ver . "</td><td>" . $ont->graph . "</td></tr>";
          drupal_set_message('ont ont: ' . $ont->ont);
          dpm($ont);          
          drupal_set_message('ont ' . serialize($ont));
          }
          
          $table .= "</table>";
                                                                          
                                         
          $form['stores']['table'] = array(
            '#type' => 'item',
            '#markup' => $table,
          );
                                                                                                                                    
          $form['stores']['delete_ont'] = array(
            '#type' => 'submit',
            '#name' => 'Delete Ontology',
            '#value' => 'Delete Ontology',
            '#submit' => array('::deleteOntology'),
          );
        
        
        } else {
          // No ontology was found
          $form['stores']['load_onto'] = array(
            '#type' => 'textfield',
            '#title' => "Load Ontology for store <em> $selected_name </em>:",
            '#description' => 'Please give the URL to a loadable ontology.',
          );
                                                     
          $form['stores']['load_onto_submit'] = array(
            '#type' => 'submit',
            '#name' => 'Load Ontology',
            '#value' => 'Load Ontology',
           # '#submit' => array('wisski_core_load_ontology'),
          );
        }
                                                                                                             
      
                                                                                                                           
      }
    } 
        
 /*                            
       # if(stristr($selected_store->getPluginId(), 'sparql') !== FALSE) {
                       
               $infos = $engine->getOntologies();
                drupal_set_message(serialize($infos));
                // there already is an ontology
                if(!empty($infos) && count($infos) > 0 ) {
                $form['header'] = array(
                '#type' => 'item',
                '#markup' => '<b>Currently loaded Ontology:</b><br/>',
                );
                                                        
                $table = "<table><tr><th>Name</th><th>Iri</th><th>Version</th><th>Graph</th></tr>";
                foreach($infos as $ont) {
                 $table .= "<tr><td>" . $ont->ont . "</td><td>" . $ont->iri . "</td><td>" . $ont->ver . "</td><td>" . $ont->graph . "</td></tr>";
                }
                $table .= "</table>";
                                                                                                                    
                                                                                                                 
                $form['table'] = array(
                '#type' => 'item',
                '#markup' => $table,
                );
                                                                                                                                                   
               $form['delete_ont'] = array(
               '#type' => 'submit',
               '#name' => 'Delete Ontology',
                '#value' => 'Delete Ontology',
                '#submit' => array('wisski_core_delete_ontology'),
                );
                                                                                                                                                                                                                                    
    #  $form['stores']['neu'] = array(
     #   '#type' => 'item',
      #  '#markup' => "Hi $selected_store $selected_name!",
    #  );
     
     }  else { 
      // No ontology was found      
       $form['stores']['load_onto'] = array(
         '#type' => 'textfield',
         '#title' => "Load Ontology for store <em> $selected_name </em>:",
         '#description' => 'Please give the URL to a loadable ontology.',
       );
                                          
       $form['stores']['load_onto_submit'] = array(
         '#type' => 'submit',
         '#name' => 'Load Ontology',
         '#value' => 'Load Ontology',
         '#submit' => array('wisski_core_load_ontology'),
       );
    }
    
  */
        
  #   $engine = $selected_adapter->getEngine();
     
   #        drupal_set_message('Engine: ' . serialize($engine));
    #             drupal_set_message('Type: ' . $engine->getPluginId());
                     
    #    $infostest = $engine->getOntologies();
    #    drupal_set_message(serialize($infostest));
        
                                                                                      
                                                                  
   #   $form['actions']['submit'] = array(
    #    '#type' => 'submit',
    #    '#value' => t('Save'),
    #  );
                             
                                                                    
      // stop here
                                                       
 /*                                                                                    
   $ns = "";                                                                                                                                                                          
   #$ns = $local_store->getNamespaces();
                                                                                   
   $table = "<table><tr><th>Short Name</th><th>URI</th></tr>";
   foreach($ns as $key => $value) {
   $table .= "<tr><td>" . $key . "</td><td>" . $value . "</td></tr>";
   }
   $table .= "</table>";
                                                                                            
   $form['ns_table'] = array(
     '#type' => 'item',
     '#markup' => $table,
   );
 */                                                                                                                 
   return $form;
   
  }
  
  
  public function ajaxStores(array $form, array $form_state) {
    return $form['stores'];
  }
   

  public function validateForm(array &$form, FormStateInterface $form_state) {
    #drupal_set_message('hello');
  }
   
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // if there is a selected store - check if there is an ontology in the store
    $selected_id = $form_state->getValue('select_store');
    $selected_name= $adapterlist[$selected_id];
    // load the store adapter entity object by means of the id of the selected store
    $selected_adapter = \Drupal\wisski_salz\Entity\Adapter::load($selected_id);
    // load the engine of the adapter
    $engine = $selected_adapter->getEngine();
    #drupal_set_message('hello submit engine ' . $engine->getPluginId());
                                                                                              
    // if the engine is of type sparql11_with_pb we can load the existing ontologies
    if($engine->getPluginId() === 'sparql11_with_pb' ) {  
      $infos = $engine->getOntologies();
      #drupal_set_message('infos in submit' . serialize($infos));                                                                                                    
      $engine->addOntologies($form_state->getValue('load_onto'));
    }                        
   return;

  }
  
  public function deleteOntology(array &$form, FormStateInterface $form_state) {
    $selected_id = $form_state->getValue('select_store');
    $selected_name= $adapterlist[$selected_id];
    // load the store adapter entity object by means of the id of the selected store
    $selected_adapter = \Drupal\wisski_salz\Entity\Adapter::load($selected_id);
    // load the engine of the adapter
    $engine = $selected_adapter->getEngine();
    #drupal_set_message('hello engine ' . $engine->getPluginId());
                                                      
    // if the engine is of type sparql11_with_pb we can load the existing ontologies
    if($engine->getPluginId() === 'sparql11_with_pb' ) {                              
      $infos = $engine->getOntologies();
      
      // there already is an ontology and we want to delete it
       if(!empty($infos)) {
         foreach($infos as $ont) {
           if(strval($ont->graph) != "default"){
             $engine->deleteOntology(strval($ont->graph));
             drupal_set_message('Successfully deleted ontology ' . $ont->graph);
           } else {
             $engine->deleteOntology(strval($ont->ont), 'no-graph');
             drupal_set_message('Successfully deleted ontology ' . $ont->ont);
           }
         }
       }
                                                               
                                                    
    }
    
  }
             
}                                                                                                                                                                                                                                                                          
