<?php

namespace Drupal\wisski_core\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\wisski_core\DMBundleInterface;

/**
 * Defines the bundle configuration entity.
 *
 * @ConfigEntityType(
 *   id = "wisski_core_bundle",
 *   label = @Translation("Wisski Bundle"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\wisski_core\Form\DMTestBundleForm",
 *       "edit" = "Drupal\wisski_core\Form\DMTestBundleForm",
 *     },
 *     "list_builder" = "Drupal\wisski_core\Controller\DMBundleListBuilder",
 *		 "access" = "Drupal\wisski_core\Controller\DMBundleAccessHandler",
 *   },
 *   admin_permission = "administer wisski_core",
 *   config_prefix = "bundle",
 *   bundle_of = "wisski_core",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *		 "description" = "description",
 *   },
 *   links = {
 *     "edit-form" = "/admin/structure/wisski_core/{wisski_core_bundle}/edit",
 *     "delete-form" = "/admin/structure/wisski_core/{wisski_core_bundle}/delete",
 *     "collection" = "/admin/structure/wisski_core/{wisski_core_bundle}",
 *		 "list" = "/admin/structure/wisski_core",
 *   }
 * )
 */
class WisskiBundle extends ConfigEntityBundleBase implements WisskiBundleInterface {

}
