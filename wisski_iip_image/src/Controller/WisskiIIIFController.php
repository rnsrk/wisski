<?php

namespace Drupal\wisski_iip_image\Controller;

use Drupal\Core\Entity\ContentEntityStorageInterface;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class WisskiIIIFController {

  public function manifest(\Drupal\wisski_core\Entity\WisskiEntity $wisski_individual = NULL) {

    echo "hallo welt!";

  }
  
}
