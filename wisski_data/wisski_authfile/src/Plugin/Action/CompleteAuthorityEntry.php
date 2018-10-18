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
#    dpm($object->id());
#    drupal_set_message("yyayy!" . microtime());
    if (empty($object) || $object->bundle() != $this->configuration['bundle']) {
      return;
    }

    $patterns = $this->parsePatterns();
    if (empty($patterns)) {
      return;
    }
    // get the uri
    $uri_field_list = $object->get($this->configuration['entry_uri_field']);
#    drupal_set_message(serialize($uri_field_list)); 
    $uri = NULL;
    if ($uri_field_list && $uri_field = $uri_field_list->first()) {
#      dpm($uri_field, "uf");
      $uri = $uri_field->get($uri_field::mainPropertyName())->getValue();
    }
    
    $old_uri = $uri;
#    dpm($uri, "uri");
    // if there is a URI and there are authority file and entry id the URI
    // will be overwritten.
    // get the authority file entity id
    $auth_file_field_id = $this->configuration['file_eid_field'];
#    dpm($auth_file_field_id, "yay!");
    $auth = NULL;
    if (!empty($auth_file_field_id)) {
      // we select a certain authority
      $auth_field_list = $object->get($auth_file_field_id);
      if ($auth_field_list && $auth_field = $auth_field_list->first()) {
        $auth = $auth_field->get($auth_field::mainPropertyName())->getValue();
      }
    }
    else {
      $auth = '*';
    }

    // due to overall smartness of users - do a trim.
    $auth = trim($auth);

#    dpm($auth, "auth");
#    dpm($patterns, "patty!");
    // check if we have some uri pattern for this authority file
    if (!empty($auth) && isset($patterns[$auth])) {
      // get the entry id
      $id_field_list = $object->get($this->configuration['entry_id_field']);
      if ($id_field_list && $id_field = $id_field_list->first()) {
        $id = $id_field->get($id_field::mainPropertyName())->getValue();
        
        // due to overall smartness of users - do a trim.
        $id = trim($id);
        
        // build the uri and add it to the entity
        if (!empty($id)) {
          $uri = str_replace('{id}', $id, $patterns[$auth]);
        }
      }
    }
#    dpm($uri);    
#    dpm($olduri); 
    // only write if:
    // either: uri is not empty but old uri is -> generate the uri because it was not filled by now
    // or: uri is not empty, old uri is not empty but they differ -> overwrite
    if ( (!empty($uri) && empty($olduri)) || ( !empty($uri) && !empty($olduri) && $olduri != $uri) ) {
      $uri = trim($uri);
      $object->set($this->configuration['entry_uri_field'], $uri);
      
      // write this
      $real_preferred = \Drupal\wisski_salz\AdapterHelper::getPreferredLocalStore(FALSE,TRUE);

      $engine = $real_preferred->getEngine();
      
      $pb = $engine->getPbsForThis();

      $bundle = $object->bundle();
      
      $entity_id = $object->id();

      $mainprop = "value";
      if(isset($uri_field))
        $mainprop = $uri_field::mainPropertyName();
      

#      dpm($bundle, "bun");

      $fv = array($this->configuration['entry_uri_field'] => array ( array($mainprop => $uri), "main_property" => $mainprop));


      $engine->writeFieldValues($entity_id, $fv, current($pb), $bundle);
      
#      dpm($object->id(), "bun");

#      dpm(serialize($real_preferred), "real");
#      dpm($real_preferred->getEngine()->getPbsForThis(), "real2");
      
#writeFieldValues($entity_id, array $field_values, $pathbuilder, $bundle_id=NULL,$old_values=array(),$force_new=FALSE, $initial_write = FALSE)
#      dpm($object->get(), "yay");
#      $object->save();
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

