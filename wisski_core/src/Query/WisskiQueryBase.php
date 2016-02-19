<?php

namespace Drupal\wisski_core\Query;

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
  
  /**
   * {@inheritdoc}
   */
  public function loadFieldValues($entity_id,$field_name,$language = LanguageInterface::LANGCODE_DEFAULT,$field_property = NULL) {

    if (isset($field_property)) {
      return 'Hello';
    }  
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
    return 'e21_person';
  }
}