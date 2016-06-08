<?php

namespace Drupal\wisski_core\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class WisskiTitleAutocompletion {

  public function autocomplete(Request $request) {
  
    //dpm(func_get_args(),__METHOD__);
    $matches = array();
    $string = $request->query->get('q');
    if ($string) {
      $bundles = \Drupal\wisski_core\Entity\WisskiBundle::loadMultiple();
      foreach ($bundles as $bundle) {
        $titles = $bundle->getCachedTitles();
        foreach ($titles as $title) {
          if (strpos($title,$string) !== FALSE) {
            $matches[] = array('value' => $title, 'label' => $title);
          }
        }
        //Early return on the first matches to avoid excessive loading
        if (!empty($matches)) return new JsonResponse($matches);
      }
    }
    return new JsonResponse($matches);
  }
}