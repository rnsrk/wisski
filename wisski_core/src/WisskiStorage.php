<?php

namespace Drupal\wisski_core;

use Drupal\Core\Entity\ContentEntityStorageBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;

use Drupal\Core\Field\FieldDefinitionInterface;

use Drupal\wisski_core\Entity\WisskiEntity;
use Drupal\wisski_core\Query\WisskiQueryInterface;
//use Drupal\wisski_core\WisskiInvalidArgumentException;

use Drupal\Core\Field\BaseFieldDefinition;

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
    $entities = array();
    $values = $this->getEntityInfo($ids);
//  dpm($values,'values');    
    foreach ($ids as $id) {
//      if (is_array($values[$id]['bundle'])) $values[$id]['bundle'] = current($values[$id]['bundle']);
      //dummy fallback
      if (empty($values[$id]) && $id === 42) {
        $values[$id] = array(
          'bundle' => 'e21_person',
          'eid' => 42,
          'name' =>'There was nothing',
          'vid' => 42,
        );
      }
//      dpm($values[$id],__METHOD__."($id)");
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
  protected function getEntityInfo(array $ids,$cached = FALSE) {
    
    $field_definitions = $this->entityManager->getFieldStorageDefinitions('wisski_individual');
//    dpm($field_definitions,'field_storage_definitions');return array();
    $entity_info = &$this->entity_info;
    if ($cached) {
      $ids = array_diff_key($ids,$ntity_info);
      if (empty($ids)) return $entity_info;
    }
    $adapters = entity_load_multiple('wisski_salz_adapter');
#    dpm(serialize($adapters));
#    drupal_set_message("hallo welt");
    $info = array();
    foreach ($adapters as $aid => $adapter) {
//      if ($adapter->getEngineId() === 'sparql11_with_pb') continue;
      try {
        $adapter_info = $adapter->loadFieldValues($ids,array_keys($field_definitions));
//        dpm($adapter_info,"info from $aid");return array();
        foreach($adapter_info as $entity_id => $entity_values) {
          //if we don't know about that entity yet, this adapter's info can be used without a change
          if (!isset($info[$entity_id])) $info[$entity_id] = $entity_values;
          else {
            //integrate additional values on existing entities
            foreach($entity_values as $field_name => $value) {
              $actual_field_info = $info[$entity_id][$field_name];
              if ($field_definitions[$field_name] instanceof BaseFieldDefinition) {
                //this is a base field and cannot have multiple values
                //@TODO make sure, we load the RIGHT value
                if (!empty($actual_field_info) && $actual_field_info != $value) drupal_set_message(
                  $this->t('Multiple values for %field_name in entity %id: %val1, %val2',array(
                    '%field_name'=>$field_name,
                    '%id'=>$entity_id,
                    '%val1'=>is_array($actual_field_info)?implode($actual_field_info,', '):$actual_field_info,
                    '%val2'=>$value,
                  )),'error');
                else $actual_field_info = $value;
                continue;
              }
              //rest is a field
              $cardinality = $field_definitions[$field_name]->getCardinality();
              if ($cardinality === 1) {
                //this is a base field and cannot have multiple values
                //@TODO make sure, we load the RIGHT value
                if (!empty($actual_field_info) && $actual_field_info != $value) drupal_set_message(
                  $this->t('Multiple values for field %field_name in entity %id: %val1, %val2',array(
                    '%field_name'=>$field_name,
                    '%id'=>$entity_id,
                    '%val1'=>is_array($actual_field_info)?implode($actual_field_info,', '):$actual_field_info,
                    '%val2'=>$value,
                  )),'error');
                else $actual_field_info = $value;
                continue;
              }
              if (!is_array($actual_field_info)) $actual_field_info = array($actual_field_info);
              if ($cardinality > 0 && count($actual_field_info) >= $cardinality) {
                drupal_set_message(
                  $this->t('Too many values for field %field_name in entity %id. %card allowed. Tried to add %val2',array(
                    '%field_name'=>$field_name,
                    '%id'=>$entity_id,
                    '%card'=>$cardinality,
                    '%val1'=>$value, )),'error');
              } else $actual_field_info[] = $value;
              $info[$entity_id][$field_name] = $actual_field_info;
            }
          }
        }
        //dpm(array('adapter_info'=>$adapter_info,'entity_info_after'=>$info),$aid);
      } catch (\Exception $e) {
        drupal_set_message('Could not load entities in adapter '.$adapter->id() . ' because ' . serialize($e));
      }
    }
    $entity_info = WisskiHelper::array_merge_nonempty($entity_info,$info);
    dpm(func_get_args()+array('result'=>$entity_info),__METHOD__);
    return $entity_info;
  }

#  /**
#   * This function is called by the Views module.
#   */
#  public function getTableMapping(array $storage_definitions = NULL) {
#
#    $definitions = $storage_definitions ? : \Drupal::getContainer()->get('entity.manager')->getFieldStorageDefinitions($this->entityTypeId);
#    if (!empty($definitions)) {
#      if (\Drupal::moduleHandler()->moduleExists('devel')) {
#        dpm($definitions,__METHOD__);
#      } else drupal_set_message('Non-empty call to '.__METHOD__);
#    }
#    return NULL;
#  }

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