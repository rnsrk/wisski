<?php

/**
 * @file
 * Contains \Drupal\wisski_adapter_yaml\Plugin\wisski_salz\Engine\YamlAdapterEngine
 */

namespace Drupal\wisski_adapter_yaml\Plugin\wisski_salz\Engine;

use Drupal\wisski_adapter_yaml\YamlAdapterBase;
use Symfony\Component\Yaml\Yaml;

/**
 * @Engine(
 *   id = "wisski_adapter_dummy",
 *   name = @Translation("Wisski YAML Adapter"),
 *   description = @Translation("A WissKI adapter that parses a YAML-string for entity info")
 * )
 */
class YamlAdapterEngine extends YamlAdapterBase  {

  private static $entity_info;

  public function load($id) {
    $entity_info = &$this->entity_info;
    if (isset($entity_info[$id])) return $entity_info[$id];
    $entity_info = Yaml::parse($this->entity_string);
    if (isset($entity_info[$id])) return $entity_info[$id];
    return array();
  }
  
  public function loadMultiple($ids = NULL) {
    $this->entity_info = Yaml::parse($this->entity_string);
    if (is_null($ids)) return $this->entity_info;
    $entities = array();
    foreach($ids as $id) {
      $entities[$id] = $this->load($id);
    }
    return $entities;
  }
    
  /**
   * @inheritdoc
   */
  public function hasEntity($entity_id) {
  
    $ent = $this->load($entity_id);
    return empty($ent);
  }


  /**
   * @inheritdoc
   */
  public function loadFieldValues(array $entity_ids = NULL, array $field_ids = NULL, $language = LanguageInterface::LANGCODE_DEFAULT) {

    if (is_null($entity_ids)) return $this->loadMultiple();
    $result = array();
    foreach ($entity_ids as $entity_id) {
      $ent = $this->load($entity_id);
      if (!is_null($field_ids)) {
        $ent = array_diff_keys($ent,array_flip($field_ids));
      }
      $result[$entity_id] = $ent;
    }
    return $result;
  }

  /**
   * @inheritdoc
   * The Yaml-Adapter cannot handle field properties, we insist on field values being the main property
   */
  public function loadPropertyValuesForField($field_id, array $property_ids, array $entity_ids = NULL, $language = LanguageInterface::LANGCODE_DEFAULT) {
    
    
    return array();
  }
}