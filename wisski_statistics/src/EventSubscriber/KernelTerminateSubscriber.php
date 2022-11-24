<?php

namespace Drupal\wisski_statistics\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Routing\RouteMatchInterface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Store request data when it terminates.
 * Adapted from https://git.drupalcode.org/project/visitors/-/blob/8.x-2.x/src/EventSubscriber/KernelTerminateSubscriber.php
 */
class KernelTerminateSubscriber implements EventSubscriberInterface {
  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Database Service Object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;


  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config_factory service object.
   * @param \Drupal\Core\Database\Connection $database
   *   The database service object.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match object.
   */
  public function __construct(
      AccountInterface $current_user, 
      ConfigFactoryInterface $config_factory, 
      Connection $database,
      RouteMatchInterface $route_match,
      ) {
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
    $this->database = $database;
    $this->routeMatch = $route_match;
  }

  /**
   * Store visitors data when a request terminates.
   *
   * @param \Symfony\Component\HttpKernel\Event\TerminateEvent $event
   *   The Event to process.
   */
  public function onTerminate(TerminateEvent $event) {
    $request = $event->getRequest();

    $config = $this->configFactory->get('wisski_statistics.page_access.config');
    $exclude_user1 = $config->get('exclude_user1');
    $excluded_roles = array_values($config->get('excluded_roles') ?? []);

    if($this->currentUser->id() == 1 && $exclude_user1) {
      return NULL;
    }

    $userRoles = $this->currentUser->getRoles();
    foreach($excluded_roles as $role) {
      $skip_because_role = in_array($role, $user_roles, TRUE);
      if ($skip_because_role) {
        return NULL;
      }
    }

    $host = $request->getHost();
    $uri = $request->getRequestUri();
    $url = urldecode("http://{$host}{$uri}");

    // TODO: see we should rather log the route and the raw parameters instead of full URLs
    //\Drupal::logger('route')->notice($this->routeMatch->getRouteName());
    //\Drupal::logger('parameters')->notice(serialize($this->routeMatch->getRawParameters()));

    

    // TODO: figure out where "http://default"
    // comes from...
    // apparently every time a (DB query?) is
    // sent (from the shell?) this triggers
    if($url == "http://default/"){
      return;
    }

    try {
      $query = $this->database->insert('wisski_statistics_requests')
        ->fields([
            'url' => $url,
            'timestamp' => time(),
        ])
        ->execute();
    }
    catch (\Exception $e) {
      watchdog_exception('wisski_statistics_access_log', $e);
    }
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return array
   *   An array of event listener definitions.
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::TERMINATE][] = ['onTerminate'];
    return $events;
  }

}
