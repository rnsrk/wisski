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
        
    $local_store = wisski_salz_invoke_local_store();
          
    // check if there is a local store
    if(empty($local_store)) {
      // give out a message that there is no local store currently - the user should select one
      $form['nothing_here'] = array(
        '#type' => 'item',
        '#markup' => '<b>No local store is specified currently.</b><br/> Please select a local store <a href="salz">here</a>',
      );
                                      
      // stop here
      return $form;
    }
    
   
                                                
    // if there is a local store - check if there is an ontology in the store
                                                  
    if(stristr($local_store->getType(), 'SPARQL') !== FALSE) {
      $infos = $local_store->getOntologies();
                                   
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
    } else {
      // No ontology was found
     
      $form['load_onto'] = array(
        '#type' => 'textfield',
        '#title' => 'Load Ontology:',
        '#description' => 'Please give the URL to a loadable ontology.',
      );
                                          
      $form['delete_ont'] = array(
        '#type' => 'submit',
        '#name' => 'Load Ontology',
        '#value' => 'Load Ontology',
        '#submit' => array('wisski_core_load_ontology'),
      );
                                                                    
      }
   }
                                                                                            
   $ns = $local_store->getNamespaces();
                                                                                   
   $table = "<table><tr><th>Short Name</th><th>URI</th></tr>";
   foreach($ns as $key => $value) {
   $table .= "<tr><td>" . $key . "</td><td>" . $value . "</td></tr>";
   }
   $table .= "</table>";
                                                                                            
   $form['ns_table'] = array(
     '#type' => 'item',
     '#markup' => $table,
   );
                                                                                                                  
   return $form;
   
  }
  

  public function validateForm(array &$form, FormStateInterface $form_state) {
 
  }
   
  public function submitForm(array &$form, FormStateInterface $form_state) {
   # drupal_set_message("muahah");
  }
             
}                                                                                                                                                                                                                                                                          
