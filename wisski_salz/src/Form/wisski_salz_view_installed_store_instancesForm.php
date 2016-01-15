<?php
/**
 * @file
 *
 */
 
namespace Drupal\wisski_salz\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Implements the wisski_salz_view_installed_store_instances form
 * which enables you to add a new store from a list of store types and 
 * provides an overview table of the stores that are already installed.
 * The url of the form is '/admin/config/wisski/salz'
 */
 
class wisski_salz_view_installed_store_instancesForm extends FormBase {
  
  
  /**
   * {@inheritdoc}.
   * The Id of every WissKI form is the name of the form class except that
   * 'Form' is added with '_form'     
   */
  public function getFormId() {
    return 'wisski_salz_view_installed_store_instances_form';
  }
  
  public function buildForm(array $form, FormStateInterface $form_state) {
    return wisski_salz_view_installed_store_instances($form, $form_state);
  }
  
  public function validateForm(array &$form, FormStateInterface $form_state) {
  
  }
  
  public function submitForm(array &$form, FormStateInterface $form_state) {
    drupal_set_message("muahah");
  }

  
}
