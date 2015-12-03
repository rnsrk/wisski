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
 * Implements the form for the wisski_salz_config_page 
 * which enables you to choose from a list the type of store to add.
 * The url of the form is '/admin/config/wisski/salz/add'
 */
 
class wisski_salz_config_pageForm extends FormBase {
  
  
  /**
   * {@inheritdoc}.
   * The Id of every WissKI form is the name of the form class except that
   * 'Form' is added with '_form'    
   */
  public function getFormId() {
    return 'wisski_salz_config_page_form';
  }
  
  public function buildForm(array $form, FormStateInterface $form_state) {
    return wisski_salz_config_page();
  }
  
  public function validateForm(array &$form, FormStateInterface $form_state) {
  
  }
  
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  
}
