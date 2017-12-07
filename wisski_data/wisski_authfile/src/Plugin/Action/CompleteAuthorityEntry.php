<?php

/**
 * @file
 * Contains \Drupal\wisski_authority_file\Plugin\Action\CompleteAuthorityEntry.
 */

namespace Drupal\wisski_authfile\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Annotation\Action;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;


/**
* Generates the title for the given WissKI entities.
*
* @Action(
*   id = "wisski_authfile_complete_info",
*   label = @Translation("Complete authority file information"),
*   type = "wisski_individual"
* )
*/
class CompleteAuthorityEntry extends ConfigurableActionBase {
  
  
  public function getAuthorityEntryBundle() {
    return $this->configuration['bundle'];
  }


  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'bundle' => '',
      'file_eid_field' => '',
      'entry_uri_field' => '',
      'entry_id_field' => '',
      'patterns' => '',
    );
  }



  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();
    $form['bundle'] = array(
      '#type' => 'textfield',
      '#title' => t('Bundle ID'),
      '#default_value' => $configuration['bundle'],
      '#required' => TRUE,
    );
    $form['entry_uri_field'] = array(
      '#type' => 'textfield',
      '#title' => t('Field ID for the URI of the entry'),
      '#default_value' => $configuration['entry_uri_field'],
    );
    $form['file_eid_field'] = array(
      '#type' => 'textfield',
      '#title' => t('Field ID for Authority File ID'),
      '#default_value' => $configuration['file_eid_field'],
    );
    $form['entry_id_field'] = array(
      '#type' => 'textfield',
      '#title' => t('Field ID for the ID of the entry'),
      '#default_value' => $configuration['entry_id_field'],
    );
    $form['patterns'] = array(
      '#type' => 'textarea',
      '#title' => t('The completion patterns'),
      '#default_value' => $configuration['patterns'],
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
    /** \Drupal\wisski_core\Entity\WisskiEntity $object */

    if (empty($object) || $object->bundle() != $this->configuration['bundle']) {
      return;
    }

    $patterns = $this->parsePatterns();
    if (empty($patterns)) {
      return;
    }
    // get the uri
    $uri_field_list = $object->get($this->configuration['entry_uri_field']);
    $uri = NULL;
    if ($uri_field_list && $uri_field = $uri_field_list->first()) {
      $uri = $uri_field->get($uri_field::mainPropertyName())->getValue();
    }
    // if there is a URI and there are authority file and entry id the URI
    // will be overwritten.
    // get the authority file entity id
    $auth_field_list = $object->get($this->configuration['file_eid_field']);
    if ($auth_field_list && $auth_field = $auth_field_list->first()) {
      $auth = $auth_field->get($auth_field::mainPropertyName())->getValue();
      // check if we have some uri pattern for this authority file
      if (isset($patterns[$auth])) {
        // get the entry id
        $id_field_list = $object->get($this->configuration['entry_id_field']);
        if ($id_field_list && $id_field = $id_field_list->first()) {
          $id = $id_field->get($id_field::mainPropertyName())->getValue();
          // build the uri and add it to the entity
          if (!empty($id)) {
            $uri = str_replace('{id}', $id, $patterns[$auth]);
          }
        }
      }
    }
#dpm($uri);    
    if (!empty($uri)) {
      $uri = trim($uri);
      $object->set($this->configuration['entry_uri_field'], $uri);
    }

  }


  /** Helper function that parses the textual patterns into an array
   *
   * @return a possibly empty array or NULL on failure
   */
  protected function parsePatterns() {
    $lines = explode("\n", trim($this->configuration['patterns']));
    if (empty($lines)) {
      return [];
    }
    $patterns = [];
    foreach ($lines as $i => $line) {
      $line = trim($line);
      if (empty($line)) {
        continue;
      }
      elseif (preg_match('/^(?<auth>\S+)\s+(?<uri>\S+)/u', $line, $matches)) {
        $patterns[$matches['auth']] = $matches['uri'];
      }
      else {
        return NULL;
      }
    }
    return $patterns;
  }

}

