<?php

/**
 * @file
 * Contains \Drupal\wisski_iip_image\Form\WisskiIIIFSettings
 */

 namespace Drupal\wisski_doi\Form;

 use Drupal\Core\Form\FormBase;
 use Drupal\Core\Form\FormStateInterface;

 use Drupal\Core\Url;


/**
 * Controller for DOI Settings
 *
 */
class WisskiDOISettings extends FormBase {

  public function getFormId() {
    return 'wisski_doi_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = array();

    $settings = $this->configFactory()->getEditable('wisski_doi.wisski_doi_settings');

    $form['#wisski_doi_settings'] = $settings;

    $form['doi_provider'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Provider'),
      '#default_value' => $settings->get('doi_provider'),
      '#description' => $this->t('The provider of your DOI repository, like DataCite or CrossRef.'),
     ];

    $form['doi_repository_id'] = [
     '#type' => 'textfield',
     '#title' => $this->t('Repository ID'),
     '#default_value' => $settings->get('doi_repository_id'),
     '#description' => $this->t('The DOI prefix, respectively your repository ID, like 10.3435.'),
    ];

    $form['doi_repository_password'] = [
     '#type' => 'password',
     '#title' => $this->t('Password'),
     '#default_value' => $settings->get('doi_repository_password'),
     '#description' => $this->t('The password for your repository account.'),
    ];

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    );

    return $form;

  }

   public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = $form['#wisski_doi_settings'];
    $new_vals = $form_state->getValues();

    $settings->set('doi_provider', $new_vals['doi_provider']);
    $settings->set('doi_repository_id', $new_vals['doi_repository_id']);
    $settings->set('doi_repository_password', $new_vals['doi_repository_password']);

    $settings->save();

    $this->messenger()->addStatus($this->t('Changed DOI settings'));

    $form_state->setRedirect('system.admin_config');

   }

}
