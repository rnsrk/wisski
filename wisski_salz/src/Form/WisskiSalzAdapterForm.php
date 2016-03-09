<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\Form\WisskiSalzAdapterForm.
 */

namespace Drupal\wisski_salz\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class WisskiSalzAdapterForm.
 *
 * @package Drupal\wisski_salz\Form
 */
class WisskiSalzAdapterForm extends EntityForm {
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $wisski_salz_adapter = $this->entity;
    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $wisski_salz_adapter->label(),
      '#description' => $this->t("Label for the WissKI Salz Adapter."),
      '#required' => TRUE,
    );

    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $wisski_salz_adapter->id(),
      '#machine_name' => array(
        'exists' => '\Drupal\wisski_salz\Entity\WisskiSalzAdapter::load',
      ),
      '#disabled' => !$wisski_salz_adapter->isNew(),
    );

    /* You will need additional form elements for your custom properties. */

    $manager = \Drupal::service('plugin.manager.wisski_salz_adapter_plugin');
        
    $plugins = $manager->getDefinitions();
    
#    drupal_set_message(serialize($plugins));
    
#    return $form;
    
    $client_options = [];
    foreach ($plugins as $client) {
      $client_options[$client['id']] = $client['name'];
    }
            
    $form['storage_settings']['client'] = array(
      '#type' => 'select',
      '#title' => $this->t('Storage client'),
      '#options' => $client_options,
      '#required' => TRUE,
#      '#default_value' => $type->getClient(),
    );

#    $formats = \Drupal::service('external_entity.storage_client.response_decoder_factory')->supportedFormats();
#    $form['storage_settings']['format'] = array(
#      '#type' => 'select',
#      '#title' => $this->t('Format'),
#      '#options' => array_combine($formats, $formats),
#      '#required' => TRUE,
##      '#default_value' => $type->getFormat(),
#    );

#    $form['storage_settings']['endpoint'] = array(
#      '#type' => 'textfield',
#      '#title' => $this->t('Endpoint'),
#      '#required' => TRUE,
##      '#default_value' => $type->getEndpoint(),
#      '#size' => 255,
#      '#maxlength' => 255,
#    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $wisski_salz_adapter = $this->entity;
    $status = $wisski_salz_adapter->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label WissKI Salz Adapter.', [
          '%label' => $wisski_salz_adapter->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label WissKI Salz Adapter.', [
          '%label' => $wisski_salz_adapter->label(),
        ]));
    }
    $form_state->setRedirectUrl($wisski_salz_adapter->urlInfo('collection'));
  }

}
