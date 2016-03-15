<?php

namespace Drupal\wisski_core\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the comment entity.
 *
 * @see \Drupal\comment\Entity\Comment.
 */
class WisskiEntityAccessHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   *
   * Link the activities to the permissions. checkAccess is called with the
   * $operation as defined in the routing.yml file.
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
dpm(func_get_args(),__METHOD__);
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view wisski content');

      case 'edit':
        return AccessResult::allowedIfHasPermission($account, 'administer wisski');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'administer wisski');
    }
  
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   *
   * Separate from the checkAccess because the entity does not yet exist, it
   * will be created during the 'add' process.
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    //dpm(func_get_args(),__METHOD__);
    return AccessResult::allowedIfHasPermission($account, 'administer wisski');
  }

}
