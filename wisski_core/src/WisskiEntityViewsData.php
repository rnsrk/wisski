<?php

namespace Drupal\wisski_core;

use Drupal\views\EntityViewsData;

// from Drupal\views\EntityViewsData
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Entity\Sql\TableMappingInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;


class WisskiEntityViewsData extends EntityViewsData {
  

  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static($entity_type);
  }

  
  public function __construct($entity_type) {
    $this->entityType = $entity_type;
  }



  public function getViewsData() {

    $data = [];
    $base_table = 'wisski_individual';
    
    // The $data array is documented in 
    // https://api.drupal.org/api/drupal/core%21modules%21views%21views.api.php/function/hook_views_data/8.2.x
    // documentation is quite sparse however and doesn't seem to be complete
    // 
    // the array has been taken from efq_views_backend and modified
    $data[$base_table]['table']['group'] = $this->entityType->getLabel();
    $data[$base_table]['table']['provider'] = $this->entityType->getProvider();
    
    // the base table
    // this is a virtual table as we do not intend to materialize it
    // we rather need this entry for specifying the custom views query engine.
    $data[$base_table]['table']['base'] = [
      'query_id' => 'wisski_individual_query',  // this is not documented on page, but is functional. replaces the standard views_query plugin
      'field' => 'eid', // this is documented but don't know if it has effects.
      'defaults' => [       // this is not documented and seems to be optional;
        'field' => 'eid',   // \Drupal\views\Plugin\views\wizard\WizardPluginBase uses it.
      ],
      'title' => $this->entityType->getLabel(),
      'cache_contexts' => $this->entityType->getListCacheContexts(),  // not documented, needed?
    ];

    // define the basic fields that every entity shares...
    $data[$base_table]['eid'] = [        // WizardPluginBase expects at least one field entry.
      'id' => 'eid',
      'title' => 'Entity Id',
      'field' => [          // the handler for views field (display!?) section, expected by WizardPluginBase
        'id' => 'standard'  // is standard the right thing here?
      ],
      'filter' => [         // the handler for views filter section
        'id' => 'wisski_field_numeric'
      ],
      'entity type' => $this->entityType->id(), // this should always be wisski_individual, used by WizardPluginBase
    ];
    $data[$base_table]['bundle'] = [ 
      'id' => 'bundle',
      'title' => 'Bundle/Group',
      'field' => [
        'id' => 'wisski_bundle'   // i think we need this extra handler
      ],
      'filter' => [
        'id' => 'wisski_bundle'   // i think we need this extra handler
      ],
      'entity type' => $this->entityType->id(),
    ];
    $data[$base_table]['title'] = [
      'id' => 'title',
      'title' => 'Title',
      'field' => [
        'id' => 'standard'  // is standard the right thing here?
      ],
      'filter' => [
        'id' => 'wisski_field_string'
      ],
      'entity type' => $this->entityType->id(),
    ];
    $data[$base_table]['preferred_uri'] = [
      'id' => 'preferred_uri',
      'title' => 'Preferred URI',
      'field' => [
        'id' => 'standard'  // TODO: whats the right handler?
      ],
      'filter' => [
        'id' => 'wisski_field_numeric'  // TODO: whats the right handler?
      ],
      'entity type' => $this->entityType->id(),
    ];

    // TODO: here should come a section where we read the paths from the pbs
    // and the fields from the bundles and add them here as views fields.
    // one could think about the pb module altering this data here and adding
    // the paths for itself rather than letting wisski_core handle pb stuff...


    return $data;

  }

  public function getViewsTableForEntityType(EntityTypeInterface $entity_type) {
    return 'wisski_individual';
  }
}
