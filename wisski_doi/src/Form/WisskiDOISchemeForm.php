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
class WisskiDOISchemeForm extends FormBase
{

  public function getFormId()
  {
    return 'wisski_doi_scheme_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {

    $form = array();

    //load existing data
    $settings = $this->configFactory()->getEditable('wisski_doi.wisski_doi_scheme_form');

    // save existing data in form property
    $form['#wisski_doi_scheme_settings'] = $settings;

    // create form elements
    $form['source'] = array(
      '#type' => 'fieldset',
      '#title' => t('Specify scheme file'),
      '#required' => TRUE,
      '#weight' => 2,
      //upload possibility with json validator and maxupload size
      'upload' => array(
        '#type' => 'file',
        '#title' => t('File upload'),
        '#upload_validators' => array(
          'file_validate_extensions' => array('json'),
          'file_validate_size' => [Environment::getUploadMaxSize()],
        ),
        '#description' => $this->t('Only JSON files allowed, max filesize is %filesize.', ['%filesize' => format_size(Environment::getUploadMaxSize())]),
        '#default_value' => $settings->get('paste'),
      ),
      // simple text area
      'paste' => array(
        '#type' => 'textarea',
        '#title' => $this->t('Direct paste'),
        '#rows' => 20,
        '#default_value' => $settings->get('paste'),
      ),
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
      '#weight' => 100,
    );

    return $form;

  }


  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    // load from file
    $data = NULL;
    $file_url = NULL;
    $files = file_managed_file_save_upload($form['source']['upload'], $form_state);
    // load from direkt paste
    $paste = $form_state->getValues()['paste'];
    // there is a file, get the content
    if ($files) {
      $file = reset($files);  // first one in array (array is keyed by file id)
      $data = file_get_contents($file->getFileUri());
    } // if not get it from paste
    elseif ($paste) {
      $data = $paste;
    } else {
      // if no file is given, it is an error
      $form_state->setError($form['source'], $this->t('You must specify an JSON file!'));
    }
    // if we came here, the user uploaded some data, but it may be invalid
    $data = JSON::decode($data) ? $data : Null;
    if (!$data) {
      $form_state->setError($form['source'], $this->t('Invalid JSON.'));
    } else {
      // as we have saved the file already, we cache its path for submitForm()
      $storage = $form_state->getStorage();
      $storage['doi_scheme'] = $data;
      $form_state->setStorage($storage);
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // laod existing data
    $settings = $form['#wisski_doi_scheme_settings'];
    $json_content = $form_state->getStorage()['doi_scheme'];
    $new_vals = $form_state->getValues();

    if (!empty($json_content)) {
      // lock for upload first
      $settings->set('paste', $json_content);
      $this->messenger()->addStatus($this->t('Saved Schema.'));
    } elseif (!empty($new_vals['paste'])) {
      // if there is none, take direct paste
      $settings->set('paste', $new_vals['paste']);
      $this->messenger()->addStatus($this->t('Saved Schema.'));
    } else {
      $this->messenger()->addStatus($this->t('Please provide a schema!'));
    }
    $settings->save();
    #$form_state->setRedirect('entity.wisski_bundle.doi_scheme');
  }

}
