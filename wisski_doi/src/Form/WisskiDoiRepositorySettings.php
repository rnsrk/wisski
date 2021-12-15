<?php

namespace Drupal\wisski_doi\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Controller for DOI Settings.
 */
class WisskiDoiRepositorySettings extends FormBase {

  /**
   * Ddsik.
   */
  public function getFormId(): string {
    return 'wisski_doi_settings';
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = [];

    $settings = $this->configFactory()
      ->getEditable('wisski_doi.wisski_doi_settings');

    $form['#wisski_doi_settings'] = $settings;

    $form['doi_provider'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Provider'),
      '#default_value' => $settings->get('doi_provider'),
      '#description' => $this->t('The provider of your DOI repository, like "DataCite" or "CrossRef".'),
    ];

    $form['data_publisher'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Data Publisher'),
      '#default_value' => $settings->get('data_publisher'),
      '#description' => $this->t('The publisher of the data or the head person respectively institute, like "Germanic National Museum".'),
    ];

    $form['doi_base_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base Uri'),
      '#default_value' => $settings->get('doi_base_uri'),
      '#description' => $this->t('The Endpoint of your REST API, like "https://api.test.datacite.org/dois"'),
    ];

    $form['doi_repository_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Repository ID'),
      '#default_value' => $settings->get('doi_repository_id'),
      '#description' => $this->t('The place where you administer your DOIs, like "My DOI Repository".'),
    ];

    $form['doi_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DOI Prefix'),
      '#default_value' => $settings->get('doi_prefix'),
      '#description' => $this->t('The DOI prefix, respectively your repository ID, like "10.3435".'),
    ];

    $form['doi_repository_password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#default_value' => $settings->get('doi_repository_password'),
      '#description' => $this->t('The password for your repository account.'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = $form['#wisski_doi_settings'];
    $newVals = $form_state->getValues();

    $settings->set('doi_provider', $newVals['doi_provider']);
    $settings->set('doi_repository_id', $newVals['doi_repository_id']);
    $settings->set('doi_prefix', $newVals['doi_prefix']);
    $settings->set('doi_base_uri', $newVals['doi_base_uri']);
    $settings->set('doi_repository_password', $newVals['doi_repository_password']);
    $settings->set('data_publisher', $newVals['data_publisher']);

    $settings->save();

    $this->messenger()->addStatus($this->t('Changed DOI settings'));

    $form_state->setRedirect('system.admin_config');
  }

}
