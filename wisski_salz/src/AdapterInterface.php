<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\AdapterInterface.
 */

namespace Drupal\wisski_salz;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Provides an interface for defining WissKI Salz Adapter entities.
 *
 * This interface also defines delegator methods for easy access of the basic
 * methods of the underlying engine
 */
interface AdapterInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {
  
  /**
   * @return string
   *  The human-readable description of the adapter instance set by the GUI
   */
  public function getDescription();

  /**
   * Sets the description
   *
   * @param description a string with the description
   */
  public function setDescription($description);


  /**
   * @return string
   *  The ID of the adapter's engine 
   */
  public function getEngineId();


  /**
   * Sets the engine ID for this adapter
   *
   * @param id the engine ID
   */
  public function setEngineId($id);


  /**
   * @return \Drupal\wisski_salz\EngineInterface
   *  The engine used by this adapter
   */
  public function getEngine();
  

  /**
   * Sets the configuration for the adapter's engine
   *
   * @param array the configuration
   */
  public function setEngineConfig(array $configuration);

  /**
   * @return \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection
   *  The plugin collection with the single engine
   */
  public function getEngineCollection();


  /**
   * @see EngineInterface::hasEntity()
   */
  public function hasEntity($entity_id);

  
  /**
   * @see EngineInterface::loadFieldValues()
   */
  public function loadFieldValues(array $entity_ids = NULL, array $field_ids = NULL, $language = LanguageInterface::LANGCODE_DEFAULT);

  
  /**
   * @see EngineInterface::loadFieldValues()
   */
  public function loadPropertyValuesForField($field_id, array $property_ids, $entity_ids = NULL, $language = LanguageInterface::LANGCODE_DEFAULT);

  /**
   * returns an instance of this Adapter's Query Class
   * @param $conjunction thetype of condition conjunction used i.e. AND or OR
   * @return \drupal\wisski_salz\WisskiQueryInterface
   */
  public function getQueryObject(EntityTypeInterface $entity_type, $condition,array $namespaces);
  
  /**
   * @see EngineInterface::getBundleIdsForEntityId
   */
  public function getBundleIdsForEntityId($entity_id);

  /**
   * @see EngineInterface::doYouKnowEntityId
   */
  public function doYouKnowEntityId($entity_id);

}
