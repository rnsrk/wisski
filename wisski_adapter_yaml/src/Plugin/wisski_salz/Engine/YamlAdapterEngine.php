<?php

/**
 * @file
 * Contains \Drupal\wisski_adapter_yaml\Plugin\wisski_salz\Engine\YamlAdapterEngine
 */

namespace Drupal\wisski_adapter_yaml\Plugin\wisski_salz\Engine;

use Drupal\wisski_adapter_yaml\Query\Query;
use Drupal\wisski_adapter_yaml\YamlAdapterBase;

use Drupal\wisski_pathbuilder\PathbuilderEngineInterface;

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
class YamlAdapterEngine extends YamlAdapterBase implements PathbuilderEngineInterface {

  private $entity_info;

  private function getEntityInfo($forID=NULL,$cached = TRUE) {

    if (!$cached || !isset($this->entity_info) || (isset($forID) && !isset($this->entity_info[$forID]))) {
      $this->entity_info = Yaml::parse($this->entity_string);
    }
    return $this->entity_info;
  }

  public function load($id) {
    $entity_info = $this->getEntityInfo($id);
    if (isset($entity_info[$id])) return $entity_info[$id];
    return array();
  }
  
  public function loadMultiple($ids = NULL) {
    $entity_info = $this->getEntityInfo(NULL,FALSE);
    if (is_null($ids)) return $entity_info;
    return array_intersect_key($entity_info,array_flip($ids));
  }
    
  /**
   * @inheritdoc
   */
  public function hasEntity($entity_id) {
  
    $ent = $this->load($entity_id);
    return empty($ent);
  }
  
  public function getPrimitiveMapping($step) {
    return "";
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
//    dpm(func_get_args(),__METHOD__);
    return new Query($entity_type,$condition,$namespaces,$this);
  }
  
  /**
   * Returns a list of possible steps between history of steps and future of
   * steps.
   *
   * @param history An array of the previous steps or an empty array if this is
   *  the beginning of the path.
   * @param future An array of the following steps or an empty array if this is 
   *  (currently!) the last step.
   *
   * @return array
   *  A list of steps
   *
   */
  public function getPathAlternatives($history = [], $future = []) {
  
    $entity_info = $this->getEntityInfo();
    $keys = array();
    foreach ($entity_info as $array) {
      foreach($history as $step) {
        if (is_array($array) && isset($array[$step])) $array = $array[$step];
      }
      if (is_array($array)) {
        foreach ($array as $key => $value) $keys[$key] = $key;
      }
    }
    dpm(func_get_args()+array('alternatives'=>$keys),__METHOD__);
    return $keys;
  }
  
  /**
   * Returns human readable information for a step.
   *
   * @param step the step
   *
   * @return an array with following keys
   *  label : a human readable label, translatable, one line
   *  description : a description of the step, translatable, multiline
   */
  public function getStepInfo($step, $history = [], $future = []) {
    dpm(func_get_args(),__METHOD__);
    return array();
  }

}