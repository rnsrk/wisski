<?php
/**
 * @file 
 * Contains \Drupal\wisski_salz\Form\wisski_salz_welcome_pageForm
 *
 */
    
namespace Drupal\wisski_salz\Form;
    
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
    
/**
 * Implements the form for the wisski_salz_welcome_page
 * which provides an overview of the WissKI module.
 * The url of the form is '/admin/config/wisski'
 */
        
class wisski_salz_welcome_pageForm extends FormBase {
        
        
 /**
  * {@inheritdoc}.
  */
  public function getFormId() {
    return 'wisski_salz_welcome_page_form';
  }
                        
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = array();
    $output = t("Welcome to the WissKI-Module. This configuration menu is separated in the following parts:<br>");
    $output .= t("- Authority: read and delete current global name authorities. Currently only SKOS is supported.<br>");
    $output .= t("- Graph-Drawing: set the settings for your installation of Graph-Viz to support drawing of graphs.<br>");
    $output .= t("- Ontology: read or delete the base ontology for your project.<br>");
    return $output;              
  }
                                
  public function validateForm(array &$form, FormStateInterface $form_state) {                          
  }
                                    
  public function submitForm(array &$form, FormStateInterface $form_state) {
    drupal_set_message("muahah");
  }
                                            
                                            
}
                                            