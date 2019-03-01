<?php

namespace Drupal\wisski_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\wisski_salz\AdapterHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 *
 */
class WisskiEntityLodController extends ControllerBase {

  /**
   *
   */
  public function get() {

    $request = \Drupal::request();
    $params = $request->query;
    $param_preference = ['uri', 'resource', 'instance', 'q'];

    $uri = NULL;
    foreach ($param_preference as $p) {
      if ($params->has($p)) {
        $uri = $params->get($p);
        break;
      }
    }

    // If no URI was given, we abort with an error.
    if ($uri === NULL) {
      drupal_set_message($this->t("No URI given. You must specify a URI using one of the following query parameters: %p", ['%p' => join(", ", $param_preference)]), 'error');
      throw new NotFoundHttpException(t("No URI given."));
    }

    // Cleanse URI: remove surrounding <> or expand prefix.
    $uri = trim($uri);
    if (substr($uri, 0, 1) == '<' && substr($uri, -1, 1) == '>') {
      $uri = trim(substr($uri, 1, -1));
    }
    else {
      // TODO expand prefix.
    }

    // Check whether some adapter knows the URI
    // if not we display a page not found.
    if (!AdapterHelper::checkUriExists($uri)) {
      drupal_set_message($this->t("The URI %uri is unknown to the system", ['%uri' => $uri]), 'error');
      throw new NotFoundHttpException(t("The given URI is unknown to the system."));
    }

    // We retrieve the URI's Drupal ID and redirect to the view
    // page. If there is no Drupal ID yet, we create one. (We know that there
    // should be one, but maybe the URI wasn't touched by Drupal, yet.)
    $eid = AdapterHelper::getDrupalIdForUri($uri, TRUE);
    $url = Url::fromRoute(
        "entity.wisski_individual.canonical",
        ['wisski_individual' => $eid],
        // Options array; RedirectResponse expects an absolute URL.
        ['absolute' => TRUE]
    );
    return new RedirectResponse($url->toString());

  }

}
