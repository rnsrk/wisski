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
    return $entites;
  }
}