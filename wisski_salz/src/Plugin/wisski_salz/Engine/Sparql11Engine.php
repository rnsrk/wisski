<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\Plugin\wisski_salz\Engine\WisskiSparql11Plugin.
 */

namespace Drupal\wisski_salz\Plugin\wisski_salz\Engine;

use Drupal\Core\Form\FormStateInterface;
use Drupal\wisski_salz\EngineBase;

/**
 * Wiki implementation of an external entity storage client.
 *
 * @Engine(
 *   id = "sparql11",
 *   name = @Translation("Sparql 1.1"),
 *   description = @Translation("Provides access to a SPARQL endpoint that supports SPARQL 1.1")
 * )
 */
class Sparql11Engine extends EngineBase {

  protected $read_url;
  protected $write_url;
  
  /**
   * An easyrdf store client
   */
  protected $store = NULL;
  
  
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'read_url' => '',
      'write_url' => '',
    ];
  }


  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);
    $this->read_url = $this->configuration['read_url'];
    $this->write_url = $this->configuration['write_url'];
    $this->store = NULL;
  }


  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return [
      'read_url' => $this->read_url,
      'write_url' => $this->write_url
    ] + parent::getConfiguration();
  }


  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    
    $form['read_url'] = [
      '#type' => 'textfield',
      '#title' => 'Read URL',
      '#default_value' => $this->read_url,
      '#description' => 'bla.',
    ];
    $form['write_url'] = [
      '#type' => 'textfield',
      '#title' => 'Write URL',
      '#default_value' => $this->write_url,
      '#description' => 'bla.',
    ];
    
    return parent::buildConfigurationForm($form, $form_state) + $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::buildConfigurationForm($form, $form_state);
    $this->read_url = $form_state->getValue('read_url');
    $this->write_url = $form_state->getValue('write_url');
  }
  

  public function load($uri) {
    return "bla";
  }


  public function loadMultiple($uris = NULL) {
    return array("bla", "blubb");

  }

  

}
