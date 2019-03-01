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
    // Return AccessResult::forbidden("You are missing the correct permissions to see this content.")->cachePerPermissions();
    // dpm(func_get_args(),__METHOD__);
    // I don't know what this does... but node does it, so we do it, too.
    $account = $this->prepareUser($account);

    // \Drupal::logger('UPDATE IN '.$operation)->debug('{u}',array('u'=>serialize($entity) . " and " . $operation));.
    if ($account->hasPermission('bypass wisski access')) {
      $result = AccessResult::allowed()->cachePerPermissions();
      return $result;
    }

    if ($operation == "view" || $operation == "edit" || $operation == "delete") {

      // Two special cases for view.
      if ($operation == "view") {
        // \Drupal::logger('UPDATE IN '.$operation)->debug('{u}',array('u'=>serialize($entity->get('status')->getValue())));
        // if it is a published one and you may view published ones...
        if ($account->hasPermission($operation . ' published wisski content')) {

          // A little bit problematic getting this value....
          $value = $entity->get('status')->getValue();

          if (isset($value[0]) && isset($value[0]["value"]) && $value[0]["value"] == TRUE) {
            $result = AccessResult::allowed()->cachePerPermissions();
            return $result;
          }
        }

        $uid = $entity->get('uid');
        if (!empty($uid)) {
          $uid = $uid->entity;
        }

        if (!empty($uid)) {
          $uid = $uid->id();
        }

        // If we may view our own unpublished content or we may view other unpublished content.
        if ((!is_null($uid) && ($account->hasPermission($operation . ' own unpublished wisski content') & $uid == $account->id())) || $account->hasPermission($operation . ' other unpublished wisski content')) {
          $result = AccessResult::allowed()->cachePerPermissions();
          return $result;
        }
      }

      // If the user may view any content or he/she may view the whole bundle - exit here.
      if ($account->hasPermission($operation . ' any wisski content') || $account->hasPermission($operation . ' any ' . $entity->bundle() . ' WisskiBundle')) {
        $result = AccessResult::allowed()->cachePerPermissions();
        return $result;
      }

      // Both above was not correct, so it may be that he is the owner of the thing.
      if ($account->hasPermission($operation . ' own wisski content') || $account->hasPermission($operation . ' own ' . $entity->bundle() . ' WisskiBundle')) {
        // Get the uid.
        $uid = $entity->get('uid');

        // See if there was something in the field.
        if (!empty($uid)) {
          // Only get the entity if there was something.
          $uid = $uid->entity;
        }

        // Only get the id if there was something in there.
        if (!empty($uid)) {
          $uid = $uid->id();
        }

        if (!empty($uid) && $uid == $account->id()) {
          $result = AccessResult::allowed()->cachePerPermissions();
          return $result;
        }
      }

      // If none holds, we forbid it.
      $result = AccessResult::forbidden("You are missing the correct permissions to see this content.")->cachePerPermissions();
      return $result;
    }
    // Update.
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   *
   * Separate from the checkAccess because the entity does not yet exist, it
   * will be created during the 'add' process.
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {

    $route_match = \Drupal::service('current_route_match');
    $bundle_from_url = $route_match->getParameter('wisski_bundle');
    // Try to get it from url if not available otherwise.
    if (empty($entity_bundle) && !empty($bundle_from_url)) {
      $entity_bundle = $bundle_from_url->ID();
    }

    // dpm(func_get_args(),__METHOD__);
    // return AccessResult::allowedIfHasPermission($account, 'administer wisski');.
    $account = $this->prepareUser($account);

    // \Drupal::logger('UPDATE IN '.$operation)->debug('{u}',array('u'=>serialize($entity) . " and " . $operation));.
    if ($account->hasPermission('bypass wisski access')) {
      $result = AccessResult::allowed()->cachePerPermissions();
      return $result;
    }

    // If the user may view any content or he/she may view the whole bundle - exit here.
    if ($account->hasPermission('create any wisski content') || $account->hasPermission('create ' . $entity_bundle . ' WisskiBundle')) {
      $result = AccessResult::allowed()->cachePerPermissions();
      return $result;
    }

    // If none holds, we forbid it.
    $result = AccessResult::forbidden("You are missing the correct permissions to see this content.")->cachePerPermissions();
    return $result;
  }

}
