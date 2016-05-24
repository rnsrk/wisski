<?php
/**
 * @file
 * Contains \Drupal\wisski_adapter_sparql11_pb\Controller\Sparql11AutocompleteController.
 */
   
  namespace Drupal\wisski_adapter_sparql11_pb\Controller;
   
  use Symfony\Component\HttpFoundation\JsonResponse;
  use Symfony\Component\HttpFoundation\Request;
  use Drupal\Component\Utility\Unicode;
  use Drupal;
   
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
#      drupal_set_message("fun: " . serialize(func_get_args()));
#      drupal_set_message("pb: " . serialize($pbid));
#      $matches = array();
#      $matches[] = array('value' => "dfdf", "label" => "sdfsdffd");
#      return new JsonResponse($matches);
      $string = $request->query->get('q');
      if ($string) {
#        drupal_set_message("str: " . serialize($string));
#        drupal_set_message("pathid: " . serialize($pathid));
        $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($pathid);
        $pb = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::load($pbid);
        $adapter = \Drupal\wisski_salz\Entity\Adapter::load($engineid);
        
#        drupal_set_message("path: " . serialize($path));
#        drupal_set_message("pb: " . serialize($pb));
#        drupal_set_message("adapter: " . serialize($adapter));

        $engine = $adapter->getEngine();

        if(empty($path))
          return NULL;
        
        if($path->getDisamb()) {
          $sparql = "SELECT * WHERE { GRAPH ?g { ";
          $sparql .= $engine->generateTriplesForPath($pb, $path, NULL, NULL, NULL, NULL, $path->getDisamb(), FALSE);
          $sparql .= " FILTER regex( ?out, '$string') . } }";        
        } else {
          $sparql = "SELECT * WHERE { GRAPH ?g { ";
          $sparql .= $engine->generateTriplesForPath($pb, $path, NULL, NULL, NULL, NULL, NULL, FALSE);
          $sparql .= " FILTER regex( ?out, '$string') . } }";
        }
      }
      
      if(empty($sparql))
        return NULL;
        
#      drupal_set_message("engine: " . serialize($sparql));
        
      $result = $engine->directQuery($sparql);
      $matches = array();
      $i=0;
      foreach($result as $key => $thing) {
        $matches[] = array('value' => $thing->out->getValue(), 'label' => $thing->out->getValue());
        $i++;
        
        if($i > 15) {
          $matches[] = array('label' => "More hits were found, continue typing...");
          break;
        }
      }
      
#      drupal_set_message("result: " . serialize($result)); //"sparql is: " . serialize($sparql));
            
      return new JsonResponse($matches);
    }
  }
