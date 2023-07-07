<?php

/**
 * @file
 * Contains \Drupal\wisski_adapter_sparql11_pb\Controller\Sparql11AutocompleteController.
 */

namespace Drupal\wisski_autocomplete\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB;
use Drupal\wisski_pathbuilder\PathbuilderManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\wisski_salz\AdapterHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Returns autocomplete responses for countries.
 */
class WisskiAutocompleteController extends ControllerBase {

  /**
   * The default value for autocomplete suggestions.
   *
   * @var int
   */
  const DEFAULT_SUGGESTIONS_LIMIT = 10;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The Pathbuilder manager service.
   *
   * @var \Drupal\wisski_pathbuilder\PathbuilderManager
   */
  protected $pathbuilderManager;

  // /**
  // * {@inheritdoc}
  // */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LanguageManagerInterface $languageManager,
    PathbuilderManager $pathbuilderManager
  ){
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->pathbuilderManager = $pathbuilderManager;
  }


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('wisski_pathbuilder.manager')
    );
  }


  /**
   * Parse autocomplete results to JsonResponse.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object containing the search string.
   * @param string $fieldId
   *   The id of the field.
   * @param string $autocompleteLimit
   *   Number of suggestions to display.
   * @param bool $useTitlePattern
   *   Use title pattern in the automplete suggestions instead of field values.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions for countries.
   */
  public function autocomplete(Request $request, string $fieldId, int $autcompleteLimit=self::DEFAULT_SUGGESTIONS_LIMIT, bool $useTitlePattern=FALSE): JsonResponse {

    // Get the settings from the Widget from the config.
    $config = \Drupal::service('config.factory')->getEditable("wisski.autocomplete");
    $settings = $config->get($fieldId);
    $autcompleteLimit = $settings['autocompleteLimit'];
    $useTitlePattern = $settings['useTitlePattern'];

    // before abstraction: (warm / cold)
    // query: 0.031772136688232 / 0.02682900428772
    // doAutocomplete: 0.053938865661621 / 0.31383299827576


    // after abstraction: (warm / cold)
    // query: 0.037721872329712 / 0.025347948074341
    // doAutocomplete: 0.056931018829346 / 0.36022710800171

    // with cache (warm / cold)
    // query:  0.027452945709229 / 0.036310911178589
    // doAutocomplete: 0.041092157363892 / 0.12897205352783

    // with even better cache (warm / cold)
    // query:  0.03709888458252 / 0.072020053863525
    // doAutocomplete: 0.044520139694214 / 0.31710195541382

    // get the query parameter
    $query = $request->query->get('q');
    if (!isset($query)) {
      return new JsonResponse([]);
    }

    // do the actual autocomplete operation
    // $start = microtime(TRUE);
    $result = $this->doAutocomplete($query, $fieldId, $autcompleteLimit, $useTitlePattern);
    // dpm(microtime(TRUE) - $start, 'doAutocomplete took');

    if ($result === NULL) {
      return new JsonResponse([]);
    }
    return new JsonResponse($result);
  }

  /**
   * Performs an autocomplete query
   *
   * @param string query
   *   Query string the user entered.
   * @param string $fieldId
   *   ID of field to query values for.
   * @param string $autocompleteLimit
   *   Number of suggestions to display.
   * @param bool $useTitlePattern
   *   Use title pattern in the automplete suggestions instead of field values.
   *
   * @return array[] List of (label, id) objects to display as a result
   */
  private function doAutocomplete(string $query, string $fieldId, int $autocompleteLimit, bool $useTitlePattern): array|NULL {
    /** @var \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity */
    $path = NULL;

    /** @var ?string */
    $pathId = NULL;

    /** @var \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity */
    $pathbuilder = NULL;

    $idsForField = $this->pathbuilderManager->getPathIdsAndPbIdsForFieldId($fieldId);
    foreach ($idsForField as $pbId => $pathIds) {
      $pathId = current($pathIds);
      $pathbuilder = $this->pathbuilderManager->getPathbuilder($pbId);
      $path = $this->pathbuilderManager->getPath($pathId);
      break;
    }

    // Exit if the path is not in any pathbuilder.
    if (empty($pathbuilder) || empty($pathId) || empty($path)) {
      return NULL;
    }

    // load the engine belonging to the pathbuilder
    // (or bail out if anything goes wrong)
    $adapterId = $pathbuilder->getAdapterId();
    if (empty($adapterId)) {
      return NULL;
    }

    /** @var \Drupal\wisski_salz\Entity\Adapter */
    $adapter = $this->entityTypeManager()->getStorage('wisski_salz_adapter')->load($adapterId);
    if (empty($adapter)) {
      return NULL;
    }

    $engine = $adapter->getEngine();
    if(!$engine instanceof Sparql11EngineWithPB){
      return NULL;
    }

    // Construct SPARQL query.

    // The $path->getDisamb iterates through the path and considers only the
    // groups/bundles. It starts counting with 1 and iterates until the disambiguation point is reached, e.g.
    // abc:resource (concept, 1)
    // ->abc:resource_has_related_item (object property [not considered])
    // ->abc:related_item_group (concept, 2)->
    // abc:related_item_to_resource (object property [not considered])
    // ->abc:resource (concept + disamb point, 3)

    // in order to construct the right sparql query which also considers the
    // URI of the invidual, we have to parse through the path in a different
    // manner (caution: in that case, the counting starts with 0):
    // abc:resource (concept, 0)
    // ->abc:resource_has_related_item (object property, 1)
    // ->abc:related_item_group (concept, 2)->
    // abc:related_item_to_resource (object property, 3)
    // ->abc:resource (concept + disamb point, 4).

    // Therefore we have to shift by one and double the position result.
    $posInPathbuilder = ($path->getDisamb() - 1) * 2;

    $var = "x" . $posInPathbuilder;

    /** @todo Implement different filter types (contains, strstarts, regex),
     * criteria for filters and field types (date, integers) */
    // Graph G?
    if ($path->getDisamb()) {

      $sparql = "SELECT ?out ?$var WHERE { ";
      // in case of disamb go for -1
      $sparql .= $engine->generateTriplesForPath($pathbuilder, $path, NULL, NULL, NULL, NULL, $path->getDisamb() - 1, FALSE);
      //$sparql .= " FILTER regex( STR(?out), '$string') . } ";
      // martin said contains is faster ;D
       $sparql .= " FILTER CONTAINS(STR(?out), '" . $engine->escapeSparqlLiteral($query) . "') . } ";
      #          $sparql .= " FILTER STRSTARTS(STR(?out), '" . $engine->escapeSparqlLiteral($query) . "') . } ";
      #          $sparql .= " FILTER CONTAINS(?out, '" . $engine->escapeSparqlLiteral($string) . "') . } ";
    } else {
      $startingPosition = (count($path->getPathArray()) - count($pathbuilder->getRelativePath($path))) / 2;
      $sparql = "SELECT DISTINCT ?out WHERE { ";
      $sparql .= $engine->generateTriplesForPath($pathbuilder, $path, NULL, NULL, NULL, NULL, $startingPosition, FALSE);
      #          $sparql .= " FILTER regex( STR(?out), '$string') . } ";
      $sparql .= " FILTER CONTAINS(STR(?out), '" . $engine->escapeSparqlLiteral($query) . "') . } ";
      #          $sparql .= " FILTER STRSTARTS(STR(?out), '" . $engine->escapeSparqlLiteral($query) . "') . } ";
      #          $sparql .= " FILTER CONTAINS(?out, '" . $engine->escapeSparqlLiteral($string) . "') . } ";
    }

    $sparql .= "LIMIT " . $autocompleteLimit;



    # dpm($sparql, "sq");


    // TODO: Add sorting directly to the query once the
    // titles are in the in the triplestore.
    #$start = microtime(TRUE);
    $results = $engine->directQuery($sparql);
    #dpm(microtime(TRUE) - $start, 'query took');

    // Initiate autocomplete matches array
    /** @var array[] */
    $matches = [];
    foreach ($results as $result) {
      // Not using the title pattern, or invalid results.
      if (!$useTitlePattern || !isset($result->$var)) {
        $matches[] = array('value' => $result->out->getValue(), 'label' => $result->out->getValue());
        //         #        $matches[] = array('value' => $key, 'label' => $thing->out->getValue());
        continue;
      }

      $id = AdapterHelper::getDrupalIdForUri($result->$var->getUri());
      $tit = wisski_core_generate_title($id);
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
      // check if it is keyed by language => in case a system does not support
      // multiple languages, this array has no distinction between the lang codes
      if (isset($tit[$langcode])) {
        $matches[] = array('value' => ($result->out->getValue() . " (" . $id . ")"), 'label' => $tit[$langcode][0]['value']);
      } else {
        $matches[] = array('value' => ($result->out->getValue() . " (" . $id . ")"), 'label' => $result->out->getValue());
      }
    }

    // Sort the results by label!
    usort($matches, function ($a, $b) {
      return strcmp($a["label"], $b["label"]);
    });

    return $matches;

    /*
    // Apply the actual limit.
    $ret = array_slice($matches, 0, $limit);
    if(count($matches) >= $limit){
      $ret[] = array('label' => "More hits were found, continue typing...");
    }
    return $ret;
    */

  }
}

