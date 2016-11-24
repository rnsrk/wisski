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
abstract class NonWritableEngineBase extends EngineBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'is_writable' => FALSE,
      'is_preferred_local_store' => FALSE,
      'same_as_properties' => array(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);
    if (is_null($configuration)) {
      $configuration = array();
    }
    $this->configuration = $configuration + $this->defaultConfiguration();
    
    $this->is_writable = FALSE;
    $this->same_as_properties = array();
  }


  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {

    return [
      'id' => $this->getPluginId(),
      'is_writable' => FALSE,
      'is_preferred_local_store' => $this->isPreferredLocalStore(),
      'same_as_properties' => array(),
    ] + parent::getConfiguration();
  }


  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['isWritable'] = 
    array(
      '#default_value' => 0,
      '#disabled' => TRUE,
    ) + $form['isWritable'];
    
    $form['sameAsProperties'] = array(
      '#default_value' => '',
      '#disabled' => TRUE,
    ) + $form['sameAsProperties'];

    return $form;
  }


  public function getQueryObject(EntityTypeInterface $entity_type,$condition, array $namespaces) {
    return new Query\Query($entity_type,$condition,$namespaces);
  }
  
  public function writeFieldValues($entity_id,array $field_values,$pathbuilder,$bundle = NULL,$original_values=array(),$force_creation=FALSE) {
    return EngineInterface::NULL_WRITE;
  }
  
  public function isWritable() {
    return FALSE;
  }
  
  public function isReadOnly() {
    return TRUE;
  }
  
  public function setReadOnly() {
  }
  
  public function setWritable() {
  }
  
  
  /**
   * {@inheritdoc}
   */
  public function getSameAsProperties() {
    return array();
  }
  
  /**
   * {@inheritdoc}
   */
  public function defaultSameAsProperties() {
    return array();
  }
  

  /**
   * {@inheritdoc}
   */
  public function getDrupalIdForUri($uri,$adapter_id=NULL) {
    return NULL;
  }
  
  /**
   * {@inheritdoc}
   */
  public function setDrupalId($uri,$eid) {
  }
      
  

  /**
   * {@inheritdoc}
   */
  public function getUrisForDrupalId($id) {
    return array();
  }
  
  /**
   * {@inheritdoc}
   */
  public function getSameUris($uri) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getSameUri($uri, $adapter_id) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setSameUris($uris, $entity_id) {
  }
  
  /**
   * {@inheritdoc}
   */
  public function generateFreshIndividualUri() {
    return NULL;
  }
  
}
