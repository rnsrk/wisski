<?php

namespace Drupal\wisski_core\Controller;

use Drupal\Core\Entity\ContentEntityStorageInterface;

class WisskiEntityViewForwarder {

  public function forward($wisski_individual,$wisski_bundle) {

    $storage = \Drupal::entityManager()->getStorage('wisski_individual');
    $storage->writeToCache($wisski_individual,$wisski_bundle);
    $entity = $storage->load($wisski_individual);
    $entity_type = $storage->getEntityType();
    $view_builder_class = $entity_type->getViewBuilderClass();
    $view_builder = $view_builder_class::createInstance(\Drupal::getContainer(),$entity_type);
//    dpm($view_builder);
    return $view_builder->view($entity);
  }
}