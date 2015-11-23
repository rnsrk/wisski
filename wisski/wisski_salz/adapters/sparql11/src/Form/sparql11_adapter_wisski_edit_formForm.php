<?php
/**
 * @file
 * Contains \Drupal\wisski_salz\Form\wisski_salzForm
 *
 */
 
namespace Drupal\sparql11_adapter\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Implements wisski_salz_view_installed_store_instances
 */
 
class sparql11_adapter_wisski_edit_formForm extends FormBase {
  
  
  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'wisski_salz_view_installed_store_instancesForm';
  }
  
  // this new parameter thing is crazy... you just give it a name
  // and tell it in the routing to use that name and hush there it is
  // I just don't get it... it is so magical ;D
  public function buildForm(array $form, FormStateInterface $form_state, $store_type_name = NULL, $store_name = NULL) {
#    drupal_set_message(serialize($form));
#    drupal_set_message(serialize($form_state));
#    drupal_set_message(serialize($store_type_name));
    return sparql11_adapter_wisski_settings_page($store_name, FALSE);
  }
  
  public function validateForm(array &$form, FormStateInterface $form_state) {
  
  }
  
  public function submitForm(array &$form, FormStateInterface $form_state) {

#    drupal_set_message(serialize($form_state) . 'sdfdsf');
#    drupal_set_message(serialize($form_state->getBuildInfo()));
    
    $buildinfo = $form_state->getBuildInfo();
    // args[1] is the store name
    $args = $buildinfo['args'];

    $label = $form_state->getValue('name');
    $name = preg_replace('/[^a-z0-9_]/u','',strtolower($label));
    $form_state->setRedirectUrl(new Url('wisski_salz.admin_config_wisski_salz'));
    $settings = array(
      'old_name' => $args[1],
      'name' => $name,
      'label' => $label,
      'query_endpoint' => $form_state->getValue('query_endpoint'),
      'update_endpoint' => $form_state->getValue('update_endpoint'),
      'update_interval' => $form_state->getValue('update_interval'),
     // 'local_data' => $form_state['values']['local_data'],
    );
    sparql11_adapter_db_insert_settings($settings, FALSE);
    drupal_set_message("Changed settings.");
    $installed_store_instances = sparql11_adapter_wisski_get_store_instances();  

  }

  
}
