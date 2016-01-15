<?php
/**
 * @file
 *
 */
    
namespace Drupal\wisski_salz\Form;
    
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
    

/**
 * Defines a confirmation form for deleting wisski_salz store data.
 */     
class wisski_salz_deleteForm extends ConfirmFormBase {
         
         
  /**
   * {@inheritdoc}.
   * The Id of every WissKI form is the name of the form class except that
   * 'Form' is added with '_form'     
   */
  public function getFormId() {
    return 'wisski_salz_delete_form';
  }
  
  /**
    * The store_type_name and store_name of the store to delete.
    *
    * @var string
    */
  protected $store_type_name;
  protected $store_name;
               
  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Do you want to delete %store_type_name store %store_name?', array('%store_type_name' => $this->store_type_name, '%store_name' => $this->store_name));
  }  

 /**
  * {@inheritdoc}
  */
  public function getCancelUrl() {
     $url = \Drupal\Core\Url::fromRoute('sparql11_adapter.admin_config_wisski_salz_edit_storetypename_storename')
              ->setRouteParameters(array('store_type_name'=>$this->store_type_name,'store_name'=>$this->store_name)); 
     #return new Url($url);
     return $url; 
  }
                   
 /**
  * {@inheritdoc}
  */
  public function getDescription() {
     return t('Only do this if you are sure!');
  }  

/**
 * {@inheritdoc}
 */
  public function getConfirmText() {
    return t('Delete');
  }
                
/**
 * {@inheritdoc}
 */
  public function getCancelText() {
    return t('Cancel');
  }

/**
 * {@inheritdoc}
 *
 * @param int $id
 *   (optional) The ID of the item to be deleted.
 */                                                           
  public function buildForm(array $form, FormStateInterface $form_state, $store_type_name = NULL, $store_name = NULL) {
    #return wisski_salz_view_installed_store_instances($form, $form_state);
    $this->store_type_name = $store_type_name;
    $this->store_name = $store_name;
    return parent::buildForm($form, $form_state);
  }
                                 
  public function validateForm(array &$form, FormStateInterface $form_state) {
                            
  }
                                     
  public function submitForm(array &$form, FormStateInterface $form_state) {      
    $buildinfo = $form_state->getBuildInfo();
    #drupal_set_message(serialize($form_state->getBuildInfo()));
    
    // args[1] is the store name
    $args = $buildinfo['args'];
    $store_name = $args[1];            
    
    $result = wisski_salz_delete_store_instances($store_name);
      if ($result) {
        drupal_set_message("The store $store_name was successfully deleted.");
      }      
     $form_state->setRedirectUrl(new Url('wisski_salz.admin_config_wisski_salz'));
       
                             
  }
                                       
                                             
}
                                             