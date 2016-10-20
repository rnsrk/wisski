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

  protected $is_writable;
  protected $is_preferred_local_store;

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
    #return parent::defaultConfiguration() + 
    return [
      'is_writable' => TRUE,
      'is_preferred_local_store' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
  // this does not exist
#    parent::setConfiguration($configuration);
    if (is_null($configuration)) {
      $configuration = array();
      drupal_set_message(__METHOD__.' $configuration === NULL','error');
    }
    $this->configuration = $configuration + $this->defaultConfiguration();
    
    $this->is_writable = $this->configuration['is_writable'];
    $this->is_preferred_local_store = $this->configuration['is_preferred_local_store'];
    
  }


  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {

    return [
      'id' => $this->getPluginId(),
      'is_writable' => $this->isWritable(),
      'is_preferred_local_store' => $this->isPreferredLocalStore(),
    ] + $this->configuration;
    // this does not exist
     #+ parent::getConfiguration();
  }


  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['isWritable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Writable'),
      '#default_value' => $this->isWritable(),
      '#description' => $this->t('Is this Adapter writable?'),
    ];
    
#    $form['isReadable'] = [
#      '#type' => 'checkbox',
#      '#title' => $this->t('Readable'),
#      '#default_value' => $adapter->getEngine()->isReadable(),
#      '#description' => $this->t('Is this Adapter readable?'),
#    ];
    
    
    $form['isPreferredLocalStore'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preferred Local Store'),
      '#default_value' => $this->isPreferredLocalStore(),
      '#description' => $this->t('Is this Adapter the preferred local store?'),
    ];

    return $form;
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
    
    $is_preferred = $form_state->getValue('isPreferredLocalStore');
    $is_writable = $form_state->getValue('isWritable');
    
    if($is_preferred)
      $this->setPreferredLocalStore();
    else
      $this->unsetPreferredLocalStore();
      
    if($is_writable)
      $this->setWritable();
    else
      $this->setReadOnly();
      
    #return FALSE;
  }
  

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  public function providesCacheMode() {
    return FALSE;
  }
  
  public function providesFastMode() {
    return FALSE;
  }
  
  public function getQueryObject(EntityTypeInterface $entity_type,$condition, array $namespaces) {
    return new Query\Query($entity_type,$condition,$namespaces);
  }
  
  //@TODO overwrite
  public function writeFieldValues($entity_id,array $field_values,$pathbuilder,$bundle = NULL,$original_values=array(),$force_creation=FALSE) {
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
    $this->is_writable = FALSE;
  }
  
  public function setWritable() {
    $this->is_writable = TRUE;
  }
  
  public function setPreferredLocalStore() {
    $this->is_preferred_local_store = TRUE;
  }
  
  public function unsetPreferredLocalStore() {
    $this->is_preferred_local_store = FALSE;
  }
  
  /**
   * {@inheritdoc}
   */
  public function checkUriExists ($uri) {
    return FALSE;
  }

  /**
   * Gets the PB object for a given adapter id
   * @return a pb object
   */
  public function getPbForThis() {
    $pbs = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::loadMultiple();
    
    foreach($pbs as $pb) {
      // if there is no adapter set for this pb  
      if($adapter_id = $pb->getAdapterId()) {
        if ($this->adapterId() == $adapter_id) return $pb;
      }      
    }
    return NULL;
  }

  public function getDrupalId($uri) {
    #dpm($uri, "uri");
    
    if(is_numeric($uri) !== TRUE) {
      $id = AdapterHelper::getDrupalIdForUri($uri,$this->adapterId());
    } else {
      $id = $uri;
    }
    return $id;
  }
  
  public function setDrupalId($uri,$eid) {
    
    AdapterHelper::setDrupalIdForUri($uri,$eid,$this->adapterId());
  }
  
  public function getUriForDrupalId($id) {
    // danger zone: if id already is an uri e.g. due to entity reference
    // we load that. @TODO: I don't like that.
#    drupal_set_message("in: " . serialize($id));
#    drupal_set_message("vgl: " . serialize(is_int($id)));
    if(is_numeric($id) === TRUE) {
      $uri = AdapterHelper::getUrisForDrupalId($id,$this->adapterId());
      // just take the first one for now.
      $uri = current($uri);
    } else {
      $uri = $id;
    }
    //dpm($uri,__FUNCTION__.' '.$id);
#    drupal_set_message("out: " . serialize($uri));
    return $uri;
  }

  
}
