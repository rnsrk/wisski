<?php

/**
 * @file
 * Contains \Drupal\wisski_adapter_yaml\YamlAdapterBase.
 */

namespace Drupal\wisski_adapter_yaml;

use Drupal\Core\Form\FormStateInterface;
use Drupal\wisski_salz\EngineBase;

/**
 * Configuration Base for the Wisski YAML Adapter Engine
 */
abstract class YamlAdapterBase extends EngineBase {

  protected $entity_string;
  
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + array(
      'entity_string' => "4711:\n eid: 4711\n bundle: e21_person\n name: Sonic the Hedgehog\n birth_day: 2016-03-22",
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);
    $this->entity_string = $this->configuration['entity_string'];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return [
      'entity_string' => $this->entity_string,
    ] + parent::getConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    
    $form['entity_string'] = array(
      '#type' => 'textarea',
      '#title' => 'Entity Info',
      '#default_value' => $this->entity_string,
      '#description' => 'The entity information in YAML-like syntax',
    );
    
    return parent::buildConfigurationForm($form, $form_state) + $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::buildConfigurationForm($form, $form_state);
    $this->entity_string = $form_state->getValue('entity_string');
  }
  
}
