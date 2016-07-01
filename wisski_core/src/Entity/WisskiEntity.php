<?php

namespace Drupal\wisski_core\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

use Drupal\Core\Language\LanguageInterface;

use Drupal\wisski_core\WisskiEntityInterface;
 // * 		 "views_data" = "Drupal\views\EntityViewsData",
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
 *     "list_controller" = "Drupal\wisski_core\Controller\WisskiEntityListController",
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
 *     "label" = "label",
 *		 "preview_image" = "preview_image",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid"
 *   },
 *   bundle_entity_type = "wisski_bundle",
 *	 label_callback = "wisski_core_generate_title",
 *   permission_granularity = "entity_type",
 *   admin_permission = "administer wisski",
 *	 fieldable = TRUE,
 *   field_ui_base_route = "entity.wisski_individual.add",
 *   links = {
 *     "canonical" = "/wisski/navigate/{wisski_bundle}/{wisski_individual}",
 *     "delete-form" = "/wisski/navigate/{wisski_bundle}/{wisski_individual}/delete",
 *     "edit-form" = "/wisski/navigate/{wisski_bundle}/{wisski_individual}/edit",
 *     "admin-form" = "/admin/structure/wisski_core/manage/{wisski_bundle}"
 *   },
 *   translatable = FALSE,
 * )
 */
class WisskiEntity extends ContentEntityBase implements WisskiEntityInterface {
  //@TODO we have a 'name' entity key and don't know what to do with it. SPARQL adapter uses a 'Tempo Hack'
  //making it the same as 'eid'
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
    
    $fields['preview_image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Preview Image'))
      ->setDescription(t('A reference to an image file that is used as the preview image of the entity'))
      ->setSetting('target_type','file')
      ->setDefaultValue(NULL);
    
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

  public function getOriginalValues() {
    
    $out = array();
    foreach ($this->values as $field_name => $field_values) {
      $out[$field_name] = $field_values;
    }
    return $out;
  }
  
  public function getFieldDataTypes() {
    $types = array();

    // Gather a list of referenced entities.
    foreach ($this->getFields() as $field_name => $field_items) {
      foreach ($field_items as $field_item) {
        // Loop over all properties of a field item.
        foreach ($field_item->getProperties(TRUE) as $property_name => $property) {
          $types[$field_name][$property_name][] = get_class($property);
        }
      }
    }

    return $types;
  }
}