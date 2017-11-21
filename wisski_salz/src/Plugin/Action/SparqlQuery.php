<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\Plugin\Action\SparqlQuery.
 */

namespace Drupal\wisski_salz\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Annotation\Action;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

use Drupal\wisski_salz\Plugin\wisski_salz\Engine\Sparql11Engine;

/**
* Redirects to a different URL.
*
* @Action(
*   id = "wisski_sparql_query",
*   label = @Translation("Execute SparQL"),
*   type = "wisski_individual"
* )
*/
class SparqlQuery extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getDefaultConfiguration() {
    return array(
      'adapter_id' => '',
      'sparql' => '',
      'query_method' => 'Update',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $adapters = entity_load_multiple('wisski_salz_adapter');
    $bundle_ids = array();
    // ask all adapters
    foreach($adapters as $aid => $adapter) {
      if ($adapter->getEngine() instanceof Sparql11Engine) {
        $adapters[$aid] = $adapter->label();
      }
      else {
        unset($adapters[$aid]);
      }
    }
    $form['adapter_id'] = array(
      '#type' => 'select',
      '#title' => t('Adapter'),
      '#default_value' => $this->configuration['adapter_id'],
      '#options' => $adapters,
      '#required' => TRUE,
    );
    $form['query_method'] = array(
      '#type' => 'radios',
      '#title' => t('Query type'),
      '#options' => [
        'Query' => 'Query',
        'Update' => 'Update',
      ],
      '#default_value' => $this->configuration['query_method'],
      '#required' => TRUE,
    );
    $form['sparql'] = array(
      '#type' => 'textarea',
      '#title' => t('SparQL query or update'),
      '#default_value' => $this->configuration['sparql'],
      '#required' => TRUE,
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration = $form_state->getValues();
  }
  
  
  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return \Drupal\Core\Access\AccessResult::allowed();
  }

  
  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    $adapter = entity_load('wisski_salz_adapter', $this->configuration['adapter_id']);
    if (!$adapter) {
      \Drupal::logger('Wisski Salz')->error('Action %action: adapter with ID %aid does not exist', [
          '%action' => $this->pluginDefinition['label'],
          '%aid' => $this->configuration['adapter_id'],
      ]);
    }
    $queryMethod = 'direct' . $this->configuration['query_method'];
    $result = $adapter->getEngine()->$queryMethod($this->configuration['sparql']);
    // what to do with the result?
  }

}

