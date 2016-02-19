<?php

namespace Drupal\wisski_core;

use Drupal\Core\Entity\ContentEntityStorageBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;

use Drupal\Core\Field\FieldDefinitionInterface;

use Drupal\wisski_core\Entity\WisskiEntity;
use Drupal\wisski_core\Query\WisskiQueryInterface;
//use Drupal\wisski_core\WisskiInvalidArgumentException;

/**
 * Test Storage that returns a Singleton Entity, so we can see what the FieldItemInterface does
 */
class WisskiStorage extends ContentEntityStorageBase implements WisskiStorageInterface {

  /**
   * stores the adpter names and classes used by this storage
   */
  private $adapters = array();
  
  /**
   * stores mappings from entity IDs to arrays of storages, that handle the id
   * and arrays of bundles the entity is in
   */
  private $entity_info = array();
  
  /**
   * adds a WisskiQueryInterface to the list of adapters
   * @param $machine_name the machine name of the adapter, must start with lower_case letter, followed by lower case letters or underscores
   * @param $override TRUE if old adapter info should be overrided
   */
  public function addAdapter($machine_name,$class_name,$override=FALSE) {
    
    if (!preg_match('/^[a-z][_a-z]*$/',$machine_name)) {
      throw new WisskiInvalidArgumentException(t('%machine_name is not a valid adapter name.',array('%machine_name'=>$machine_name)));
    }
    
    if (!class_exists($class_name)) {
      throw new WisskiInvalidArgumentException(t('%class is not a valid adapter class.',array('%class'=>$class_name)));
    }
    $adapter = new $class_name();
    if (!($adapter instanceof WisskiQueryInterface)) {
      throw new WisskiInvalidArgumentException(t('%class is not a valid WissiQuery Adapter',array('%class'=>$class_name)));
    }
    if (array_key_exists($this->adapters,$machine_name) && !$override) {
      return FALSE;
    }
    $this->adapters[$machine_name] = $adapter;
    return TRUE;
  }

  /**
   * !!! inherited from SqlContentEntityStorage. This should NEVER be called
   * since our class does not inherit from there.
   * This function is actually called by the Views module. 
   */
/*  public function getTableMapping(array $storage_definitions = NULL) {
  
    $definitions = $storage_definitions ? : \Drupal::getContainer()->get('entity.manager')->getFieldStorageDefinitions($this->entityTypeId);
    if (!empty($definitions)) {
      if (\Drupal::moduleHandler()->moduleExists('devel')) {
        dpm($definitions,__METHOD__);
      } else drupal_set_message('Non-empty call to '.__METHOD__);
    }
    return NULL;
  }
  */
  /**
   * {@inheritdoc}
   */
/*  public function loadMultiple(array $ids = NULL) {
    $ents = array();
    foreach ($ids as $id) {
      $ents[$id] = $this->load($id);
    }
    return $ents;
  }
*/

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $ids = NULL) {
    $field_definitions = $this->entityManager->getFieldStorageDefinitions('wisski_individual');
    $entities = array();
    foreach ($ids as $id) {
      $values = array();
      $info = $this->getEntityInfo($id);
      foreach ($info as $adapter_name => $bundles) {
        foreach ($field_definitions as $field_name => $def) {
          $values[$field_name] = $this->doLoadFieldItems($id,$field_name,$adapter_name);
        }
        foreach ($bundles as $bundle) {
          
        }
      }
//      if (!isset($values['id'])) $values['id'] = $id;
      if (is_array($values['bundle'])) $values['bundle'] = current($values['bundle']);
      $entities[$id] = $this->create($values);
    }
    return $entities;
  }

  /**
   * makes the adapter class load the entity information
   */
  protected function doLoadFieldItems($entity_id,$field_name,$adapter_name) {
    $adapter = $this->adapters[$adapter_name];
    return $adapter->loadFieldValues($id,$field_name);
  }

  /**
   * gets the adapter classes and bundles the entity is handled by
   * @param $id entity ID
   * @param $cached TRUE for static caching, FALSE for forced update
   * @return array keyed by adapter name containing bundle names
   */
  protected function getEntityInfo($id,$cached = TRUE) {
    
    $entity_info = &$this->entity_info;
    if (isset($entity_info[$id])) return $entity_info[$id];
    $entity_info[$id] = array();
    foreach ($this->adapters as $name => $adapter) {
      if ($adapter->hasEntity($id)) {
        $entity_info[$id][$name]['bundles'] = $adapter->getBundlesForEntity($id);
      }
    }
    return $entity_info[$id];
  }

  /**
   * {@inheritdoc}
   */
//  public function load($id) {
//    //@TODO load WisskiEntity here
//  }

  /**
   * {@inheritdoc}
   */
#  public function loadRevision($revision_id) {
#    return NULL;
#  }

  /**
   * {@inheritdoc}
   */
#  public function deleteRevision($revision_id) {
#  }

  /**
   * {@inheritdoc}
   */
#  public function loadByProperties(array $values = array()) {
#    
#    return array();
#  }

  /**
   * {@inheritdoc}
   */
#  public function delete(array $entities) {
#  }

  /**
   * {@inheritdoc}
   */
#  protected function doDelete($entities) {
#  }

  /**
   * {@inheritdoc}
   */
#  public function save(EntityInterface $entity) {
#
#    return parent::save($entity);
#  }

  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() {
    return 'entity.query.wisski_core';
  }

  /**
   * {@inheritdoc}
   * @TODO must be implemented
   */
  protected function doLoadRevisionFieldItems($revision_id) {
  }

  /**
   * {@inheritdoc}
   * @TODO must be implemented
   */
  protected function doSaveFieldItems(ContentEntityInterface $entity, array $names = []) {
  }

  /**
   * {@inheritdoc}
   * @TODO must be implemented
   */
  protected function doDeleteFieldItems($entities) {
  }

  /**
   * {@inheritdoc}
   * @TODO must be implemented
   */
  protected function doDeleteRevisionFieldItems(ContentEntityInterface $revision) {
  }

  /**
   * {@inheritdoc}
   * @TODO must be implemented
   */
  protected function readFieldItemsToPurge(FieldDefinitionInterface $field_definition, $batch_size) {
    return array();
  }

  /**
   * {@inheritdoc}
   * @TODO must be implemented
   */
  protected function purgeFieldItems(ContentEntityInterface $entity, FieldDefinitionInterface $field_definition) {
  }

  /**
   * {@inheritdoc}
   */
#  protected function doSave($id, EntityInterface $entity) {
#  }

  /**
   * {@inheritdoc}
   * @TODO must be implemented
   */
  protected function has($id, EntityInterface $entity) {
  }

  /**
   * {@inheritdoc}
   * @TODO must be implemented
   */
  public function countFieldData($storage_definition, $as_bool = FALSE) {
    //@TODO return the truth
    return $as_bool ? FALSE : 0;
  }

  /**
   * {@inheritdoc}
   */
#  public function hasData() {
#    return FALSE;
#  }  
}