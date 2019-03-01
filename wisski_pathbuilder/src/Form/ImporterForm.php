<?php

namespace Drupal\wisski_pathbuilder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;

/**
 *
 */
class ImporterForm extends FormBase {

  protected $configManager = NULL;

  /**
   *
   */
  public function getFormId() {
    return 'wisski_pathbuilder_importer_form';
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state, $pbid = NULL) {

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File URL'),
    ];
    $form['actions'] = [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Import'),
      ],
    ];
    return $form;

  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the file and decode the yaml.
    $url = $form_state->getValue('url');
    $yaml = file_get_contents($url);
    $config_assemblage = Yaml::decode($yaml);
    // Create a new config entity for each entry.
    $configManager = \Drupal::service('config.manager');
    $factory = $configManager->getConfigFactory();
    foreach ($config_assemblage as $config_name => $data) {
      $config = $factory->getEditable($config_name);
      $config->setData($data);
      $config->save();
    }
  }

}
