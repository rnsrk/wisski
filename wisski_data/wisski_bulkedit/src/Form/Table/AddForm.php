<?php

/**
 * @file
 * Contains \Drupal\wisski_bulkedit\Form\Table\AddForm.
 */
   
namespace Drupal\wisski_bulkedit\Form\Table;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

use Drupal\wisski_bulkedit\Entity\Table;

use League\Csv\Reader;

class AddForm extends EntityForm {
  

  /**
   * {@inheritdoc}
   */
  function form(array $form, FormStateInterface $form_state) {
    
    $form = parent::form($form, $form_state);

    $form['label'] =  [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_name' => '',
      '#required' => TRUE,
      '#field_prefix' => Table::TABLE_PREFIX,
      '#machine_name' => [
        'label' => $this->t("Table name"),
        'exists' => ['\Drupal\wisski_bulkedit\Entity\Table', 'load'],
      ],
      '#default_value' => $this->entity->id(),
      '#disabled' => !$this->entity->isNew(),
    ];
    $form['csv_content'] = [
      '#type' => 'details',
      '#title' => $this->t('CSV Content'),
#      '#tree' => TRUE,
    ];
    $extensions = ['txt', 'csv'];
    $form['csv_content']['file'] = [
      '#type' => 'file',
      '#title' => 'Content file upload',
      '#upload_validators' => [
        'file_validate_extensions' => [join(' ', $extensions)],
      ],
      '#description' => $this->t('A CSV file. Only these extensions are allowed: %e', ['%e' => join(', ', $extensions)]),
    ];
    $form['csv_content']['direct'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content paste area'),
      '#description' => $this->t('Directly paste CSV content. This is only considered if no file is uploaded and must not be empty then.'),
    ];
    $form['table_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Table settings'),
      '#tree' => TRUE,
    ];
    $form['table_settings']['col_names'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Override column names'),
      '#description' => $this->t('One column name per row. If left empty, the first row is interpreted as column names.'),
    ];
    $form['table_settings']['col_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Column size'),
      '#default_value' => 1000,
    ];
    $form['table_settings']['autoinc_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Auto-increment field'),
      '#default_value' => '',
      '#description' => $this->t('If non-empty, adds an auto-increment field with this name to the table'),
    ];
    
    return $form;

  }
  

  /** 
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $csv = NULL;
    
    $files = file_managed_file_save_upload($form['csv_content']['file'], $form_state);
    if ($files) {
      $file = reset($files);
      $form_state->set('table_file', $file);
      $csv = Reader::createFromPath($file->getFileUri());
    }
    elseif ($content = trim($form_state->getValue('direct'))) {
      $form_state->set('table_content', $content);
      $csv = Reader::createFromString($content);
    }
    $form_state->set('csv', $csv);
    // We have to be able to build the schema, either because it is specified
    // directly or it is deduced from content
    $schema = $this->buildSchema($form_state, $csv);
    if ($schema) {
      $form_state->set('schema', $schema);
    }
    else {
      $form_state->setErrorByName(
        'csv_content', 
        $this->t('Cannot determine table columns. Specify columns or upload content.')
      );
    }

  }

  
  /** 
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // we could derive the schema. 
    // we can now create the entity and the db table
    $return = parent::save($form, $form_state);
    
    $table = $this->entity;
    
    # we create the DB table and load the contents 
    $table->makeTable($form_state->get('schema'));
    $this->importCsv($form_state->get('csv'));
    
    $form_state->setRedirect('entity.wisski_bulkedit_table.collection');
    return $return;
  }


  protected function importCsv($csv) {
    if (empty($csv)) return 0;
    $c = 0;
    $insert = NULL;
    foreach ($csv as $record) {
      if ($c % 100 == 0) {
        if ($insert !== NULL) $insert->execute();
        $insert = $this->entity->getDbConnection()
                  ->insert($this->entity->tableName())
                  ->fields($record);
      }
      else {
        $insert->values($record);
      }
      $c++;
    }
    if ($insert !== NULL) $insert->execute();
    drupal_set_message($this->t('Successfully imported @c rows', ['@c' => $c]));
    return $c;
  }


  protected function buildSchema($form_state, $csv) {
    $settings = $form_state->getValue(['table_settings']);

    // we either take the given column names or we extract them from the data
    if (trim($settings['col_names']) != '') {
      preg_match_all('/^\s*(\S|\S.*\S)\s*$/um', $settings['col_names'], $columns);
      $columns = $columns[1];
    }
    elseif ($csv) {
      $csv->setHeaderOffset(0);
      $columns = $csv->getHeader();
    }
    if (empty($columns)) {
      return FALSE;
    }
    // for each column make a varchar field and set it the same size
    $schema = [];
    foreach ($columns as $col) {
      $schema[$col] = [
        'type' => 'varchar',
        'length' => $settings['col_size'] ?: 333,
      ];
    }
    if (empty($schema)) return FALSE;
    $schema = ['fields' => $schema];
    // add an autoinc id field and make it the primary index
    if ($autoinc_field = $settings['autoinc_field']) {
      $schema['fields'][$autoinc_field] = [
        'type' => 'serial',
        'size' => 'normal',
        'not null' => TRUE,
      ];
      $schema['primary key'] = ['__id__'];
    }
    return $schema;
  }

}
