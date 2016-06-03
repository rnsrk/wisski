<?php

namespace Drupal\wisski_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class WisskiDisplaySettingsForm extends FormBase {

  public function getFormId() {

    return 'wisski_core_display_settings_form';
  }
  
  public function buildForm(array $form, FormStateInterface $form_state) {
  
    $settings = $this->configFactory()->getEditable('wisski_core.settings');
    
    $form['#wisski_settings'] = $settings;
    
    $form['pager_max'] = array(
      '#type' => 'number',
      '#default_value' => $settings->get('wisski_max_entities_per_page'),
      '#min' => 1,
      '#max' => 100,
      '#step' => 1,
      '#title' => $this->t('Maximum number of entities displayed per list page'),
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    );
    return $form;
  }
  
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $settings = $form['#wisski_settings'];
    $new_val = $form_state->getValue('pager_max');
    $settings->set('wisski_max_entities_per_page',$new_val)->save();
    drupal_set_message($this->t('Set maximum number of displayed WissKI Entities to %num.',array('%num'=>$new_val)));
    $form_state->setRedirect('system.admin_config');
  }
}