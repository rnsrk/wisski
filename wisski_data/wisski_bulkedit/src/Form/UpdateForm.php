<?php

/**
 * @file
 * Contains \Drupal\wisski_bulkedit\Form\UpdateForm.
 */
   
namespace Drupal\wisski_bulkedit\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class UpdateForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wisski_bulkedit_update_form';
  }

  /**
   * {@inheritdoc}
   */
  function buildForm(array $form, FormStateInterface $form_state) {
    
    $form['#tree'] = TRUE;

    $storage = $form_state->getStorage();
    
    $file_id = empty($form_state->getValue('file', '')) ? '' : $form_state->getValue('file', '')[0];
    $bundle_id = $form_state->getValue('bundle', '');

    // we effectively have a two-step form triggered by ajax
    // first: upload file_id
    // second: define mappings
    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => 'CSV file',
      '#upload_location' => 'private://',
      '#default_value' => $file_id,
      '#upload_validators' => [
        'file_validate_extensions' => ['csv txt tsv'],
      ],
    ];

    $bundles = ['' => $this->t('- Select -')];
    foreach (entity_load_multiple('wisski_bundle') as $bid => $bundle) {
      $bundles[$bid] = $bundle->label();
    }
    
    $form['bundle'] = [
      '#type' => 'select',
      '#title' => 'Bundle',
      '#options' => $bundles,
      '#default_value' => $form_state->getValue('bundle', ''),
      '#ajax' => [
        'callback' => '::ajaxUpdateMapping',
        'wrapper' => 'mapping_wrapper',
      ],
    ];
    
    if (!isset($storage['header']) && $file_id) {
      $file = entity_load('file', $file_id);
      if ($file) {
        $storage += $this->parseFile($file->getFileUri());
        $form_state->setStorage($storage);
      }
      else {
        drupal_set_message('Could not load file');
      }
    }

    $form['mapping'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Column mappings'),
      '#prefix' => '<div id="mapping_wrapper">',
      '#suffix' => '</div>',
    ];
   
#rpm([$bundle_id, $file_id, $storage], 'öö');   
    $fields = ['' => $this->t('- None -')];
    if ($bundle_id) {
      $field_defs = \Drupal::entityManager()->getFieldDefinitions('wisski_individual', $bundle_id);
      foreach ($field_defs as $field_id => $def) {
        /** Drupal\Core\Field\FieldDefinitionInterface $def **/
        $fields[$field_id] = $def->getLabel();  // ->label() is not defined!
      }
    }
    
    if (isset($storage['header']) && !empty($storage['header']) && $bundle_id) {
      $header = $storage['header'];
      foreach ($header as $i => $col) {
        $form['mapping']["col_$i"] = [
          '#type' => 'select',
          '#title' => empty($col) ? $this->t("<column @i>", ['@i' => $i]) : Html::escape($col),
          '#options' => $fields,
          '#default_value' => $form_state->getValue("col_$i", ''),
        ];
      }
    }
    else {
      $form['mapping']['#description'] = $this->t('Please first select a CSV/TSV file_id and a bundle.');
    }
      
    // submit button
    $form['actions']['update'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
    ];

    return $form;

  }
  
  
  public function ajaxUpdateMapping(array $form, FormStateInterface $form_state) {
    return $form['mapping'];
  }


  public function submitForm(array &$form, FormStateInterface $form_state) {
    $bundle_id = $form_state->getValue('bundle', '');
    $mapping = $form_state->getValue('mapping', []);
    dpm([$bundle_id, $mapping]);
  }
  

  /** Parse the csv file and return header and table data
   * 
   * TODO: this is just a dirty hack for parsing a TSV with no options
   *       this could be done more professional
   *       maybe read into a db table and do the db import? => both should
   *       actually be merged
   */
  protected function parseFile($file) {
    $csv = file_get_contents($file);
    if (!$csv) return ['header' => NULL, 'table' => NULL];

    list($header, $data) = explode("\n", $csv, 2);

    $header = explode("\t", $header);
    $col_count = count($header);

    $table = [];
    foreach (explode("\n", $data) as $row) {
      $cells = explode("\t", $row);
      // make array same size as header
      $cells = array_pad($cells, $col_count, '');
      array_splice($cells, $col_count);
      $table[] = $cells;
    }
    
    return ['header' => $header, 'table' => $table];
  }

}
