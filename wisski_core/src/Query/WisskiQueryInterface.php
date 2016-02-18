<?php

namespace Drupal\wisski_core\Query;

use Drupal\Core\Language\LanguageInterface;

/**
 * Interface that defines functions for internal and external storage adapters.
 * Those must be able to CRUD basic entity info like the external ID i.e. for example a URI in triple stores or 
 * a line number in CSVs.
 * Furthermore, the Adapter must be able to load and save field values for special entities
 */
interface WisskiQueryInterface {

  /**
   * the saved entry was new to the adapter
   */
  const SAVED_NEW = 'SAVED_NEW';
  
  /**
   * the saved entry was known before
   */
  const SAVED_UPDATED = 'SAVED_UPDATED';

  /**
   * an error ocurred when saving the entry
   */
  const ERROR_ON_SAVE = 'ERROR_ON_SAVE';  

  /**
   * determines whether an entity with this ID exists in the storage
   * @param $entity_id the ID of the given entity
   * @return TRUE if the storage handles this entity, FALSE otherwise
   */
  public function hasEntity($entity_id);
  
  /**
   * loads the entity with the given ID
   * @param $entity_id the internal i.e. Drupal-specific Entity ID
   * @return the external i.e. Adapter-Specific Entity ID
   */
  protected function getExternalID($entity_id);
  
  /**
   * retrieves the field data for the given entity ID and field name
   * @param $entity_id the ID of the given entity
   * @param $field_name the machine name of the field to search the value for
   * @param $language language code for the desired translation
   * @param $field_propery a specific sub-field property name e.g. value, if NULL, all data for the field is loaded
   * @return String with single sub-field-level property value or array of such keyed by property
   */
  public function loadFieldValues($entity_id,$field_name,$language = LanguageInterface::LANGCODE_DEFAULT,$field_property = NULL);
  
  /**
   * writes the field data forthe given entity ID and field name
   * @param $entity_id the ID of the given entity
   * @param $field_name the machine name of the field to search the value for
   * @param $values array of field data keyed by langcode and (sub-field-level) property
   * @return SAVED_NEW or SAVED_UPDATED or ERROR_ON_SAVE
   */
  public function saveFieldValues($entity_id,$field_name,array $values);
  
  /**
   * determines whether there exists field data for the given entity ID and field name
   * @param $entity_id the ID of the given entity
   * @param $field_name the machine name of the field to search the value for
   * @param $field_propery a specific sub-field property name e.g. value, if NULL, we check for ANY data for that entity and field
   * @return TRUE if the adapter knows about that field data, FALSE otherwise
   */
  public function hasFieldData($entity_id,$field_name,$field_property = NULL);
  
  /**
   * determines the bundle for the given entity
   * @param $entity_id the ID of the given entity
   * @return array of the Drupal machine-names of the bundles that this entity is contained in, empty if there is no such bundle
   */
  public function getBundlesForEntity($entity_id);
}