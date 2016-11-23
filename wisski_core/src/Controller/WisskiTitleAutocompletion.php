<?php

namespace Drupal\wisski_core\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class WisskiTitleAutocompletion {
  
  public $limit = 30;

  public function autocomplete(Request $request) {
    
    $string = $request->query->get('q');
    
    $matches = array($string);
    
    if ($string) {
      // just query the ngram table
      $select = \Drupal::service('database')
          ->select('wisski_title_n_grams','w');
      $rows = $select
          ->fields('w', array('ngram'))
          ->condition('ngram', "%" . $select->escapeLike($string) . "%", 'LIKE')
          ->range(0, $this->limit)
          ->execute()
          ->fetchCol();
      
      $matches = $rows;
      
    } 

    return new JsonResponse($matches);


    

    //dpm(func_get_args(),__METHOD__);
    $matches = array();
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
