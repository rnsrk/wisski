<?php

/**
 * @file
 * Contains \Drupal\wisski_adapter_sparql11_pb\Controller\Sparql11AutocompleteController.
 */

namespace Drupal\wisski_autocomplete\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB;
use Drupal\wisski_pathbuilder\Entity\WisskiPathEntity;
use Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity;
use Drupal\wisski_salz\Entity\Adapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\wisski_salz\AdapterHelper;

/**
 * Returns autocomplete responses for countries.
 */
class WisskiAutocompleteController extends ControllerBase {

  /**
   * The amount of autocomplete suggestions.
   *
   * @var int
   */
  private $autocompleteSuggestionsLimit = 10;

  /**
   * If the autocompleteshould use the title pattern.
   *
   * @var bool
   */
  private $autocompleteTitlePatternEnabled = FALSE;


  /**
   * The entity type manager.
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   *The language manager.
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  // /**
  // * {@inheritdoc}
  // */
  // public function __construct(
  //   EntityTypeManagerInterface $entityTypeManager,
  //   LanguageManagerInterface $languageManager
  // ){
  //   $this->entityTypeManager = $entityTypeManager;
  //   $this->languageManager = $languageManager;
  // }


  // /**
  //  * {@inheritdoc}
  //  */
  // public static function create(ContainerInterface $container) {
  //   return new static(
  //     $container->get('entity_type.manager')
  //   );
  // }


  /**
   * Parse autocomplete results to JsonResponse.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object containing the search string.
   * @param string $fieldId
   *   The id of the field.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions for countries.
   */
  public function autocomplete(Request $request, string $fieldId): JsonResponse {
    // get the query parameter
    $query = $request->query->get('q');
    if (!isset($query)) {
      return new JsonResponse([]);
    }

    // do the actual autocomplete operation
    $result = $this->doAutocomplete($query, $fieldId);
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
   *
   * @return array[] List of (label, id) objects to display as a result
   */
  private function doAutocomplete(string $query, string $fieldId): array|NULL {
    /** @var ?string */
    $pathId = NULL;

    /** @var \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity */
    $pathbuilder = NULL;

    // Iterate through pathbuilders for corresponding path id.
    $pbs = WisskiPathbuilderEntity::loadMultiple();
    if (empty($pbs)) {
      return NULL;
    }
    foreach ($pbs as $pb) {
      $path = $pb->getPathForFid($fieldId);
      if (empty($path)) {
        continue;
      }

      $pathId = $path->id();
      $pathbuilder = $pb;
      break;
    }

    // Exit if the path is not in any pathbuilder.
    if (empty($pathbuilder) || empty($pathId)) {
      return NULL;
    }

    // load the engine belonging to the pathbuilder
    // (or bail out if anything goes wrong)
    $adapterId = $pathbuilder->getAdapterId();
    if (empty($adapterId)) {
      return NULL;
    }

    $adapter = Adapter::load($adapterId);
    if (empty($adapter)) {
      return NULL;
    }

    $engine = $adapter->getEngine();
    if(!$engine instanceof Sparql11EngineWithPB){
      return NULL;
    }


    // Use title pattern if set.
    /** @var array */
    $pbPath = $pathbuilder->getPbPath($pathId);
    $titlePatternEnabled = $this->autocompleteTitlePatternEnabled;
    if (isset($pbPath) && isset($pbPath['displaywidget'])) {
      $titlePatternEnabled = $pbPath['displaywidget'] == "wisski_autocomplete_widget";
    }

    // Load field settings.
    /** @var array */
    $fieldSettings = NULL;
    if (isset($pbPath) && isset($pbPath['bundle'])) {
      $ind = \Drupal::service('entity_type.manager')->getStorage('entity_form_display')->load('wisski_individual.' . $pbPath['bundle'] . '.default');
      if (isset($ind)) {
        $comp = $ind->getComponent($fieldId);
        if (isset($comp) && isset($comp['settings'])) {
          $fieldSettings = $comp['settings'];
        }
      }
    }

    // Determine autocomplete limit to use.
    // Either the global limit, or the field-based override.
    $limit = $this->autocompleteSuggestionsLimit;
    if (isset($fieldSettings) && isset($fieldSettings['autocompletelimit'])) {
      $limit = $fieldSettings['autocompletelimit'];
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
      #          $sparql .= " FILTER STRSTARTS(STR(?out), '" . $engine->escapeSparqlLiteral($string) . "') . } ";
      #          $sparql .= " FILTER CONTAINS(?out, '" . $engine->escapeSparqlLiteral($string) . "') . } ";
    } else {
      $startingPosition = (count($path->getPathArray()) - count($pathbuilder->getRelativePath($path))) / 2;
      $sparql = "SELECT DISTINCT ?out WHERE { ";
      $sparql .= $engine->generateTriplesForPath($pathbuilder, $path, NULL, NULL, NULL, NULL, $startingPosition, FALSE);
      #          $sparql .= " FILTER regex( STR(?out), '$string') . } ";
      $sparql .= " FILTER CONTAINS(STR(?out), '" . $engine->escapeSparqlLiteral($query) . "') . } ";
      #          $sparql .= " FILTER STRSTARTS(STR(?out), '" . $engine->escapeSparqlLiteral($string) . "') . } ";
      #          $sparql .= " FILTER CONTAINS(?out, '" . $engine->escapeSparqlLiteral($string) . "') . } ";
    }



    # dpm($sparql, "sq");

    // $sparql .= "LIMIT " . $this->autocompleteSuggestionsLimit;
    #      dpm(microtime());

    // TODO: Add limit and sorting directly to the query once thei
    // titles are in the in the triplestore.
    $result = $engine->directQuery($sparql);
    # dpm($result);
    #      dpm(microtime());

    // Initiate autocomplete matches array
    /** @var array[] */
    $matches = [];
    foreach ($result as $key => $thing) {

      // Not using the title patter, or invalid results.
      if (!$titlePatternEnabled || !isset($thing->$var)) {
        $matches[] = array('value' => $thing->out->getValue(), 'label' => $thing->out->getValue());
        //         #        $matches[] = array('value' => $key, 'label' => $thing->out->getValue());
        continue;
      }

      $id = AdapterHelper::getDrupalIdForUri($thing->$var->getUri());
      $tit = wisski_core_generate_title($id);
      $langcode = \Drupal::service('language_manager')->getCurrentLanguage()->getId();
      // check if it is keyed by language => in case a system does not support
      // multiple languages, this array has no distinction between the lang codes
      if (isset($tit[$langcode])) {
        $matches[] = array('value' => ($thing->out->getValue() . " (" . $id . ")"), 'label' => $tit[$langcode][0]['value']);
      } else {
        $matches[] = array('value' => ($thing->out->getValue() . " (" . $id . ")"), 'label' => $thing->out->getValue());
      }
    }

    // Sort the results by label!
    usort($matches, function ($a, $b) {
      return strcmp($a["label"], $b["label"]);
    });

    // Apply the actual limit.
    $ret = array_slice($matches, 0, $limit);
    if(count($matches) >= $limit){
      $ret[] = array('label' => "More hits were found, continue typing...");
    }

    return $ret;
  }
}

