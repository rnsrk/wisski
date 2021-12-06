<?php

namespace Drupal\wisski_doi\Form;

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

    //load exisiting data
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
        /*
        '#upload_validators' => array(
          'file_validate_extensions' => array('json'),
          'file_validate_size' => [Environment::getUploadMaxSize()],
        ),
        */
        '#description' => $this->t('Only JSON files allowed, max filesize is %filesize.', ['%filesize' => format_size(Environment::getUploadMaxSize())]),
      ),
      // simple text area
      'paste' => array(
        '#type' => 'textarea',
        '#title' => $this->t('Direct paste'),
        '#rows' => 20,
        '#default_value' => $settings->get('schema'),
      ),
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
      '#weight' => 100,
    );

    return $form;

  }


    public function validateForm(array &$form, FormStateInterface $form_state) {
      $upload = $form_state->getValue('upload');

      dpm(json_decode($upload));
      $paste = $form_state->getValue('paste');
      if (empty($upload) && empty($paste)) {
        $form_state->setErrorByName('upload', $this->t('You have to provide a file or insert a direct paste.'));
        $form_state->setErrorByName('paste', $this->t('You have to provide a file or insert a direct paste.'));
        $continue = FALSE;
      }
    }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $settings = $form['#wisski_doi_scheme_settings'];
    $new_vals = $form_state->getValues();
    dpm($new_vals);
    if (!empty($new_vals['upload'])) {
      $settings->set('paste', $new_vals['upload']);
      $this->messenger()->addStatus($this->t('Saved Schema from upload.'));
    } elseif
    (!empty($new_vals['paste'])) {
      $settings->set('paste', $new_vals['paste']);
      $this->messenger()->addStatus($this->t('Saved Schema from direct paste.'));
    } else {
      $this->messenger()->addStatus($this->t('Please provide a schema!'));
    }
    dpm(serialize($settings));
    $settings->save();
    #$form_state->setRedirect('entity.wisski_bundle.doi_scheme');
  }

}
