<?php

/**
 * @file
 * Contains Drupal\wisski_salz\WisskiSalzAdapterPluginInterface.
 */

namespace Drupal\wisski_salz;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\wisski_salz\ExternalEntityInterface;

/**
 * Defines an interface for external entity storage client plugins.
 */
interface WisskiSalzAdapterPluginInterface extends PluginInspectionInterface {
  /**
   * Return the name of the external entity storage client.
   *
   * @return string
   *   The name of the external entity storage client.
   */
#  public function getName();

  /**
   * Loads one entity.
   *
   * @param mixed $id
   *   The ID of the entity to load.
   *
   * @return \Drupal\wisski_salz\ExternalEntityInterface|null
   *   An external entity object. NULL if no matching entity is found.
   */
#  public function load($id);

  /**
   * Saves the entity permanently.
   *
   * @param \Drupal\wisski_salz\ExternalEntityInterface $entity
   *   The entity to save.
   *
   * @return int
   *   SAVED_NEW or SAVED_UPDATED is returned depending on the operation
   *   performed.
   */
#  public function save(ExternalEntityInterface $entity);

  /**
   * Deletes permanently saved entities.
   *
   * @param \Drupal\wisski_salz\ExternalEntityInterface $entity
   *   The external entity object to delete.
   */
#  public function delete(ExternalEntityInterface $entity);

  /**
   * Query the external entities.
   *
   * @param array $parameters
   *   Key-value pairs of fields to query.
   */
#  public function query(array $parameters);

  /**
   * Get HTTP headers to add.
   *
   * @return array
   *   Associative array of headers to add to the request.
   */
#  public function getHttpHeaders();

}
