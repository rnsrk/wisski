<?php

namespace Drupal\wisski_core;

use Drupal\views\EntityViewsData;

// From Drupal\views\EntityViewsData.
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class WisskiEntityViewsData extends EntityViewsData {

  /**
   *
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static($entity_type);
  }

  /**
   *
   */
  public function __construct($entity_type) {
    $this->entityType = $entity_type;
  }

  /**
   *
   */
  public function getViewsData() {

    $data = [];
    $base_table = 'wisski_individual';

    // dpm($this->entityType->id(), "id!");.
    // The $data array is documented in
    // https://api.drupal.org/api/drupal/core%21modules%21views%21views.api.php/function/hook_views_data/8.2.x
    // documentation is quite sparse however and doesn't seem to be complete
    //
    // the array has been taken from efq_views_backend and modified.
    $data[$base_table]['table']['group'] = $this->entityType->getLabel();
    $data[$base_table]['table']['provider'] = $this->entityType->getProvider();

    // This is needed by some views plugins. e.g. for the rest module.
    $data[$base_table]['table']['entity type'] = $this->entityType->id();
    // From Drupal\views\Plugin\views\query\QueryPluginBase->getEntityTableInfo():
    // "A boolean that specifies whether the table is a base table or
    // a revision table of the entity type.".
    $data[$base_table]['table']['entity revision'] = FALSE;

    // The base table
    // this is a virtual table as we do not intend to materialize it
    // we rather need this entry for specifying the custom views query engine.
    $data[$base_table]['table']['base'] = [
    // This is not documented on page, but is functional. replaces the standard views_query plugin.
      'query_id' => 'wisski_individual_query',
    // This is documented but don't know if it has effects.
      'field' => 'eid',
    // This is not documented and seems to be optional;.
      'defaults' => [
    // \Drupal\views\Plugin\views\wizard\WizardPluginBase uses it.
        'field' => 'eid',
      ],
      'title' => $this->entityType->getLabel(),
      // Not documented, needed?
      'cache_contexts' => $this->entityType->getListCacheContexts(),
    ];

    // Define the basic fields that every entity shares...
    // WizardPluginBase expects at least one field entry.
    $data[$base_table]['eid'] = [
      'id' => 'eid',
      'title' => 'Entity Id',
    // The handler for views field (display!?) section, expected by WizardPluginBase.
      'field' => [
    // Is standard the right thing here?
        'id' => 'standard',
      ],
      // The handler for views filter section.
      'filter' => [
        'id' => 'wisski_field_numeric',
      ],
      'argument' => [
        'id' => 'wisski_entity_id',
      ],
      // This should always be wisski_individual, used by WizardPluginBase.
      'entity type' => $this->entityType->id(),
    ];
    $data[$base_table]['bundle'] = [
      'id' => 'bundle',
      'title' => 'Bundle/Group',
      'field' => [
    // Is standard the right thing here?
        'id' => 'standard',
      ],
      'filter' => [
      // I think we need this extra handler.
        'id' => 'wisski_bundle',
      ],
      'argument' => [
        'id' => 'wisski_bundle',
      ],
      'entity type' => $this->entityType->id(),
    ];
    $data[$base_table]['bundle_label'] = [
      'id' => 'bundle_label',
      'title' => 'Bundle/Group Label',
      'field' => [
    // Is standard the right thing here?
        'id' => 'standard',
      ],
      'entity type' => $this->entityType->id(),
    ];
    $data[$base_table]['bundles'] = [
      'id' => 'bundles',
      'title' => 'Bundles/Groups',
      'field' => [
    // Is standard the right thing here?
        'id' => 'standard',
      ],
      'entity type' => $this->entityType->id(),
    ];
    $data[$base_table]['title'] = [
      'id' => 'title',
      'title' => 'Title',
      'field' => [
    // 'standard', // is standard the right thing here?
        'id' => 'wisski_title',
      ],
      'filter' => [
        'id' => 'wisski_field_string',
      ],
          // 'sort' => [
          // 'id' => 'standard'
          // ],.
      'entity type' => $this->entityType->id(),
    ];
    $data[$base_table]['preferred_uri'] = [
      'id' => 'preferred_uri',
      'title' => 'Preferred URI',
      'field' => [
    // TODO: whats the right handler?
        'id' => 'standard',
      ],
      'filter' => [
          // TODO: whats the right handler?
        'id' => 'wisski_field_string',
      ],
      'entity type' => $this->entityType->id(),
    ];
    $data[$base_table]['preview_image'] = [
      'id' => 'preview_image',
      'title' => 'Preview Image',
      'field' => [
        // 'id' => 'wisski_field',  // is standard the right thing here?
        'id' => 'wisski_preview_image',
        'entity_type' => $this->entityType->id(),
        'entity field' => 'preview_image',
      ],
      'filter' => [
        'id' => 'wisski_field_string',
      ],
      'entity type' => $this->entityType->id(),
      'entity_type' => $this->entityType->id(),
    ];

    // TODO: here should come a section where we read the paths from the pbs
    // and the fields from the bundles and add them here as views fields.
    // one could think about the pb module altering this data here and adding
    // the paths for itself rather than letting wisski_core handle pb stuff...
    $moduleHandler = \Drupal::service('module_handler');
    if (!$moduleHandler->moduleExists('wisski_pathbuilder')) {
      return NULL;
    }
    $field_storage_def = \Drupal::entityManager()->getFieldStorageDefinitions('wisski_individual');
    $fdef_for_bundle = [];

    // This has to be defined to use parent-functions.
    $this->entityManager = \Drupal::entityManager();

    $pbs = entity_load_multiple('wisski_pathbuilder');
    foreach ($pbs as $pbid => $pb) {
      // We load all paths. then we go though the top groups
      // paths that haven't been handled in a group will be added below.
      $orphaned_paths = $pb->getAllPaths();
      $groups = $pb->getMainGroups();
      foreach ($groups as $gid => $group) {
        $paths = $pb->getAllPathsForGroupId($gid, TRUE);
        foreach ($paths as $path) {
          $pid = $path->id();
          // We are gonna handle this path, so it's not orphaned.
          unset($orphaned_paths[$pid]);
          if (!$path->isGroup()) {

            $fieldid = NULL;
            $pbpath = $pb->getPbPath($path->id());
            if (!empty($pbpath)) {
              $fieldid = $pbpath['field'];
            }

            // If there is no fieldid or it is create no field - do nothing!
            if (empty($fieldid) || $fieldid == "1ae353e47a8aa3fc995220848780758a") {
              // The warning is left here only for debug purpose.
              continue;
              drupal_set_message("Path " . $path->getName() . " has no field definition.", "warning");
            }

            $field = NULL;

            if (isset($field_storage_def[$fieldid])) {
              $field = $field_storage_def[$fieldid];
            }

            $standard_values = [];

            if (!empty($field)) {
              // dpm($field->getType(), "fn");.
              $bundleid = $pbpath['bundle'];

              // dpm($bundleid, "bun");
              // dpm(\Drupal::entityManager()->getFieldDefinitions('wisski_individual',$bundleid)[$fieldid], "fdef");
              // .
              if (!isset($fdef_for_bundle[$bundleid])) {
                $fdef_for_bundle[$bundleid] = \Drupal::entityManager()->getFieldDefinitions('wisski_individual', $bundleid);
              }

              if (isset($fdef_for_bundle[$bundleid])) {
                if (isset($fdef_for_bundle[$bundleid][$fieldid])) {
                  $fdef = $fdef_for_bundle[$bundleid][$fieldid];

                  if (isset($fdef)) {

                    // $fdef = \Drupal::entityManager()->getFieldDefinitions('wisski_individual',$bundleid)[$fieldid];
                    // $this->entityManager = \Drupal::entityManager();
                    // dpm($field_storage_def[$fieldid], "yay");.
                    $standard_values = $this->mapSingleFieldViewsData($data, $fieldid, $field->getType(), $fieldid, $field->getType(), TRUE, $fdef);
                    // dpm($standard_values, "val");.
                  }
                }
              }
            }

            // Begin with the standard values... it does not hurt if there are no...
            $data[$base_table]["wisski_path_${pbid}__$pid"] = $standard_values;

            // Override this
            // It would have been brilliant if we could combine both pb ID
            // and path ID by a dot for forming the field's ID as wisski
            // entity query encodes paths like that. But Drupal views does
            // not allow dots... :(.
            $data[$base_table]["wisski_path_${pbid}__$pid"]['id'] = "wisski_path_{$pbid}__$pid";

            // Override the title.
            $data[$base_table]["wisski_path_${pbid}__$pid"]['title'] = $this->t(
            "@group -> @path (@id) in @pb", [
              "@group" => $group->getName(),
              "@path" => $path->getName(),
              "@id" => $pid,
              "@pb" => $pb->getName(),
            ]
            );

            // Override the field-properties, as we do know better, what to do with them...
            $data[$base_table]["wisski_path_${pbid}__$pid"]['field'] = [
            // 'wisski_standard',.
              'id' => 'wisski_entityfield',
              'field_name' => $fieldid,
              'entity_type' => $this->entityType->id(),
              'wisski_field' => "$pbid.$pid",
            ];
            // dpm($data[$base_table]["wisski_path_${pbid}__$pid"], "filter!!! $pid");
            // override this only if the standard did not set something or it has set "standard" which seems to be stupid.
            if (!isset($data[$base_table]["wisski_path_${pbid}__$pid"]['filter']['id'])
            || $data[$base_table]["wisski_path_${pbid}__$pid"]['filter']['id'] == "standard"
            || $data[$base_table]["wisski_path_${pbid}__$pid"]['filter']['id'] == "string"
            // Special case for entity reference, because we dont want to have int filter there...
            || (isset($data[$base_table]["wisski_path_${pbid}__$pid"]["relationship"]) && $data[$base_table]["wisski_path_${pbid}__$pid"]["relationship"]["base"] == "wisski_individual" && $data[$base_table]["wisski_path_${pbid}__$pid"]["relationship"]["base field"] == "eid")
            ) {
              $data[$base_table]["wisski_path_${pbid}__$pid"]['filter']['id'] = 'wisski_field_string';
            }
            $data[$base_table]["wisski_path_${pbid}__$pid"]['filter']['pb'] = $pbid;
            $data[$base_table]["wisski_path_${pbid}__$pid"]['filter']['path'] = $pid;
            $data[$base_table]["wisski_path_${pbid}__$pid"]['filter']['wisski_field'] = "$pbid.$pid";

            // Override this only if the standard did not set something.
            if (!isset($data[$base_table]["wisski_path_${pbid}__$pid"]['sort']['id'])) {
              $data[$base_table]["wisski_path_${pbid}__$pid"]['sort']['id'] = 'standard';
            }
            // dpm(serialize($data[$base_table]["wisski_path_${pbid}__$pid"]['argument']['id']), $pbid.$pid);
            // override this only if the standard did not set something.
            if (!isset($data[$base_table]["wisski_path_${pbid}__$pid"]['argument']['id'])) {
              $data[$base_table]["wisski_path_${pbid}__$pid"]['argument']['id'] = 'wisski_string';
            }
            // dpm(serialize($data[$base_table]["wisski_path_${pbid}__$pid"]['argument']['id']), $pbid.$pid);.
            $data[$base_table]["wisski_path_${pbid}__$pid"]['argument']['pb'] = $pbid;
            $data[$base_table]["wisski_path_${pbid}__$pid"]['argument']['path'] = $pid;
            $data[$base_table]["wisski_path_${pbid}__$pid"]['argument']['wisski_field'] = "$pbid.$pid";

            $data[$base_table]["wisski_path_${pbid}__$pid"]['entity type'] = $this->entityType->id();
            /*
            $data[$base_table]["wisski_path_${pbid}__$pid"] =
            // It would have been brilliant if we could combine both pb ID
            // and path ID by a dot for forming the field's ID as wisski
            // entity query encodes paths like that. But Drupal views does
            // not allow dots... :(
            'id' => "wisski_path_{$pbid}__$pid",
            'title' => $this->t("@group -> @path (@id) in @pb", [
            "@group" => $group->getName(),
            "@path" => $path->getName(),
            "@id" => $pid,
            "@pb" => $pb->getName(),
            ]),
            'field' => [
            'id' => 'wisski_entityfield', #'wisski_standard',
            'field_name' => $fieldid,
            'entity_type' => $this->entityType->id(),
            'wisski_field' => "$pbid.$pid",
            ],
            'filter' => [
            'id' => 'wisski_field_string', // TODO: depending on the field type we should use other filter types like numeric etc.
            'pb' => $pbid,
            'path' => $pid,
            'wisski_field' => "$pbid.$pid",
            ],
            'sort' => [
            'id' => 'standard',
            ],
            'argument' => [
            'id' => 'wisski_string',
            'pb' => $pbid,
            'path' => $pid,
            'wisski_field' => "$pbid.$pid",
            ],
            'entity type' => $this->entityType->id(),
            ]; */
          }
        }
      }

      // Handle orphaned paths.
      foreach ($orphaned_paths as $path) {
        $pid = $path->id();
        if (!$path->isGroup()) {
          $data[$base_table]["wisski_path_${pbid}__$pid"] = [
            'id' => "wisski_path_{$pbid}__$pid",
            'title' => $this->t(
          "@path (@id) in @pb (Standalone)", [
            "@path" => $path->getName(),
            "@id" => $pid,
            "@pb" => $pb->getName(),
          ]
            ),
            'field' => [
              'id' => 'wisski_standard',
              'wisski_field' => "$pbid.$pid",
            ],
            'filter' => [
                      // TODO: depending on the field type we should use other filter types like numeric etc.
              'id' => 'wisski_field_string',
              'pb' => $pbid,
              'path' => $pid,
              'wisski_field' => "$pbid.$pid",
            ],
            'sort' => [
              'id' => 'standard',
            ],
            'argument' => [
              'id' => 'wisski_string',
              'pb' => $pbid,
              'path' => $pid,
              'wisski_field' => "$pbid.$pid",
            ],
            'entity type' => $this->entityType->id(),
          ];
        }
      }

    }

    $top_bundles = WisskiHelper::getTopBundleIds(TRUE);
    foreach ($top_bundles as $bid => $bundle_info) {
      // HERE we should add the fields per bundle.
    }

    return $data;

  }

  /**
   *
   */
  public function getViewsTableForEntityType(EntityTypeInterface $entity_type) {
    return 'wisski_individual';
  }

}
