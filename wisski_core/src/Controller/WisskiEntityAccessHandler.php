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
#    return AccessResult::forbidden("You are missing the correct permissions to see this content.")->cachePerPermissions();
#  dpm(func_get_args(),__METHOD__);

    // I don't know what this does... but node does it, so we do it, too.
    $account = $this->prepareUser($account);
    
    if ($account->hasPermission('bypass wisski access')) {
      $result = AccessResult::allowed()->cachePerPermissions();
      return $result;
    }
    
    if($operation == "view" || $operation == "edit" || $operation == "delete") {

      // two special cases for view
      if($operation == "view") {
#        \Drupal::logger('UPDATE IN '.$operation)->debug('{u}',array('u'=>serialize($entity->get('status')->getValue())));
        // if it is a published one and you may view published ones...    
        if($account->hasPermission($operation . ' published wisski content')) {

          // a little bit problematic getting this value....
          $value = $entity->get('status')->getValue();

          if(isset($value[0]) && isset($value[0]["value"]) && $value[0]["value"] == TRUE) {
            $result = AccessResult::allowed()->cachePerPermissions();
            return $result;
          }
        }
              
        // if we may view our own unpublished content or we may view other unpublished content
        if(($account->hasPermission($operation . ' own unpublished wisski content') & $entity->get('uid')->entity->id() == $account->id()) || $account->hasPermission($operation . ' other unpublished wisski content')) {
          $result = AccessResult::allowed()->cachePerPermissions();
          return $result;
        }
      }      
         
      // if the user may view any content or he/she may view the whole bundle - exit here.
      if($account->hasPermission($operation . ' any wisski content') || $account->hasPermission($operation . ' any ' . $entity->bundle() . ' WisskiBundle')) {
        $result = AccessResult::allowed()->cachePerPermissions();
        return $result;
      }
      
      // both above was not correct, so it may be that he is the owner of the thing.
      if($account->hasPermission($operation . ' own wisski content') || $account->hasPermission($operation . ' own ' . $entity->bundle() . ' WisskiBundle')) {
        if($entity->get('uid')->entity->id() == $account->id()) {
          $result = AccessResult::allowed()->cachePerPermissions();
          return $result;
        }
      }

      // if none holds, we forbid it.
      $result = AccessResult::forbidden("You are missing the correct permissions to see this content.")->cachePerPermissions();
      return $result;      
    }
    // update
  
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
