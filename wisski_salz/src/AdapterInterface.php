<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\AdapterInterface.
 */

namespace Drupal\wisski_salz;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

/**
 * Provides an interface for defining WissKI Salz Adapter entities.
 *
 * This interface also defines delegator methods for easy access of the basic
 * methods of the underlying engine
 */
interface AdapterInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {
  
  
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

  
}
