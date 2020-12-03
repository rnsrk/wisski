<?php

namespace Drupal\wisski_core\Form;

//use \Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Field\BaseFieldDefinition;
use \Drupal\Core\Form\FormStateInterface;
use \Drupal\Core\Entity\ContentEntityForm;
use \Drupal\wisski_core;
use \Drupal\wisski_salz\AdapterHelper;
use \Drupal\Core\Entity\RevisionableContentEntityBase;
use \Drupal\Core\Entity\Sql\DefaultTableMapping;
use \Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

class WisskiEntityForm extends ContentEntityForm
{

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form = parent::buildForm($form, $form_state);
    $form['#title'] = $this->t('Edit') . ' ' . wisski_core_generate_title($this->entity, NULL, TRUE);

    // this code here is evil!!!
    // whenever you have subentities (referenced by entity reference)
    // no new ids are generated because it takes the oldest one in store (e.g. 12) and simply adds one (= 13).
    // this is nonsense because it does this for all new ones, so everything is 13 after this.
    // We really don't know the id we will be getting - so stop this here!
    /*
    if (empty($this->entity->id())) {
      $fresh_id = AdapterHelper::getFreshDrupalId();
      $this->entity->set('eid',$fresh_id);
      //dpm($this->entity->id(),'set '.$fresh_id);
    }
    */
#    dpm($form,__METHOD__);
    $this->entity->saveOriginalValues($this->entityTypeManager->getStorage('wisski_individual'));
    //@TODO extend form
    //dpm($this->getEntity());
#    dpm($form,__METHOD__);

    // hard code revisionflag
    //$this->entity->getEntityType()->set('show_revision_ui', true);
    //dpm($this->entity);
    //dpm($this->entity->getRevisionUserId());
    if ($this->showRevisionUi()) {
      $this->addRevisionableFormFields($form);
    }//dpm($this->entityTypeManager->getStorage('wisski_individual'), 'vlt storage');
    ;
    foreach ($this->entity as $field_name => $field_item_list) {
      //dpm($field_name);
      //dpm($this->entity->get($field_name)->getValue(), $field_name);
      //dpm($this->entity->get('eid')->getValue()[0]['value'], 'eid');
    }



