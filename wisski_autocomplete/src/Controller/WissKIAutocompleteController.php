<?php

namespace Drupal\wisski_autocomplete\Controller;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;

/**
 * Class WissKIAutocompleteController
 * @package Drupal\wisski_autocomplete\Controller
 */
class WissKIAutocompleteController
{

  /**
   * @return JsonResponse
   */
  public function autocompleteMatch(Request $request)
  {
    \Drupal::logger('wisski_autocomplete')->error($request);

    $results = [];
    $input = $request->query->get('q');
    \Drupal::messenger()->addMessage($input);
    /*
    if (!$input) {
      return new JsonResponse($results);
    }
    $input = Xss::filter($input);
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'article')
      ->condition('title', $input, 'CONTAINS')
      ->groupBy('nid')
      ->sort('created', 'DESC')
      ->range(0, 10);
    $ids = $query->execute();
    $nodes = $ids ? \Drupal\node\Entity\Node::loadMultiple($ids) : [];
    foreach ($nodes as $node) {
      $results[] = [
        'value' => 'EntityAutocomplete::getEntityLabels([$node])',
        'label' => $node->getTitle().' ('.$node->id().')',
      ];
    }
    */
    //return new JsonResponse($results);
    $results[] = [
        'value' => 'Hallo',
        'label' => 'Hey blabla',
      ];
    return new JsonResponse($results);
  }

}