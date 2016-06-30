<?php

namespace Drupal\wisski_core\Routing;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Drupal\Core\Entity\EntityManagerInterface;

class WisskiRoutes {

  /**
   * The entity storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * Creates an ExternalEntityRoutes object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityStorage = $entity_manager->getStorage('wisski_bundle');
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $route_collection = new RouteCollection();
    foreach ($this->entityStorage->loadMultiple() as $type) {
      $route = new Route(
        '/wisski/navigate/' . $type->id().'/{wisski_individual}/view',
        [
          '_entity_view' => 'wisski_individual',
          'bundle' => $type->id(),
          '_title' => 'WissKI Entity Content',
        ],
        [
          '_entity_access' => 'wisski_individual.view',
        ]
      );
      $route_collection->add('entity.wisski_individual.'. $type->id().'.view', $route);
    }
    return $route_collection;
  }
}