<?php

namespace Drupal\wisski_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;

use Symfony\Component\Routing\RouteCollection;

use Drupal\search\Entity\SearchPage;
use Drupal\search\Routing\SearchPageRoutes;

class WisskiRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection) {
    
    //\Drupal::logger('wisski')->warning(__METHOD__);
    $page = SearchPage::load('wisski_search');
    if (is_null($page)) {
      $values = array(
        'id' => 'wisski_search',
        'plugin' => 'wisski_individual_search',
        'path' => 'wisski',
        'label' => 'Search WissKI Entities',
        'weight' => -100,
      );
      $page = SearchPage::create($values);
      $page->save();
    }
    $routes = SearchPageRoutes::create(\Drupal::getContainer())->routes();
    foreach (array('search.view_wisski_search','search.help_wisski_search') as $key) {    
      $route = $collection->get($key);
      if (is_null($route)) {
        $collection->add($key,$routes[$key]);      
      }
    }
  }
}