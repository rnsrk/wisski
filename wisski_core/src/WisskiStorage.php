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
   * stores mappings from entity IDs to arrays of storages, that handle the id
   * and arrays of bundles the entity is in
   */
  private $entity_info = array();

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $ids = NULL) {
//  dpm($ids,__METHOD__);
    $field_definitions = $this->entityManager->getFieldStorageDefinitions('wisski_individual');
//  dpm($field_definitions,'field_storage_definitions');
    $entities = array();
    $values = $this->getEntityInfo($ids,array_keys($field_definitions));
//  dpm($values,'values');    
    foreach ($ids as $id) {
      if (is_array($values[$id]['bundle'])) $values[$id]['bundle'] = current($values[$id]['bundle']);
      //dummy fallback
      if (empty($values[$id]) && $id === 42) {
        $values[$id] = array(
          'bundle' => 'e21_person',
          'eid' => 42,
          'name' =>'There was nothing',
          'vid' => 42,
        );
      }
      if (!empty($values[$id])) $entities[$id] = $this->create($values[$id]);
    }
    return $entities;
  }

  /**
   * gathers entity field info from all available adapters
   * @param $id entity ID
   * @param $cached TRUE for static caching, FALSE for forced update
   * @return array keyed by entity id containing entity field info
   */
  protected function getEntityInfo(array $ids,array $fields,$cached = FALSE) {
    
    $entity_info = &$this->entity_info;
    if ($cached) {
      $ids = array_diff_key($ids,$ntity_info);
      if (empty($ids)) return $entity_info;
    }
    $adapters = entity_load_multiple('wisski_salz_adapter');
#    dpm(serialize($adapters));
#    drupal_set_message("hallo welt");
    $info = array();
    foreach ($adapters as $adapter) {
      try {
        $adapter_info = $adapter->getEngine()->loadFieldValues($ids,$fields);

        foreach($adapter_info as $entity_id => $entity_values) {

          if (!isset($info[$entity_id])) $info[$entity_id] = $entity_values;
          else {
            foreach($entity_values as $key => $value) {
              // if we already have that - continue
              if($value == $info[$entity_id][$key])
                continue; 

              // it might be that we could need array_merge_recursive here in case
              // of more complex data than just an array
              // @TODO: Check.
              else 
                if(!empty($entity_values[$key]))
                  $info[$entity_id][$key] = array_merge($info[$entity_id][$key],$entity_values[$key]);
            }
          }
        }
      } catch (\Exception $e) {
        drupal_set_message('Could not load entities in adapter '.$adapter->id() . ' because ' . serialize($e));
      }
    }
    $entity_info = array_merge($entity_info,$info);
#    dpm(func_get_args()+array('result'=>$entity_info),__METHOD__);
    return $entity_info;
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