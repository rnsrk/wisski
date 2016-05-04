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

  private $is_writable = TRUE;
  private $is_preferred_local_store = FALSE;

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

  public function getBundleIdsForEntityId($entity_id) {
    return NULL;
  }
  
  //@TODO overwrite
  public function writeFieldValues($entity_id,array $field_values) {
    return EngineInterface::NULL_WRITE;
  }
  
  public function isWritable() {
    return $this->is_writable;
  }
  
  public function isReadOnly() {
    return !$this->is_writable;
  }
  
  public function isPreferredLocalStore() {
    return $this->is_preferred_local_store;
  }
  
  public function setReadOnly() {
    $this->is_writable = FALSE;;
  }
  
  public function setWritable() {
    $this->is_writable = TRUE;
  }
  
  public setPreferredLocalStore() {
    $this->is_preferred_local_store = TRUE;
  }
  
  public unsetPreferredLocalStore() {
    $this->is_preferred_local_store = FALSE;
  }
}