    dpm($this->entity);
    return $form;
  }

  public function save(array $form, FormStateInterface $form_state)
  {
#    dpm($form, "form");
#    dpm($form_state, "fs");
    $entity = $this->getEntity();

    $this->copyFormValuesToEntity($entity, $form, $form_state);
    $entity->save();
    $bundle = $entity->get('bundle')->getValue()[0]['target_id'];
    $drupalid = $entity->id();
#    $drupalid = AdapterHelper::getDrupalIdForUri($entity->id());
#    dpm($bundle,__METHOD__);
    $form_state->setRedirect(
      'entity.wisski_individual.canonical',
#      'entity.wisski_individual.view',
      array(
        'wisski_bundle' => $bundle,
        'wisski_individual' => $drupalid,
      )
    );
    /*
    $fields = array();
    $query2 = \Drupal::database()->insert('wisski_revision')
      ->fields(array('eid', 'bid', 'fid', 'delta', 'ident', 'properties'));
    $query2->execute();
    //$entity->setNewRevision();
    */

    $entity_type_id = $entity->getEntityType()->id();
    $field_manager = \Drupal::service('entity_field.manager');
    $field_manager->useCaches(FALSE);
    $storage_definitions = $field_manager->getFieldStorageDefinitions($entity_type_id);
    $tableMapping = DefaultTableMapping::create($entity->getEntityType(), $storage_definitions);
    dpm($tableMapping, tableMapping);

    /*foreach ($tableMapping->getDedicatedTableNames() as $name) {
      if (strpos($name, 'wisski_individual_r') !== FALSE)  {
        $table = $tableMapping->getExtraColumns($name);
        \Drupal::database()
          ->schema()
          ->createTable($name, $table);
      }
    }*/

    $this->entity->setNewRevision();
  }
  protected function getDedicatedTableSchema(\Drupal\Core\Field\FieldStorageDefinitionInterface $storage_definition, \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type = NULL) {
    $entity_type = $entity_type ?: $this->entityType;
    $description_current = "Data storage for {$storage_definition->getTargetEntityTypeId()} field {$storage_definition->getName()}.";
    $description_revision = "Revision archive storage for {$storage_definition->getTargetEntityTypeId()} field {$storage_definition->getName()}.";
    $id_definition = $this->fieldStorageDefinitions[$entity_type
      ->getKey('id')];
    if ($id_definition
        ->getType() == 'integer') {
      $id_schema = [
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
        'description' => 'The entity id this data is attached to',
      ];
    }
    else {
      $id_schema = [
        'type' => 'varchar_ascii',
        'length' => 128,
        'not null' => TRUE,
        'description' => 'The entity id this data is attached to',
      ];
    }

    // Define the revision ID schema.
    if (!$entity_type
      ->isRevisionable()) {
      $revision_id_schema = $id_schema;
      $revision_id_schema['description'] = 'The entity revision id this data is attached to, which for an unversioned entity type is the same as the entity id';
    }
    elseif ($this->fieldStorageDefinitions[$entity_type
        ->getKey('revision')]
        ->getType() == 'integer') {
      $revision_id_schema = [
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
        'description' => 'The entity revision id this data is attached to',
      ];
    }
    else {
      $revision_id_schema = [
        'type' => 'varchar',
        'length' => 128,
        'not null' => TRUE,
        'description' => 'The entity revision id this data is attached to',
      ];
    }
    $data_schema = [
      'description' => $description_current,
      'fields' => [
        'bundle' => [
          'type' => 'varchar_ascii',
          'length' => 128,
          'not null' => TRUE,
          'default' => '',
          'description' => 'The field instance bundle to which this row belongs, used when deleting a field instance',
        ],
        'deleted' => [
          'type' => 'int',
          'size' => 'tiny',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'A boolean indicating whether this data item has been deleted',
        ],
        'entity_id' => $id_schema,
        'revision_id' => $revision_id_schema,
        'langcode' => [
          'type' => 'varchar_ascii',
          'length' => 32,
          'not null' => TRUE,
          'default' => '',
          'description' => 'The language code for this data item.',
        ],
        'delta' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'description' => 'The sequence number for this data item, used for multi-value fields',
        ],
      ],
      'primary key' => [
        'entity_id',
        'deleted',
        'delta',
        'langcode',
      ],
      'indexes' => [
        'bundle' => [
          'bundle',
        ],
        'revision_id' => [
          'revision_id',
        ],
      ],
    ];

    // Check that the schema does not include forbidden column names.
    $schema = $storage_definition
      ->getSchema();
    $properties = $storage_definition
      ->getPropertyDefinitions();
    $table_mapping = $this
      ->getTableMapping($entity_type, [
        $storage_definition,
      ]);
    if (array_intersect(array_keys($schema['columns']), $table_mapping
      ->getReservedColumns())) {
      throw new FieldException("Illegal field column names on {$storage_definition->getName()}");
    }

    // Add field columns.
    foreach ($schema['columns'] as $column_name => $attributes) {
      $real_name = $table_mapping
        ->getFieldColumnName($storage_definition, $column_name);
      $data_schema['fields'][$real_name] = $attributes;

      // A dedicated table only contain rows for actual field values, and no
      // rows for entities where the field is empty. Thus, we can safely
      // enforce 'not null' on the columns for the field's required properties.
      // Fields can have dynamic properties, so we need to make sure that the
      // property is statically defined.
      if (isset($properties[$column_name])) {
        $data_schema['fields'][$real_name]['not null'] = $properties[$column_name]
          ->isRequired();
      }
    }

    // Add indexes.
    foreach ($schema['indexes'] as $index_name => $columns) {
      $real_name = $this
        ->getFieldIndexName($storage_definition, $index_name);
      foreach ($columns as $column_name) {

        // Indexes can be specified as either a column name or an array with
        // column name and length. Allow for either case.
        if (is_array($column_name)) {
          $data_schema['indexes'][$real_name][] = [
            $table_mapping
              ->getFieldColumnName($storage_definition, $column_name[0]),
            $column_name[1],
          ];
        }
        else {
          $data_schema['indexes'][$real_name][] = $table_mapping
            ->getFieldColumnName($storage_definition, $column_name);
        }
      }
    }

    // Add unique keys.
    foreach ($schema['unique keys'] as $index_name => $columns) {
      $real_name = $this
        ->getFieldIndexName($storage_definition, $index_name);
      foreach ($columns as $column_name) {

        // Unique keys can be specified as either a column name or an array with
        // column name and length. Allow for either case.
        if (is_array($column_name)) {
          $data_schema['unique keys'][$real_name][] = [
            $table_mapping
              ->getFieldColumnName($storage_definition, $column_name[0]),
            $column_name[1],
          ];
        }
        else {
          $data_schema['unique keys'][$real_name][] = $table_mapping
            ->getFieldColumnName($storage_definition, $column_name);
        }
      }
    }

    // Add foreign keys.
    foreach ($schema['foreign keys'] as $specifier => $specification) {
      $real_name = $this
        ->getFieldIndexName($storage_definition, $specifier);
      $data_schema['foreign keys'][$real_name]['table'] = $specification['table'];
      foreach ($specification['columns'] as $column_name => $referenced) {
        $sql_storage_column = $table_mapping
          ->getFieldColumnName($storage_definition, $column_name);
        $data_schema['foreign keys'][$real_name]['columns'][$sql_storage_column] = $referenced;
      }
    }
    $dedicated_table_schema = [
      $table_mapping
        ->getDedicatedDataTableName($storage_definition) => $data_schema,
    ];

    // If the entity type is revisionable, construct the revision table.
    if ($entity_type
      ->isRevisionable()) {
      $revision_schema = $data_schema;
      $revision_schema['description'] = $description_revision;
      $revision_schema['primary key'] = [
        'entity_id',
        'revision_id',
        'deleted',
        'delta',
        'langcode',
      ];
      $revision_schema['fields']['revision_id']['not null'] = TRUE;
      $revision_schema['fields']['revision_id']['description'] = 'The entity revision id this data is attached to';
      $dedicated_table_schema += [
        $table_mapping
          ->getDedicatedRevisionTableName($storage_definition) => $revision_schema,
      ];
    }
    return $dedicated_table_schema;
  }
}


