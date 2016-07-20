<?php

namespace Drupal\wisski_core\Controller;

use Drupal\Core\Entity\ContentEntityStorageInterface;

class WisskiEntityViewForwarder {

  public function forward($wisski_individual) {

    $storage = \Drupal::entityManager()->getStorage('wisski_individual');
    //let's see if the user provided us with a bundle, if not, the storage will try to guess the right one
    $match = \Drupal::request();
    $bundle_id = $match->query->get('wisski_bundle');
    if ($bundle_id) $storage->writeToCache($wisski_individual,$bundle_id);
    $entity = $storage->load($wisski_individual);
    //dpm($entity->bundle(),__FUNCTION__);
    $entity_type = $storage->getEntityType();
    $view_builder_class = $entity_type->getViewBuilderClass();
    $view_builder = $view_builder_class::createInstance(\Drupal::getContainer(),$entity_type);
//    dpm($view_builder);
    return $view_builder->view($entity);
  }
  
}