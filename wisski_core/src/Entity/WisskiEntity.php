<?php

namespace Drupal\wisski_core\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

use Drupal\Core\Language\LanguageInterface;

use Drupal\wisski_core\WisskiEntityInterface;

//keep for later use
// *		 "views_data" = "Drupal\wisski_core\WisskiEntityViewsData",


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
 *             "views_data" = "Drupal\wisski_core\WisskiEntityViewsData",
 *     "list_builder" = "Drupal\wisski_core\Controller\WisskiEntityListBuilder",
 *     "list_controller" = "Drupal\wisski_core\Controller\WisskiEntityListController",
 *     "form" = {
 *       "default" = "Drupal\wisski_core\Form\WisskiEntityForm",
 *       "edit" = "Drupal\wisski_core\Form\WisskiEntityForm",
 *	 		 "add" = "Drupal\wisski_core\Form\WisskiEntityForm",
 *	 		 "delete" = "Drupal\wisski_core\Form\WisskiEntityDeleteForm",
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
 *     "canonical" = "/wisski/navigate/{wisski_individual}",
 *     "delete-form" = "/wisski/navigate/{wisski_individual}/delete",
 *     "add-form" = "/wisski/create/{wisski_bundle}",
 *     "edit-form" = "/wisski/navigate/{wisski_individual}/edit",
 *     "admin-form" = "/admin/structure/wisski_core/manage/{wisski_bundle}",
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
    
    // TODO: wisski entities are not translatable. do we thus need the lang code?
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

  protected $original_values;
  
  public function saveOriginalValues($storage) {
  
    $this->original_values = $this->extractFieldData($storage);
  }

  public function getOriginalValues() {
    
    return $this->original_values;
  }
  
  public function getValues($storage,$save_field_properties=FALSE) {
    
    return array($this->extractFieldData($storage,$save_field_properties),$this->original_values);
  }
  
  protected function extractFieldData($storage,$save_field_properties=FALSE) {
#    dpm("calling extractfieldData with sfp: " . serialize($save_field_properties));
#    dpm(func_get_args(), "extractFieldData");
#    dpm($this, "this");
#    return array();
    $out = array();

    //$this is iterable itself, iterates over field list
    foreach ($this as $field_name => $field_item_list) {
#      dpm($field_name, "fieldname");
#      dpm($field_item_list, "fielditem");

      $out[$field_name] = array();
      if ($save_field_properties) {
        //clear the field values for this field in entity in bundle
        db_delete('wisski_entity_field_properties')
          ->condition('eid',$this->id())
          ->condition('bid',$this->bundle())
          ->condition('fid',$field_name)
          ->execute();
      }
      
      foreach($field_item_list as $weight => $field_item) {
        
        $field_values = $field_item->getValue();
        $field_def = $field_item->getFieldDefinition()->getFieldStorageDefinition();
        if (!empty($field_values) && method_exists($field_def,'getDependencies') && in_array('file',$field_def->getDependencies()['module'])) {
          //when loading we assume $target_id to be the file uri
          //this is a workaround since Drupal File IDs do not carry any information when not in drupal context
          if (!isset($field_values['target_id'])) {
#dpm(func_get_args(), __METHOD__.__LINE__);
            continue;
          }
          $field_values['target_id'] = $storage->getPublicUrlFromFileId($field_values['target_id']);
        }
        $main_property = $field_item->mainPropertyName();
        //we transfer the main property name to the adapters
        $out[$field_name]['main_property'] = $main_property;
        //gathers the ARRAY of field properties for each field list item
        //e.g. $out[$field_name][] = array(value => 'Hans Wurst', 'format' => 'basic_html');
        $out[$field_name][$weight] = $field_values;
        if ($save_field_properties && !empty($this->id())) {

          $fields_to_save = array(
            'eid' => $this->id(),
            'bid' => $this->bundle(),
            'fid' => $field_name,
            'delta' => $weight,
            'ident' => $field_values[$main_property], 
            // this formerly was in here
            // the problem however is that this could never be written, because we don't know what is the disamb...
            #isset($field_values['wisskiDisamb']) ? $field_values['wisskiDisamb'] : $field_values[$main_property],
            'properties' => serialize($field_values),
          );

#          dpm($fields_to_save, "fields to save");
          db_insert('wisski_entity_field_properties')
            ->fields($fields_to_save)
            ->execute();
        }
      }
      if (!isset($out[$field_name][0]) || empty($out[$field_name][0])) unset($out[$field_name]);
    }
#    dpm($out,__METHOD__);
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
  
  /**
   * Is the entity new? We cannot answer that question with certainty, so we always say NO unless we definitely know it better
   */
  public function isNew() {
  
    return !empty($this->enforceIsNew);
  }
  
}
