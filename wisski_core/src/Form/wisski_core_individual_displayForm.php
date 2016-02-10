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
 * Form for specifying entity display configuration
 *
 * @return form
 *   Form for entity display configuration
 *
 */

class wisski_core_individual_displayForm extends FormBase {
                                       
  /**
   * {@inheritdoc}.
   * The Id of every WissKI form is the name of the form class except that
   * 'Form' is added with '_form'
   */   
  public function getFormId() {
    return 'wisski_core_individual_display_form';
  }
                                                     
  public function buildForm(array $form, FormStateInterface $form_state) {
    $wisski_core_config = \Drupal::service('config.factory')
                                  ->getEditable('wisski_core.settings');   
    $form['max_entities_per_page'] = array(
      '#type' => 'textfield',
      # In d8 variable_get/set/del API is now removed, so you have to use the new configuration system API/storage      
      #'#default_value' => variable_get('wisski_max_entities_per_page',20),
      '#default_value' => $wisski_core_config->get('wisski_max_entities_per_page'),      
      '#description' => t('Maximum number of entities that are shown per listing page'),
    );

    // Add some buttons.
    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Set'),
      '#weight' => 40,
    );
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
 
  }
   
  public function submitForm(array &$form, FormStateInterface $form_state) {
   # variable_set('wisski_max_entities_per_page',(int)$form_state['values']['max_entites_per_page']);
   # In d8 the newly added FormStateInterface is used instead of an array for $form_state,
   # look at https://www.drupal.org/node/2310411 for more details       
   $wisski_core_config = \Drupal::service('config.factory')
                          ->getEditable('wisski_core.settings');                                   
   $wisski_core_config->set('wisski_max_entities_per_page',(int)$form_state->getValue('max_entities_per_page'));
   $wisski_core_config->save();
   drupal_flush_all_caches();      
  }
             
}                                                                                                                                                                                                                                                                          
