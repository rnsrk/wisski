<?php
/**
 * @file
 *
 * Contains Drupal\wisski_pathbuilder\Exporter
 */

namespace Drupal\wisski_pathbuilder\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;

use Drupal\Core\Cache\CacheableResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

use Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity;

class WisskiPathbuilderExportController extends ControllerBase {

  /**
   * Export a pathbuilder
   *
   * @param Request $request
   *   The Request
   * @param string $pathbuilder_id
   *   The machine name of the requested pathbuilder
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response
   */
  public function export(Request $request, string $pathbuilder_id): Response {
    # TODO: implement
    $cacheing = FALSE;

    $response = new Response();
    if ($cacheing) {
      $response = new CacheableResponse();
      $response->getCacheableMetadata()->addCacheContexts(
        ['url.query_args', 'url.path']
      );
    }

    // Get the PB
    $pathbuilder = WisskiPathbuilderEntity::load($pathbuilder_id);
    // If there's none return error message
    if (!$pathbuilder) {
      $headers = ['Content-Type' => 'text/plain'];
      $response->setContent("No such pathbuilder with id $pathbuilder_id!");
      $response->setStatusCode(Response::HTTP_NOT_FOUND);
      return $response;
    }

    $xml = $pathbuilder->toXML();
    // Default to application/xml if there was no request supplied.
    $response->headers->set("Content-Type", "application/xml");
    if ($request) {
      // Default to application/xml if no Content-Type header was specified.
      $response->headers->set("Content-Type", strtolower($request->headers->get("Content-Type", "application/xml")));
    }

    // Set status code.
    $response->setStatusCode(Response::HTTP_OK);
    $response->setContent($xml);
    return $response;
  }
}
