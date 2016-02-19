<?php

namespace Drupal\wisski_core\Query;

use Drupal\Core\Language\LanguageInterface;

class WisskiQueryBase implements WisskiQueryInterface {

  /**
   * {@inheritdoc}
   */
  public function hasEntity($entity_id) {
    return ($entity_id == 42);
  }
  
  /**
   * {@inheritdoc}
   */
  public function getExternalID($entity_id) {
  
    return '42';
  }
  
  private $dummy_field_values = array(
    'bundle' => 'e21_person',
    'name' => 'Super Mario',
    'id' => 42,
  );
  
  /**
   * {@inheritdoc}
   */
  public function loadFieldValues($entity_id,$field_name,$language = LanguageInterface::LANGCODE_DEFAULT,$field_property = NULL) {

    if (isset($field_property)) {
      if (isset($this->dummy_field_values[$field_name])) return $this->dummy_field_values[$field_name];
      return 'Hello';
    }
    if (isset($this->dummy_field_values[$field_name])) return array('value'=>$this->dummy_field_values[$field_name]);
    return array('value'=>'Ciao');
  }
  
  /**
   * {@inheritdoc}
   */
  public function saveFieldValues($entity_id,$field_name,array $values) {
    
    return SAVED_NEW;
  }
  
  /**
   * {@inheritdoc}
   */
  public function hasFieldData($entity_id,$field_name,$field_property = NULL) {
    
    return ($entity_id == 42);
  }
  
  /**
   * {@inheritdoc}
   */
  public function getBundlesForEntity($entity_id) {
  
    //this is a hardcoded example bundle, should exist in your Drupal
    return array('e21_person');
  }
}