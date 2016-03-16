<?php

/**
 * @file
 * Contains Drupal\wisski_salz\EngineBase.
 */

namespace Drupal\wisski_salz;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base class for external entity storage clients.
 */
abstract class EngineBase extends PluginBase implements EngineInterface {


  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->pluginDefinition['name'];
  }


  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'];
  }


  public function defaultConfiguration() {
    return [];
  }


  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }


  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return [
      'id' => $this->getPluginId(),
    ] + $this->configuration;
  }


  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [];
  }


  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }


  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }
  

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }


}
