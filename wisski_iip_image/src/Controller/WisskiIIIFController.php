<?php

namespace Drupal\wisski_iip_image\Controller;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class WisskiIIIFController {

  public function manifest(\Drupal\wisski_core\Entity\WisskiEntity $wisski_individual = NULL) {

    $data = [];

    $data[] = "Hallo Welt!";

    $data['#cache'] = [
      'max-age' => 1, 
      'contexts' => [
         'url',
      ],
    ];

    $response = new CacheableJsonResponse($data);

    return $response;
  }
  
}
