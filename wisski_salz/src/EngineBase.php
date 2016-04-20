<?php

/**
 * @file
 * Contains Drupal\wisski_salz\EngineBase.
 */

namespace Drupal\wisski_salz;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeInterface;

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
  
  
  /**
   * {@inheritdoc}
   */
  public function adapterId() {
    return $this->configuration['adapterId'];
  }


  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    if (is_null($configuration)) {
      $configuration = array();
      drupal_set_message(__METHOD__.' $configuration === NULL','error');
    }
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
  
  public function getQueryObject(EntityTypeInterface $entity_type,$condition, array $namespaces) {
    return new Query\Query($entity_type,$condition,$namespaces);
  }

  public function getBundleIdForEntityId($entity_id) {
    return NULL;
  }
  
  public function doYouKnowEntityId($entity_id) {
    return FALSE;
  }
}
