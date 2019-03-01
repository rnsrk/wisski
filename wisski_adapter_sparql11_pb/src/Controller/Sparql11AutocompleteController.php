<?php

namespace Drupal\wisski_adapter_sparql11_pb\Controller;

use Drupal\wisski_salz\Entity\Adapter;
use Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity;
use Drupal\wisski_pathbuilder\Entity\WisskiPathEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns autocomplete responses for countries.
 */
class Sparql11AutocompleteController {

  /**
   * Returns response for the country name autocompletion.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object containing the search string.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions for countries.
   */
  public function autocomplete(Request $request, $fieldid, $pathid, $pbid, $engineid) {
    // drupal_set_message("fun: " . serialize(func_get_args()));
    // drupal_set_message("pb: " . serialize($pbid));
    // $matches = array();
    // $matches[] = array('value' => "dfdf", "label" => "sdfsdffd");
    // return new JsonResponse($matches);
    $string = $request->query->get('q');
    if ($string) {
      // drupal_set_message("str: " . serialize($string));
      // drupal_set_message("pathid: " . serialize($pathid));
      $path = WisskiPathEntity::load($pathid);
      $pb = WisskiPathbuilderEntity::load($pbid);
      $adapter = Adapter::load($engineid);

      // drupal_set_message("path: " . serialize($path));
      // drupal_set_message("pb: " . serialize($pb));
      // drupal_set_message("adapter: " . serialize($adapter));
      $engine = $adapter->getEngine();

      if (empty($path)) {
        return NULL;
      }

      // Graph G?
      if ($path->getDisamb()) {
        $sparql = "SELECT ?out WHERE { ";
        // In case of disamb go for -1.
        $sparql .= $engine->generateTriplesForPath($pb, $path, NULL, NULL, NULL, NULL, $path->getDisamb() - 1, FALSE);
        // $sparql .= " FILTER regex( STR(?out), '$string') . } ";
        // martin said contains is faster ;D.
        $sparql .= " FILTER CONTAINS(STR(?out), '" . $engine->escapeSparqlLiteral($string) . "') . } ";
        // $sparql .= " FILTER STRSTARTS(STR(?out), '" . $engine->escapeSparqlLiteral($string) . "') . } ";
        // $sparql .= " FILTER CONTAINS(?out, '" . $engine->escapeSparqlLiteral($string) . "') . } ";.
      }
      else {
        $starting_position = (count($path->getPathArray()) - count($pb->getRelativePath($path))) / 2;
        $sparql = "SELECT DISTINCT ?out WHERE { ";
        $sparql .= $engine->generateTriplesForPath($pb, $path, NULL, NULL, NULL, NULL, $starting_position, FALSE);
        // $sparql .= " FILTER regex( STR(?out), '$string') . } ";.
        $sparql .= " FILTER CONTAINS(STR(?out), '" . $engine->escapeSparqlLiteral($string) . "') . } ";
        // $sparql .= " FILTER STRSTARTS(STR(?out), '" . $engine->escapeSparqlLiteral($string) . "') . } ";
        // $sparql .= " FILTER CONTAINS(?out, '" . $engine->escapeSparqlLiteral($string) . "') . } ";.
      }
    }

    if (empty($sparql)) {
      return NULL;
    }

    $sparql .= "LIMIT 10";

    // drupal_set_message("engine: " . serialize($sparql));
    // dpm(microtime());
    $result = $engine->directQuery($sparql);
    // dpm(microtime());
    $matches = [];
    $i = 0;
    foreach ($result as $key => $thing) {
      $matches[] = ['value' => $thing->out->getValue(), 'label' => $thing->out->getValue()];
      // $matches[] = array('value' => $key, 'label' => $thing->out->getValue());
      $i++;

      if ($i > 9) {
        $matches[] = ['label' => "More hits were found, continue typing..."];
        break;
      }
    }

    // dpm(serialize(new JsonResponse($matches)), "out");.
    return new JsonResponse($matches);
  }

}
