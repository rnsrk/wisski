<?php

namespace Drupal\wisski_core\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

use Drupal\Core\Language\LanguageInterface;

use Drupal\wisski_core\WisskiEntityInterface;

/**
 * Defines the entity class.
 *
 * @ContentEntityType(
 *   id = "wisski_individual",
 *   label = @Translation("Wisski Entity"),
 *   bundle_label = @Translation("Wisski Bundle"),
 *   handlers = {
 *		 "storage" = "Drupal\wisski_core\WisskiStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\wisski_core\Controller\WisskiEntityListBuilder",
 *     "form" = {
 *       "default" = "Drupal\wisski_core\Form\WisskiEntityForm",
 *       "edit" = "Drupal\wisski_core\Form\WisskiEntityForm",
 *			 "add" = "Drupal\wisski_core\Form\WisskiEntityForm",
 *			 "delete" = "Drupal\wisski_core\Form\WisskiEntityDeleteForm",
 *     },
 *     "access" = "Drupal\wisski_core\Controller\WisskiEntityAccessHandler",
 *   },
 *   render_cache = TRUE,
 *   entity_keys = {
 *     "id" = "eid",
 *     "revision" = "vid",
 *     "bundle" = "bundle",
 *     "label" = "name",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid"
 *   },
 *   bundle_entity_type = "wisski_bundle",
 *   permission_granularity = "entity_type",
 *   admin_permission = "administer wisski",
 *	 fieldable = TRUE,
 *   field_ui_base_route = "entity.wisski_bundle.entity_add",
 *   links = {
 *     "canonical" = "/wisski_core/{wisski_individual}/view",
 *     "delete-form" = "/wisski_core/{wisski_individual}/delete",
 *     "edit-form" = "/wisski_core/{wisski_individual}/edit",
 *     "admin-form" = "/admin/structure/wisski_core/manage/{wisski_bundle}"
 *   },
 *   translatable = FALSE,
 * )
 */
class WisskiEntity extends ContentEntityBase implements WisskiEntityInterface {
  
  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
  
    $fields = array();
    
    $fields['eid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The ID of this entity.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('This entity\'s UUID.'))
      ->setReadOnly(TRUE);

    $fields['vid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Revision ID'))
      ->setDescription(t('The revision ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['bundle'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Bundle'))
      ->setDescription(t('The bundle.'))
      ->setSetting('target_type', 'wisski_bundle')
      ->setReadOnly(TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('Language code.'))
      ->setRevisionable(TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity name'))
      ->setDescription(t('The human readable name of this entity.'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => -5,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ))
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Creator ID'))
      ->setDescription(t('The user ID of the entity creator.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(0)
      ->setSetting('target_type', 'user')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', array(
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => array(
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ),
      ))
      ->setDisplayConfigurable('form', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the entity is published.'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE);
    
    return $fields;
  }

  public function tellMe() {
  
    $keys = func_get_args();
    $return = array();
    foreach ($keys as $key) {
      $field_name = $this->getEntityType()->getKey($key);
      $definition = $this->getFieldDefinition($field_name);
      $property = $definition->getFieldStorageDefinition()->getMainPropertyName();
      $value = $this->get($field_name)->$property;
      $return[$key] = array('key'=>$key,'field_name'=>$field_name,'property'=>$property,'value'=>$value);
    }
    return $return;
  }

#  public function id() {
#    
#    dpm($this->tellMe('id'));
#    dpm($this->tellMe('label'));
#    return 42;//parent::id();
#  }
}