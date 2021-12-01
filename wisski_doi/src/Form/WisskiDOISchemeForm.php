<?php

namespace Drupal\wisski_doi\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;


/**
 * Provides a form for reverting a wisski_individual revision.
 *
 * @internal
 */
class WisskiDOISchemeForm extends FormBase {

  public function getFormId()
  {
    return 'wisski_doi_scheme_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = array();

    $settings = $this->configFactory()->getEditable('wisski_doi.wisski_doi_scheme_form');

    $form['#wisski_doi_scheme_settings'] = $settings;

    $form['schema'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Schema'),
      '#default_value' => $settings->get('schema'),
      '#description' => $this->t('The Schema.'),
    ];

    $form['source'] = array(
      '#type' => 'fieldset',
      '#title' => t('Specify scheme file'),
      '#required' => TRUE,
      '#weight' => 2,
      'upload' => array(
        '#type' => 'file',
        '#title' => t('File upload'),
        // port to D8:
        // we must explicitly set the extension validation to allow xml files
        // to be uploaded.
        // an empty array disables the extension restrictions:
        // this is theoretically somewhat insecure but we get away with it ftm...
        '#upload_validators' => array(
          'file_validate_extensions' => array(),  // => array('xml')
        ),
      ),
      'paste' => array(
        '#type' => 'textarea',
        '#title' => $this->t('Direct paste'),
        '#rows' => 4,
      ),
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
      '#weight' => 100,
    );

    return $form;

  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $settings = $form['#wisski_doi_scheme_settings'];
    $new_vals = $form_state->getValues();
    dpm($new_vals);
    if (!empty($new_vals['paste'])) {
      $settings->set('schema', $new_vals['paste']);
      $this->messenger()->addStatus($this->t('Saved Schema from direct paste.'));
    } elseif (!empty($new_vals['upload'])) {
      $settings->set('schema', $new_vals['upload']);
      $this->messenger()->addStatus($this->t('Saved Schema from upload.'));
    } else {
      $this->messenger()->addStatus($this->t('Please provide a schema!'));
    }
    dpm(serialize($settings));
    $settings->save();
    #$form_state->setRedirect('entity.wisski_bundle.doi_scheme');
  }

}
