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
    drupal_set_message(serialize($adapters));
    
    $adapterlist = array();
     
    // create a list of all adapters to choose from
    foreach($adapters as $adapter) {
      $adapterlist[$adapter->id()] = $adapter->label();
    }
                       
    // we have to rewrite the functions of local_store, 
    // look at wisski d7 wisski_salz/adapters/sparql11/SPARQL11Adapter.php for more details
    // old d7 form can be found in wisski_core/wisski_core.admin.inc   
    
    // check if there is a local store
    $local_store = "";
    if(empty($local_store)) {
      // give out a message that there is no local store currently - the user should select one
      $form['nothing_here'] = array(
        '#type' => 'item',
        '#markup' => '<b>No store is specified currently.</b><br/> Please select a store below.',
      );
      
      $form['stores'] = array(
        '#type' => 'markup',
        // The prefix/suffix provide the div that we're replacing, named by
        // #ajax['wrapper'] below.
        '#prefix' => '<div id="selected_store_div">',
        '#suffix' => '</div>',
        '#value' => "",                      
      );
                                          
      $form['selected_store'] = array(
        '#type' => 'select',
        '#title' => $this->t('Select the store for which you want to load an ontology.'),
      #  '#default_value' => $pathbuilder->getAdapterId(),
        '#options' => $adapterlist,
        '#ajax' => array(
          'callback' => 'Drupal\wisski_pathbuilder\Form\WisskiPathForm::ajaxStores',
          'wrapper' => 'selected_store_div',
          'event' => 'change',
        ),
                                               
      );
      
   #   $form['actions']['submit'] = array(
    #    '#type' => 'submit',
    #    '#value' => t('Save'),
    #  );
                             
                                                                    
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
  
  public function ajaxStores(array $form, array $form_state) {
    return $form['selected_store'];
  }
   

  public function validateForm(array &$form, FormStateInterface $form_state) {
 
  }
   
  public function submitForm(array &$form, FormStateInterface $form_state) {
   # drupal_set_message("muahah");
  }
             
}                                                                                                                                                                                                                                                                          
