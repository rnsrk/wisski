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
 * Form for configuring Linked Open Data (LOD) behaviour and URI management
 *
 * @return form
 *   Form for the LOD and URI configuration
 * 
 */
class wisski_core_admin_lod_and_uriForm extends FormBase {

  /**
   * {@inheritdoc}.
   * The Id of every WissKI form is the name of the form class except that
   * 'Form' is added with '_form'
   */
  public function getFormId() {
    return 'wisski_core_admin_lod_and_uri_form';
  }
                        
  public function buildForm(array $form, FormStateInterface $form_state) {  
      global $base_url;

      $form['uri'] = array(
        '#type' => 'fieldset',
        '#title' => t('URI generation'),
      );
      $wisski_core_config = \Drupal::service('config.factory')
                               ->getEditable('wisski_core.settings');                         
      # in d8 variable_get/set/del API is now removed, so you have to use the new configuration system API/storage 
      #$templates = variable_get('wisski_core_lod_uri_templates', array('' => $base_url . '/inst/%{bundle}/%{hash}'));
      $templates = $wisski_core_config->set('wisski_core_lod_uri_templates', $base_url . '/inst/%{bundle}/%{hash}'); 
      # drupal_set_message(serialize($templates));
      $form['uri']['template'] = array(
        '#type' => 'textfield',
        '#title' => t('Template'),
        # in d8 function check_plain() is deprecated respectively undefined! Use '#plain_text' instead.  
        #'#field_prefix' => check_plain($base_url . '/'),  
        '#field_prefix' => array('#plain_text' => $base_url . '/'),        
      #  '#default_value' => substr($templates[''], strlen($base_url) + 1),
        '#default_value' => substr($wisski_core_config->get('wisski_core_lod_uri_templates'), strlen($base_url) + 1),      
        '#description' => t('<p>The URI will be registered so that a LOD-conforming HTTP redirect can be performed. ' . 
                            'The user must make sure that the URI path does not interfere with any Drupal-related paths.</p>' . 
                            '<p>You may use placeholders of the form "%pl1" or "%pl2". The following placeholders may be used:<dl>!l</dl></p>',
                            array(
                              '%pl1' => "%{placeholder}",
                              '%pl2' => "%{placeholder}{append if empty}{append if not empty}",
                              '!l' => join('', array(
          '<dt>hash</dt><dd>A randomly generated md5 hash</dd>',
          '<dt>number</dt><dd>A number, starting from 1, that will be incremented until the URI does not already exist</dd>',
          '<dt>bundle</dt><dd>The name of the entity bundle/class with non-alphanumeric chars replaced and collapsed to "_"</dd>',
          '<dt>title</dt><dd>The title of the entity with non-alphanumeric chars replaced and collapsed to "_"</dd>',
        )))),
      );
  
  // Add some buttons.
  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save configuration'),
    '#weight' => 40,
  );
  return $form;
 }


  public function validateForm(array &$form, FormStateInterface $form_state) {
 
  }
   
  public function submitForm(array &$form, FormStateInterface $form_state) {
    drupal_set_message("muahah");

    global $base_url;
    $wisski_core_config = \Drupal::service('config.factory')
                              ->getEditable('wisski_core.settings');
    # In d8 variable_get/set/del API is now removed, so you have to use the new configuration system API/storage, see https://www.drupal.org/node/1809490                                     
    # variable_set('wisski_core_lod_uri_templates', array('' => $base_url . '/' . $form_state['values']['template']));
    
    # In d8 the newly added FormStateInterface is used instead of an array for $form_state,
    # use the appropriate function instead, in this case $form_state->getValue(). 
    # Look at https://www.drupal.org/node/2310411 for more details
    $wisski_core_config->set('wisski_core_lod_uri_templates', array('' => $base_url .  '/' . $form_state->getValue('template')));
    $wisski_core_config->save();     
  }
}

