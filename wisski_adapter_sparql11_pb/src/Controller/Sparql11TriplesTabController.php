<?php

namespace Drupal\wisski_adapter_sparql11_pb\Controller;

use Symfony\Component\Routing\Exception\InvalidParameterException;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use \Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\wisski_core;
use Drupal\wisski_salz\AdapterHelper;
use Drupal\wisski_salz\Plugin\wisski_salz\Engine\Sparql11Engine;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;


class Sparql11TriplesTabController extends ControllerBase {

  // handles the triple tab from an entity
  const ENTITY_ROUTE = 'wisski_adapter_sparql11_pb.wisski_individual.triples';

  // handles triple tab only provided with URI
  const URI_ROUTE = 'wisski_adapter_sparql11_pb.triples';

    
  /**
   * Routine for handling the WissKI Entity Triples Tab.
   *
   * @param  int $wisski_individual
   *  The EID of the current entity
   * 
   * @return array
   */
  public function forward(int $wisski_individual) {
    $storage = \Drupal::service('entity_type.manager')->getStorage('wisski_individual');

    //let's see if the user provided us with a bundle, if not, the storage will try to guess the right one
    $match = \Drupal::request();

    // TODO: check if this is evil
    $bundle_id = $match->query->get('wisski_bundle');
    if ($bundle_id) $storage->writeToCache($wisski_individual, $bundle_id);

    // get the target uri from the parameters
    $target_uri = $match->query->get('target_uri');

    // if it is empty, the entity is the starting point
    if (empty($target_uri)) {
      $target_uri = AdapterHelper::getOnlyOneUriPerAdapterForDrupalId($wisski_individual);
      $target_uri = current($target_uri);
    } else // if not we want to view something else
      $target_uri = urldecode($target_uri);

    return $this->generateTriplesForm($target_uri, $wisski_individual);
  }

    
  /**
   * Routine for handling the headless Triples tab.
   *
   * @return array
   */
  function forwardUri(){
		$query = \Drupal::request();
		// read URI from query
		$target_uri = $query->get('target_uri', null);

    if(empty($target_uri)){
      // TODO: build a form that allows inputting a URI here
      return array(
        '#type' => 'item',
        '#markup' => 'To pass a URI please use the URI parameter ?target_uri='
      );
    }
    return $this->generateTriplesForm($target_uri);
  }

  
  /**
   * Generates the URL for the passed URI depending on the context
   *
   * @param  string $route
   *  The route to be used for redirection
   * @param  string $target_uri
   *  The URI that should be linked to
   * @param  int $wisski_individual_id
   *  The EID that is used for context
   * 
   * @return Url
   */
  private function getURL(string $route, string $target_uri, int $wisski_individual=-1){
    $parameters = [];
    $parameters['target_uri'] = $target_uri;

    if($wisski_individual != -1){
      dpm($wisski_individual);
      $route = self::ENTITY_ROUTE;
      $parameters['wisski_individual'] = $wisski_individual;
    }
    return Url::fromRoute($route, $parameters);
  }
      
  /**
   * Builds a triples form consisting of incoming and 
   * outgoing triples for a particular URI
   *
   * @param  string $target_uri
   *  The URI for which the tables 
   * @param  int $wisski_individual
   * 
   * @return array
   */
  function generateTriplesForm(string $target_uri, int $wisski_individual=-1) {
    $route = self::URI_ROUTE;
    if($wisski_individual != -1){
      $route = self::ENTITY_ROUTE;
    }

    if(empty($target_uri)){
      return array(
        '#type' => 'markup',
        '#markup' => $this->t("Please provide a URI via the ?target_uri= query parameter.")
      );
    }

    // go through all adapters    
    $adapters = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->loadMultiple();

    $form['in_triples'] = array(
      '#type' => 'table',
      '#caption' => $this->t('In-coming triples'),
      '#header' => array('Subject', 'Predicate', 'Object', 'Graph', 'Adapter'),
    );

    $form['out_triples'] = array(
      '#type' => 'table',
      '#caption' => $this->t('Out-going triples'),
      '#header' => array('Subject', 'Predicate', 'Object', 'Graph', 'Adapter'),
    );

    foreach ($adapters as $a) {
      $label = $a->label();
      $e = $a->getEngine();
      if ($e instanceof Sparql11Engine) {
        $values = 'VALUES ?x { <' . $target_uri . '> } ';
        $q = "SELECT ?g ?s ?sp ?po ?o WHERE { $values { { GRAPH ?g { ?s ?sp ?x } } UNION { GRAPH ?g { ?x ?po ?o } } } }";
        #        dpm($q);
        $results = $e->directQuery($q);
        foreach ($results as $result) {
          #var_dump($result);
          if (isset($result->sp)) {

            $existing_bundles = $e->getBundleIdsForUri($result->s->getUri());
            if (empty($existing_bundles)){
              $subjecturi = $this->getUrl($route, $target_uri, $wisski_individual);
            }
            else {
              $remote_entity_id = $e->getDrupalId($result->s->getUri());
              $subjecturi = $this->getURL($route, $result->s->getUri(), $remote_entity_id);
            }

            $predicateuri = $this->getURL($route, $result->sp->getUri(), $wisski_individual);
            $objecturi = $this->getUrl($route, $target_uri, $wisski_individual);

            $form['in_triples'][] = array(
              #              "<" . $result->s->getUri() . ">",
              Link::fromTextAndUrl($this->t($result->s->getUri()), $subjecturi)->toRenderable(),
              Link::fromTextAndUrl($this->t($result->sp->getUri()), $predicateuri)->toRenderable(),
              Link::fromTextAndUrl($this->t($target_uri), $objecturi)->toRenderable(),
              array('#type' => 'item', '#title' => $result->g->getUri()),
              array('#type' => 'item', '#title' => $label),
            );
          } else {

            $subjecturi = $this->getURL($route, $target_uri, $wisski_individual);
            $predicateuri = $this->getURL($route, $result->po->getUri(), $wisski_individual);

            if ($result->o instanceof \EasyRdf_Resource or get_class($result->o) == "EasyRdf\Resource") {
              try {

                $existing_bundles = $e->getBundleIdsForUri($result->o->getUri());
                if (empty($existing_bundles))
                  $objecturi = $this->getURL($route, $result->o->getUri(), $wisski_individual);
                else {
                  $remote_entity_id = $e->getDrupalId($result->o->getUri());
                  $objecturi = $this->getUrl($route, $result->o->getUri(), $remote_entity_id);
                }
                $got_target_url = TRUE;
              } catch (InvalidParameterException $ex) {
                $got_target_url = FALSE;
              }
              $object_text = $result->o->getUri();
            } else {
              $got_target_url = FALSE;
              $object_text = $result->o->dumpValue('string');
            }
            $graph_uri = isset($result->g) ? $result->g->getUri() : 'DEFAULT';
            $form['out_triples'][] = array(
              Link::fromTextAndUrl($target_uri, $subjecturi)->toRenderable(),
              Link::fromTextAndUrl($result->po->getUri(), $predicateuri)->toRenderable(),
              $got_target_url ? Link::fromTextAndUrl($object_text, $objecturi)->toRenderable() : array('#type' => 'item', '#title' => $object_text),
              array('#type' => 'item', '#title' => $graph_uri),
              array('#type' => 'item', '#title' => $label),
            );
          }
        }
      }
    }

    $form['#title'] = $this->t('View Triples for ') . $target_uri;

    return $form;
  }
}
