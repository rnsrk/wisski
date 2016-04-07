<?php

/**
 * @file
 * Contains Drupal\wisski_salz\EngineInterface.
 */

namespace Drupal\wisski_salz;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\wisski_salz\ExternalEntityInterface;
use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines an interface for external entity storage client plugins.
 */
interface EngineInterface extends PluginInspectionInterface, ConfigurablePluginInterface, PluginFormInterface  {
  
  
  /**
   * returns the ID of the adapter that this engine instance belongs to
   * @return the adapter ID
   */
  public function adapterId();

  
  /**
   * determines whether an entity with this ID exists in the storage
   * @param $entity_id the ID of the given entity
   * @return TRUE if the storage handles this entity, FALSE otherwise
   */
  public function hasEntity($entity_id);


  /**
   * Loads all field data for multiple entities.
   *
   * If there is no entity with a given ID handled by this adapter i.e. we got no information about it
   * there MUST NOT be an entry with that ID in the result array.
   *
   * Note that this function gets passed Drupal entity IDs.
   * The engine is responsible for doing whatever ID handling/mapping/managing
   * is necessary to guarantee stable, persistent Drupal IDs if the storage
   * type does not use Drupal IDs.
   * 
   * @param $entity_ids an array of the entity IDs.
   * @param $field_ids an array with the machine names of the fields to search the values for
   * @param $language language code for the desired translation
   * @return an array describing the values TODO: describe structure
   */
  public function loadFieldValues(array $entity_ids = NULL, array $field_ids = NULL, $language = LanguageInterface::LANGCODE_DEFAULT);

  
  /**
   * Loads property data for a given field for multiple entities.
   *
   * Note that this function gets passed Drupal entity IDs.
   * The engine is responsible for doing whatever ID handling/mapping/managing
   * is necessary to guarantee stable, persistent Drupal IDs if the storage
   * type does not use Drupal IDs.
   * 
   * retrieves the field data for the given entity IDs and field name
   * @param $field_id the machine name of the field to search the value for
   * @param $property_ids an array of specific sub-field property names e.g. value
   * @param $entity_ids an array of the entity IDs.
   * @param $language language code for the desired translation
   * @return an array describing the values TODO: describe structure
   */
  public function loadPropertyValuesForField($field_id, array $property_ids, array $entity_ids = NULL, $language = LanguageInterface::LANGCODE_DEFAULT);
  
  /**
   * returns an instance of this Adapter's Query Class
   * @param $conjunction thetype of condition conjunction used i.e. AND or OR
   * @return \drupal\wisski_salz\WisskiQueryInterface
   */
   public function getQueryObject(EntityTypeInterface $entity_type,$conjunction,array $namespaces);
}
