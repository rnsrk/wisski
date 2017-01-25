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
  
#  public function __construct($entity_type) {
#    parent::__construct($entity_type, (SqlEntityStorageInterface) NULL, NULL, NULL, NULL);
#  }


  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static($entity_type);
  }

  
  public function __construct($entity_type) {
    $this->entityType = $entity_type;
  }



  public function getViewsData() {

    $data = [];
    $base_table = 'wisski_title_n_grams';
    
    // The $data array is documented in 
    // https://api.drupal.org/api/drupal/core%21modules%21views%21views.api.php/function/hook_views_data/8.2.x
    // documentation is quite sparse however and doesn't seem to be complete
    // 
    // the array has been taken from efq_views_backend and modified
    $data[$base_table]['table']['group'] = $this->entityType->getLabel();
    $data[$base_table]['table']['provider'] = $this->entityType->getProvider();

    $data[$base_table]['table']['base'] = [
      'query_id' => 'wisski_individual_query',  // this is not documented on page, but is functional. replaces the standard views_query plugin
      'field' => 'ent_num', // this is documented but don't know if it has effects.
      'defaults' => [       // this is not documented and seems to be optional;
        'field' => 'ent_num',   // \Drupal\views\Plugin\views\wizard\WizardPluginBase uses it.
      ],
      'title' => $this->entityType->getLabel(),
      'cache_contexts' => $this->entityType->getListCacheContexts(),  // not documented, needed?
    ];
    $data[$base_table]['ent_num'] = [        // WizardPluginBase expects at least one field entry.
      'id' => 'ent_num',
      'title' => 'Entity Id',
      'field' => [          // the handler for views field (display!?) section, expected by WizardPluginBase
        'id' => 'standard'  // is standard the right thing here?
      ],
      'filter' => [         // the handler for views filter section
        'id' => 'numeric'
      ],
      'entity type' => $this->entityType->id(), // this should always be wisski_individual, used by WizardPluginBase
    ];

    return $data;

  }

  public function getViewsTableForEntityType(EntityTypeInterface $entity_type) {
    return 'wisski_title_n_grams';
  }
}
