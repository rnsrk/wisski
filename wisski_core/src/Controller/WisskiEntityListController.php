<?php
/**
 * contains Drupal\wisski_core\Controller\WisskiEntityListController
 */

namespace Drupal\wisski_core\Controller;
 
use Drupal\Core\Entity\Controller\EntityListController;
 
class WisskiEntityListController extends EntityListController {

  public function listing($wisski_bundle,$limit=NULL) {
    if (is_null($limit)) {
      //@TODO try to get limit from configuration
      $limit = 10;
      drupal_set_message('use hard coded limit of '.$limit);
    }
    return $this->entityManager()->getListBuilder('wisski_individual')->render($wisski_bundle,$limit);
  }
}