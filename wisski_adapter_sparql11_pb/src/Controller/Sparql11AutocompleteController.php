<?php

/**
 * @file
 * Contains \Drupal\wisski_adapter_sparql11_pb\Controller\Sparql11AutocompleteController.
 */

namespace Drupal\wisski_adapter_sparql11_pb\Controller;

use Drupal\wisski_pathbuilder\Entity\WisskiPathEntity;
use Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity;
use Drupal\wisski_salz\Entity\Adapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\wisski_salz\AdapterHelper;
use Drupal;

/**
 * Returns autocomplete responses for countries.
 */
class Sparql11AutocompleteController
{

  private $autocomplete_suggestions_limit = 10;
  private $autocomplete_title_pattern_enabled = false;

  /**
   * Returns response for the country name autocompletion.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object containing the search string.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions for countries.
   */
  public function autocomplete(Request $request, $fieldid, $pathid, $pbid, $engineid)
  {
    #      drupal_set_message("fun: " . serialize(func_get_args()));
    #      drupal_set_message("pb: " . serialize($pbid));
    #      $matches = array();
    #      $matches[] = array('value' => "dfdf", "label" => "sdfsdffd");
    #      return new JsonResponse($matches);
    $autocomplete_query_string = $request->query->get('q');
    if (isset($autocomplete_query_string)) {
      #        drupal_set_message("str: " . serialize($string));
      #        drupal_set_message("pathid: " . serialize($pathid));
      $path = WisskiPathEntity::load($pathid);
      if (empty($path))
        return NULL;

      $pb = WisskiPathbuilderEntity::load($pbid);
      $adapter = Adapter::load($engineid);
      $engine = $adapter->getEngine();

      #        drupal_set_message("path: " . serialize($path));
      #        drupal_set_message("pb: " . serialize($pb));
      #        drupal_set_message("adapter: " . serialize($adapter));

      // check if field widget contains 'autocompletelimit' setting
      $bundleid = $pb->getBundle($pathid);

      $field_settings = \Drupal::service('entity_type.manager')->getStorage('entity_form_display')->load('wisski_individual.' . $bundleid . '.default')->getComponent($fieldid)['settings'];

      if (isset($field_settings['autocompletelimit'])) {
        $this->autocomplete_suggestions_limit = $field_settings['autocompletelimit'];
      }

      $pbpath = $pb->getPbPath($pathid);
      if (isset($pbpath) && isset($pbpath['displaywidget'])) {
        $this->autocomplete_title_pattern_enabled = $pbpath['displaywidget'] == "wisski_autocomplete_widget";
      }


      // the $path->getDisamb iterates through the path and considers only the 
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
      // ->abc:resource (concept + disamb point, 4) 

      // therefore we have to shift by one and double the position result
      $posInPathbuilder = ($path->getDisamb() - 1) * 2;

      $var = "x" . $posInPathbuilder;


      // Graph G?
      if ($path->getDisamb()) {

        $sparql = "SELECT ?out ?$var WHERE { ";
        // in case of disamb go for -1
        $sparql .= $engine->generateTriplesForPath($pb, $path, NULL, NULL, NULL, NULL, $path->getDisamb() - 1, FALSE);
        //$sparql .= " FILTER regex( STR(?out), '$string') . } ";        
        // martin said contains is faster ;D
        $sparql .= " FILTER CONTAINS(STR(?out), '" . $engine->escapeSparqlLiteral($autocomplete_query_string) . "') . } ";
        #          $sparql .= " FILTER STRSTARTS(STR(?out), '" . $engine->escapeSparqlLiteral($string) . "') . } ";
        #          $sparql .= " FILTER CONTAINS(?out, '" . $engine->escapeSparqlLiteral($string) . "') . } ";
      } else {
        $starting_position = (count($path->getPathArray()) - count($pb->getRelativePath($path))) / 2;
        $sparql = "SELECT DISTINCT ?out WHERE { ";
        $sparql .= $engine->generateTriplesForPath($pb, $path, NULL, NULL, NULL, NULL, $starting_position, FALSE);
        #          $sparql .= " FILTER regex( STR(?out), '$string') . } ";
        $sparql .= " FILTER CONTAINS(STR(?out), '" . $engine->escapeSparqlLiteral($autocomplete_query_string) . "') . } ";
        #          $sparql .= " FILTER STRSTARTS(STR(?out), '" . $engine->escapeSparqlLiteral($string) . "') . } ";
        #          $sparql .= " FILTER CONTAINS(?out, '" . $engine->escapeSparqlLiteral($string) . "') . } ";
      }
    }
    if (empty($sparql))
      return NULL;

    # dpm($sparql, "sq");

    // $sparql .= "LIMIT " . $this->autocomplete_suggestions_limit;

    #      drupal_set_message("engine: " . serialize($sparql));
    #      dpm(microtime());
    
    // TODO: Add limit and sorting directly to the query once thei 
    // titles are in the in the triplestore.
    $result = $engine->directQuery($sparql);
    # dpm($result);
    #      dpm(microtime());
    $matches = array();

    foreach ($result as $key => $thing) {
      if ($this->autocomplete_title_pattern_enabled && isset($thing->$var)) {
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
      } else {
        $matches[] = array('value' => $thing->out->getValue(), 'label' => $thing->out->getValue());
        #        $matches[] = array('value' => $key, 'label' => $thing->out->getValue());
      }
    }

    usort($matches, function ($a, $b) {
      return strcmp($a["label"], $b["label"]);
    });

    // In for future extentions we want to use the title pattern within the tiple store for the actual SPARQL query
    $ret = array_slice($matches, 0, $this->autocomplete_suggestions_limit);
    if(count($matches) >= $this->autocomplete_suggestions_limit){
      $ret[] = array('label' => "More hits were found, continue typing...");
    }

    #dpm($matches, "out");
    return new JsonResponse($ret);
  }
}
