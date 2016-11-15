<?php

namespace Drupal\wisski_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\Core\Url;

class WisskiConfigForm extends FormBase {

  public function getFormId() {

    return 'wisski_core_display_settings_form';
  }
  
  public function buildForm(array $form, FormStateInterface $form_state) {
  
    $request_query = \Drupal::request()->query;
    //dpm($request_query,'HTTP GET');
    if ($request_query->get('q') === 'flush') {
      db_truncate('wisski_salz_id2uri');
      drupal_set_message('Flushed the ID cache');
    }
  
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
    $form['preview_image'] = array('#tree'=>TRUE);
    $form['preview_image']['max_width'] = array(
      '#type' => 'number',
      '#default_value' => $settings->get('wisski_preview_image_max_width_pixel'),
      '#min' => 10,
      '#step' => 1,
      '#title' => $this->t('Maximum width of entity list preview images in pixels'),
    );
    $form['preview_image']['max_height'] = array(
      '#type' => 'number',
      '#default_value' => $settings->get('wisski_preview_image_max_height_pixel'),
      '#min' => 10,
      '#step' => 1,
      '#title' => $this->t('Maximum height of entity list preview images in pixels'),
    );
    $form['flush'] = array(
      '#type' => 'details',
      '#title' => $this->t('Flush WissKI caches'),
    );
    $form['flush']['disclaimer'] = array(
      '#type' => 'item',
      '#markup' => $this->t('Thia will flush the \'wisski_salz_id2uri\' database table but keep the local store info untouched'),
    );
    $form['flush']['id2uri'] = array(
      '#type' => 'link',
      '#title' => $this->t('Flush EntityID - URI matching table'),
      '#url' => Url::fromRoute('<current>',array('q'=>'flush')),
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    );
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    $settings = $form['#wisski_settings'];
    $new_vals = $form_state->getValues();
    $settings->set('wisski_max_entities_per_page',$new_vals['pager_max']);
    $settings->set('wisski_preview_image_max_width_pixel',$new_vals['preview_image']['max_width']);
    $settings->set('wisski_preview_image_max_height_pixel',$new_vals['preview_image']['max_height']);
    $settings->save();
    drupal_set_message($this->t('Changed global WissKI display settings'));
    $form_state->setRedirect('system.admin_config');
  }
}