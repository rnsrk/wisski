<?php
/**
 * contains Drupal\wisski_core\Controller\WisskiEntityListController
 */

namespace Drupal\wisski_core\Controller;
 
use Drupal\Core\Entity\Controller\EntityListController;
 
class WisskiEntityListController extends EntityListController {

  public function listing($wisski_bundle,$limit=NULL) {

    if (is_null($limit)) {
      $limit = \Drupal::config('wisski_core.settings')->get('wisski_max_entities_per_page');
    }
    return $this->entityManager()->getListBuilder('wisski_individual')->render($wisski_bundle,$limit);
  }
}