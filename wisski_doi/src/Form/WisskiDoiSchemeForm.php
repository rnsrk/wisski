<?php

namespace Drupal\wisski_doi\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Environment;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for reverting a wisski_individual revision.
 *
 * @internal
 */
class WisskiDoiSchemeForm extends FormBase {

  /**
   *
   */
  public function getFormId() {
    return 'wisski_doi_scheme_form';
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = [];

    // Load existing data.
    $settings = $this->configFactory()->getEditable('wisski_doi.wisski_doi_scheme_form');

    // Save existing data in form property.
    $form['#wisski_doi_scheme_settings'] = $settings;

    // Create form elements.
    $form['source'] = [
      '#type' => 'fieldset',
      '#title' => t('Specify scheme file'),
      '#required' => TRUE,
      '#weight' => 2,
      // Upload possibility with json validator and maxupload size.
      'upload' => [
        '#type' => 'file',
        '#title' => t('File upload'),
        '#upload_validators' => [
          'file_validate_extensions' => ['json'],
          'file_validate_size' => [Environment::getUploadMaxSize()],
        ],
        '#description' => $this->t('Only JSON files allowed, max filesize is %filesize.', ['%filesize' => format_size(Environment::getUploadMaxSize())]),
        '#default_value' => $settings->get('paste'),
      ],
      // Simple text area.
      'paste' => [
        '#type' => 'textarea',
        '#title' => $this->t('Direct paste'),
        '#rows' => 20,
        '#default_value' => $settings->get('paste'),
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
      '#weight' => 100,
    ];

    return $form;

  }

  /**
   *
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Load from file.
    $data = NULL;
    $file_url = NULL;
    $files = file_managed_file_save_upload($form['source']['upload'], $form_state);
    // Load from direkt paste.
    $paste = $form_state->getValues()['paste'];
    // There is a file, get the content.
    if ($files) {
      // First one in array (array is keyed by file id)
      $file = reset($files);
      $data = file_get_contents($file->getFileUri());
    }
    // If not get it from paste.
    elseif ($paste) {
      $data = $paste;
    }
    else {
      // If no file is given, it is an error.
      $form_state->setError($form['source'], $this->t('You must specify an JSON file!'));
    }
    // If we came here, the user uploaded some data, but it may be invalid.
    $data = JSON::decode($data) ? $data : NULL;
    if (!$data) {
      $form_state->setError($form['source'], $this->t('Invalid JSON.'));
    }
    else {
      // As we have saved the file already, we cache its path for submitForm()
      $storage = $form_state->getStorage();
      $storage['doi_scheme'] = $data;
      $form_state->setStorage($storage);
    }
  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Laod existing data.
    $settings = $form['#wisski_doi_scheme_settings'];
    $json_content = $form_state->getStorage()['doi_scheme'];
    $new_vals = $form_state->getValues();

    if (!empty($json_content)) {
      // Lock for upload first.
      $settings->set('paste', $json_content);
      $this->messenger()->addStatus($this->t('Saved Schema.'));
    }
    elseif (!empty($new_vals['paste'])) {
      // If there is none, take direct paste.
      $settings->set('paste', $new_vals['paste']);
      $this->messenger()->addStatus($this->t('Saved Schema.'));
    }
    else {
      $this->messenger()->addStatus($this->t('Please provide a schema!'));
    }
    $settings->save();
    // $form_state->setRedirect('entity.wisski_bundle.doi_scheme');
  }

}
