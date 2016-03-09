<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\Entity\WisskiSalzAdapter.
 */

namespace Drupal\wisski_salz\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\wisski_salz\WisskiSalzAdapterInterface;

/**
 * Defines the WissKI Salz Adapter entity.
 *
 * @ConfigEntityType(
 *   id = "wisski_salz_adapter",
 *   label = @Translation("WissKI Salz Adapter"),
 *   handlers = {
 *     "list_builder" = "Drupal\wisski_salz\WisskiSalzAdapterListBuilder",
 *     "form" = {
 *       "add" = "Drupal\wisski_salz\Form\WisskiSalzAdapterForm",
 *       "edit" = "Drupal\wisski_salz\Form\WisskiSalzAdapterForm",
 *       "delete" = "Drupal\wisski_salz\Form\WisskiSalzAdapterDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\wisski_salz\WisskiSalzAdapterHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "wisski_salz_adapter",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/wisski_salz_adapter/{wisski_salz_adapter}",
 *     "add-form" = "/admin/config/wisski_salz_adapter/add",
 *     "edit-form" = "/admin/config/wisski_salz_adapter/{wisski_salz_adapter}/edit",
 *     "delete-form" = "/admin/config/wisski_salz_adapter/{wisski_salz_adapter}/delete",
 *     "collection" = "/admin/config/wisski_salz_adapter"
 *   }
 * )
 */
class WisskiSalzAdapter extends ConfigEntityBase implements WisskiSalzAdapterInterface {
  /**
   * The WissKI Salz Adapter ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The WissKI Salz Adapter label.
   *
   * @var string
   */
  protected $label;

}
