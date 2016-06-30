<?php

namespace Drupal\wisski_core\Controller;

use Drupal\Core\Entity\ContentEntityStorageInterface;

class WisskiEntityViewForwarder {

  private $storage;

  public function __construct(EntityStorageInterface $storage) {
    $this->storage = $storage;
    dpm($this->storage,__METHOD__);
  }

  public static function forward($wisski_individual,$wisski_bundle) {
    dpm(func_get_args(),__METHOD__);
    $forwarder = \Drupal::service('wisski.forwarder');
    dpm($forwarder);
    return array('#markup' => '<h1>Gotcha</h1>');
  }
}