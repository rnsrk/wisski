<?php

namespace Drupal\wisski_salz;

use Drupal\Core\Form\FormStateInterface;

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

  /**
   *
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'is_writable' => FALSE,
      'is_preferred_local_store' => FALSE,
      'same_as_properties' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);
    if (is_null($configuration)) {
      $configuration = [];
    }
    $this->configuration = $configuration + $this->defaultConfiguration();

    $this->is_writable = FALSE;
    $this->same_as_properties = [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {

    return [
      'id' => $this->getPluginId(),
      'is_writable' => FALSE,
      'is_preferred_local_store' => $this->isPreferredLocalStore(),
      'same_as_properties' => [],
    ] + parent::getConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildConfigurationForm($form, $form_state);
    $form['isWritable'] =
        [
          '#default_value' => 0,
          '#disabled' => TRUE,
        ] + $form['isWritable'];

    $form['sameAsProperties'] = [
      '#default_value' => '',
      '#disabled' => TRUE,
    ] + $form['sameAsProperties'];

    return $form;
  }

  /**
   * This function is copied from the sparql_pb engine.
   * This should be made global as it actually stores the namespaces globally.
   */
  public function getNamespaces() {
    $ns = [];
    $db_spaces = db_select('wisski_core_ontology_namespaces', 'ns')
      ->fields('ns')
      ->execute()
      ->fetchAllAssoc('short_name');
    foreach ($db_spaces as $space) {
      $ns[$space->short_name] = $space->long_name;
    }
    return $ns;
  }

  /**
   *
   */
  public function writeFieldValues($entity_id, array $field_values, $pathbuilder, $bundle = NULL, $original_values = [], $force_creation = FALSE, $initial_write = FALSE) {
    return EngineInterface::NULL_WRITE;
  }

  /**
   *
   */
  public function isWritable() {
    return FALSE;
  }

  /**
   *
   */
  public function isReadOnly() {
    return TRUE;
  }

  /**
   *
   */
  public function setReadOnly() {
  }

  /**
   *
   */
  public function setWritable() {
  }

  /**
   *
   */
  public function deleteEntity($entity) {
  }

  /**
   * {@inheritdoc}
   */
  public function getSameAsProperties() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSameAsProperties() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getDrupalIdForUri($uri, $adapter_id = NULL) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setDrupalId($uri, $eid) {
  }

  /**
   * {@inheritdoc}
   */
  public function getUrisForDrupalId($id) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSameUris($uri) {
    return [];
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
