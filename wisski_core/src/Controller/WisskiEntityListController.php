<?php
/**
 * contains Drupal\wisski_core\Controller\WisskiEntityListController
 */

namespace Drupal\wisski_core\Controller;
 
use Drupal\Core\Entity\Controller\EntityListController;
 
class WisskiEntityListController extends EntityListController {

  public function listing($wisski_bundle=NULL,$wisski_individual=NULL) {
    
    if (is_null($wisski_bundle)) return $this->entityManager()->getListBuilder('wisski_bundle')->render(WisskiBundleListBuilder::NAVIGATE);
    return $this->entityManager()->getListBuilder('wisski_individual')->render($wisski_bundle,$wisski_individual);
  }
}