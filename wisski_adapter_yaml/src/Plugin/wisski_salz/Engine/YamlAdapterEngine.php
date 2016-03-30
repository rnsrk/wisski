<?php

/**
 * @file
 * Contains \Drupal\wisski_adapter_yaml\Plugin\wisski_salz\Engine\YamlAdapterEngine
 */

namespace Drupal\wisski_adapter_yaml\Plugin\wisski_salz\Engine;

use Drupal\wisski_adapter_yaml\Query\Query;

use Drupal\wisski_adapter_yaml\YamlAdapterBase;
use Symfony\Component\Yaml\Yaml;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * @Engine(
 *   id = "wisski_adapter_dummy",
 *   name = @Translation("Wisski YAML Adapter"),
 *   description = @Translation("A WissKI adapter that parses a YAML-string for entity info")
 * )
 */
class YamlAdapterEngine extends YamlAdapterBase  {

  private $entity_info;

  public function load($id) {
    $entity_info = &$this->entity_info;
    if (isset($entity_info[$id])) return $entity_info[$id];
    $entity_info = Yaml::parse($this->entity_string);
    if (isset($entity_info[$id])) return $entity_info[$id];
    return array();
  }
  
  public function loadMultiple($ids = NULL) {
    dpm($this->getConfiguration());
    $this->entity_info = Yaml::parse($this->entity_string);
    dpm($this->entity_info,__METHOD__);
    if (is_null($ids)) return $this->entity_info;
    return array_intersect_key($this->entity_info,array_flip($ids));
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

    if (is_null($entity_ids)) {
      $ents = $this->loadMultiple();
      if (is_null($field_ids)) return $ents;
      $field_ids = array_flip($field_ids);
      return array_map(function($array) use ($field_ids) {return array_intersect_key($array,$field_ids);},$ents);
    }
    $result = array();
    foreach ($entity_ids as $entity_id) {
      $ent = $this->load($entity_id);
      if (!is_null($field_ids)) {
        $ent = array_intersect_key($ent,array_flip($field_ids));
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
    
    
    $main_property = \Drupal\field\Entity\FieldStorageConfig::loadByName($entity_type, $field_name)->getItemDefinition()->mainPropertyName();
    if (in_array($main_property,$property_ids)) {
      return $this->loadFieldValues($entity_ids,array($field_id),$language);
    }
    return array();
  }
  
  public function getQueryObject(EntityTypeInterface $entity_type,$condition,array $namespaces) {
  
    return new Query($entity_type,$condition,$namespaces,$this);
  }
}