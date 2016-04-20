<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\Entity\Adapter.
 */

namespace Drupal\wisski_salz\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\wisski_salz\AdapterInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\wisski_salz\EngineCollection;
use Psr\Log\LoggerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the WissKI Salz Adapter entity.
 * 
 * @ConfigEntityType(
 *   id = "wisski_salz_adapter",
 *   label = @Translation("WissKI Salz Adapter"),
 *   handlers = {
 *     "list_builder" = "Drupal\wisski_salz\AdapterListBuilder",
 *     "form" = {
 *       "add" = "Drupal\wisski_salz\Form\Adapter\AddForm",
 *       "edit" = "Drupal\wisski_salz\Form\Adapter\EditForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *   },
 *   config_prefix = "wisski_salz_adapter",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "description" = "description"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/wisski_salz/adapter/{wisski_salz_adapter}",
 *     "add-form" = "/admin/config/wisski_salz/adapter/add",
 *     "edit-form" = "/admin/config/wisski_salz/adapter/{wisski_salz_adapter}/edit",
 *     "delete-form" = "/admin/config/wisski_salz/adapter/{wisski_salz_adapter}/delete",
 *     "collection" = "/admin/config/wisski_salz/adapter"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "engine_id",
 *     "engine"
 *   }
 * )
 */
class Adapter extends ConfigEntityBase implements AdapterInterface {

  /**
   * The WissKI Salz Adapter ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The WissKI Salz Adapter label.
   *
   * @var string
   */
  protected $label;


  /**
   * A human-readable description of the adapter
   *
   * @var string
   */
  protected $description;


  /**
   * The engine id/type
   *
   * @var string
   */
  protected $engine_id;


  /**
   * An array with the engine configuration
   *
   * @var array
   */
  protected $engine = [];



  /**
   * The collection with the single engine
   *
   * @var EngineCollection
   */
  protected $engineCollection;

  
  
  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return array(
      'engine' => $this->getEngineCollection(),
    );
  }


  /** Returns the Engine Collection
   *
   * This is a convenience method.
   *
   * @return \Drupal\wisski_salz\EngineCollection
   */
  public function getEngineCollection() {
    if (!$this->engineCollection) {
      // DefaultSingleLazyPluginCollection expects the plugin instance id
      // to be identical to the plugin id.
      $this->engine['adapterId'] = $this->id();
      $this->engineCollection = new EngineCollection($this->getEngineManager(), $this->engine_id, $this->engine);
    }
    return $this->engineCollection;
  }


  /**
   * Returns the attribute manager.
   *
   * @return \Drupal\Component\Plugin\PluginManagerInterface
   *   The attribute manager.
   */
  public function getEngineManager() {
    return \Drupal::service('plugin.manager.wisski_salz_engine');
  }

  
  /**
   * {@inheritdoc}
   */
  public function getEngine() {
    return $this->getEngineCollection()->get($this->engine_id);
  }

  /**
   * {@inheritdoc}
   */
  public function setEngineConfig(array $configuration) {
    $this->engine = $configuration;
    $this->engine_id = $configuration['id'];
    $this->getEngineCollection()->setConfiguration($configuration);
  }
  
  public function setEngineId($id) {
    $this->engine_id = $id;
    $this->getEngineCollection()->addInstanceId($id);
  }

  public function getEngineId() {
    return $this->engine_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }


  /**
   * {@inheritdoc}
   */
  public function setDescription($d) {
    $this->description = trim($d);
  }


    
  
  /**
   * {@inheritdoc}
   */
  public function hasEntity($entity_id) {
    return $this->getEngine()->hasEntity($entity_id);
  }

  
  /**
   * {@inheritdoc}
   */
  public function loadFieldValues(array $entity_ids = NULL, array $field_ids = NULL, $language = LanguageInterface::LANGCODE_DEFAULT) {
    return $this->getEngine()->loadFieldValues($entity_ids, $field_ids, $language);
  }


  public function loadPropertyValuesForField($field_id, array $property_ids, $entity_ids = NULL, $language = LanguageInterface::LANGCODE_DEFAULT) {
    return $this->getEngine()->loadPropertyValuesForField($field_id, $property_ids, $entity_ids, $language);
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryObject(EntityTypeInterface $entity_type, $condition,array $namespaces) {
    return $this->getEngine()->getQueryObject($entity_type,$condition,$namespaces);
  }
  
  public function getBundleIdsForEntityId($entity_id) {
    return $this->getEngine()->getBundleIdForEntityId($entity_id);
  }
  
}
