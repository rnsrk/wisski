<?php
/**
 * @file
 *
 */
 
namespace Drupal\sparql11_adapter\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Implements wisski_salz_edit_formForm which
 * enables the user to edit a store adapting the following settings:
 *  - name of the store
 *  - query endpoint
 *  - update endpoint
 *  - update interval
 * The url of the form is '/admin/config/wisski/salz/edit/{store_type_name}/{store_name}'
 */
        
 */
 
class sparql11_adapter_wisski_edit_formForm extends FormBase {
  
  
  /**
   * The Id of every WissKI form is the name of the form class except that 
   * 'Form' is added with '_form'
   */
  public function getFormId() {
    return 'sparql11_adapter_wisski_edit_form_form';
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

   # drupal_set_message(serialize($form_state) . 'sdfdsf');
   # drupal_set_message(serialize($form_state->getBuildInfo()));
    
    $buildinfo = $form_state->getBuildInfo();
    // args[1] is the store name
    $args = $buildinfo['args'];
    // args[0] is the store type name 
    $store_type_name = $args[0];

    $label = $form_state->getValue('name');
    $name = preg_replace('/[^a-z0-9_]/u','',strtolower($label));
    #$form_state->setRedirectUrl(new Url('wisski_salz.admin_config_wisski_salz'));
    
    // if you want to redirect to a path pattern like '/admin/config/wisski/salz/edit/{store_type_name}/{store_name}', 
    // e.g. to a path like '/admin/config/wisski/salz/edit/sparql11/test',
    // as it is defined in the sparql11_adapter.routing.yml,
    // you have to set the mandatory route parameters - as they are defined with {} in the mymodule.routing.yml (e.g. {store_name})- explicitly!!! 
    // Otherwise Symfony will throw an exception like the following:
    //   Uncaught PHP Exception Symfony\\Component\\Routing\\Exception\\MissingMandatoryParametersException: 
    //   "Some mandatory parameters are missing ("store_type_name", "store_name") to generate a URL for route 
    //   "sparql11_adapter.admin_config_wisski_salz_edit_storetypename_storename"." at 
    //   /srv/www/htdocs/dev/core/lib/Drupal/Core/Routing/UrlGenerator.php line 177, referer: http://fiz.gnm.de/dev/admin/config/wisski/salz/edit/sparql11/test
    // It seems this error comes up not when matching a URL to a route, but when generating a URL from a route.
    //
    // To set the route parameters, you have to use the function setRouteParameters with your Url::fromRoute url
    $url = \Drupal\Core\Url::fromRoute('sparql11_adapter.admin_config_wisski_salz_edit_storetypename_storename')
              ->setRouteParameters(array('store_type_name'=>$store_type_name,'store_name'=>$name));
    #drupal_set_message($url);
    #$urlstring = $url->toString(); 
    #drupal_set_message($urlstring);          
    $form_state->setRedirectUrl($url);
    //drupal_set_message(serialize($form_state->getBuildInfo()));
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
