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
    $items = array();

    $items['source'] = array(
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

    $items['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
      '#weight' => 100,
    );

    return $items;

  }


  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // TODO: Implement submitForm() method.
  }
}
