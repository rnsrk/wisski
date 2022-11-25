<?php

namespace Drupal\wisski_fire_brigade\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB;

class GraphCleanupForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Drupal messenger
   *
   * @var Drupal\Core\Messenger\Messenger
   */
  protected $messenger;


  /**
   * The constructor.
   */
  public function __construct(
      EntityTypeManagerInterface $entity_type_manager,
      Messenger $messenger,
      ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('entity_type.manager'),
        $container->get('messenger')
        );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(){
    return self::class;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state){
    $storage = $this->entityTypeManager->getStorage('wisski_salz_adapter');

    $rows = [];

    foreach($storage->loadMultiple() as $machineName => $adapter){
      $differingUris = $this->getDifferingUris($adapter->getEngine());
      foreach($differingUris as $graph => $uris){
        foreach($uris as $uri => $amount){
          $rows[] = [
            'graph' => $graph,
            'uri' => $uri,
            'amount' => $amount
          ];
        }
      }
    }
    if(!empty($rows)){
      $form['tooltip'] = array(
        '#type'=> 'markup',
        '#markup' => "<h4>Move URIs that differ from their graphs URI to the correct URI prefix</h4>"
        );
      $form['table'] = array(
          '#type' => 'tableselect',
          '#header' => array(
            'graph' => t('Graph'),
            'uri' => t('Differing base URI'),
            'amount' => t('Amount of Triples'),
            ),
          '#options' => $rows
          );
      $form['submit'] = array(
          '#type' => 'submit',
          '#value' => t('Rename'),
          );
      $this->messenger->addWarning(t('Warning: Please do not proceed without backups of the triplestore and SQL database! '));
    }
    else {
      $this->messenger->addMessage(t('No URIs with differing base URI found!'));
    }
    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state){
    $storage = $this->entityTypeManager->getStorage('wisski_salz_adapter');

    foreach($storage->loadMultiple() as $machineName => $adapter){
      foreach($form['table']['#value'] as $i){
        $row = $form['table']['#options'][$i];
        $engine = $adapter->getEngine();
        if(!$engine instanceof Sparql11EngineWithPB){
          continue;
        }
        $query = self::cleanupGraphsQuery($row['graph'], $row['uri']);
        $engine->directUpdate($query);
      }
    }
    return $form_state;
  }


  /**
   * Searches graphs for URIs that do not
   * start with their graphs URI.
   *
   * @param \Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB $engine
   *  The engine to perform the search on.
   *
   * @return array
   *  Info about the differing URIs:
   *  [$graphURI][$oddURI] = $amount
   *
   *  $graphURI: URI of th graph.
   *  $oddURI: URI that does not match the graph URI
   *  $amount: amount of triples with this non-matching URI
   */
  protected function getDifferingUris($engine){
    if(!($engine instanceof Sparql11EngineWithPB)){
		  return [];
	  }

    // get the graphs with differing uris
    try{
      $graphsResult = $engine->directQuery(self::differingGraphsQuery());
    }
    catch(Exception $e){
      \Drupal::logger("wisski_fire_brigade")->error("Query failed");
    }


    $mismatchedUris = [];
    foreach($graphsResult as $graphRow){
      $graph = $graphRow->g->getUri();

      if(str_contains($graph, 'originatesFrom') || str_contains($graph, 'baseFields')){
        continue;
      }

      // get the base URI of the differing URIs and their count
      try{
        $urisResult = $engine->directQuery($this->getDifferingUrisForGraphQuery($graph));
      } catch (Exception $e){
        \Drupal::logger("wisski_fire_brigade")->error("Query failed");
      }

      foreach($urisResult as $uriRow){
        $base = $uriRow->base->getValue();
        $count = $uriRow->count->getValue();
        $mismatchedUris[$graph][$base] = $count;
      }
    }
    return $mismatchedUris;
  }

  // Queries

  /**
   * Returns a SPARQL query that searches for graphs which contain 
   * URIs that do not fully contain the graph's URI
   *
   * @return string
   *  The query
   */
  static function differingGraphsQuery() : string {
    return "SELECT ?g where {
      GRAPH ?g {
        ?s ?p ?o
      }
      FILTER(!strStarts(str(?s), str(?g)))
    } GROUP BY ?g";
  }

  /**
   * Returns a SPARQL query that searches a particular graph for
   * differing base URIs. Also returns how many URIs differ.
   * 
   * @param string
   *  The graph to be searched
   * 
   * @return string
   *  The query
   */
  static function getDifferingUrisForGraphQuery(string $graph) : string {
    return "SELECT ?base (sum(if(!strStarts(str(?s), str(\"$graph\")), 1, 0)) as ?count) WHERE {
      GRAPH <$graph> { ?s ?p ?o }
      FILTER(!strStarts(str(?s), str(\"$graph\"))) .
      BIND(REPLACE(str(?s) , '.[^/]*$', '/' ) AS ?base)
    }
    GROUP BY ?base";
  }

  /**
   * Returns a SPARQL query that replaces all occurences
   * of oddURI with the graphs URI
   *
   * @param string $graph
   *  The URI of the graph.
   * @param string $oddUri
   *  The URI to be replaced.
   *
   * @return string
   *  The query.
   */
  static function cleanupGraphsQuery(string $graph, string $oddUri) : string {
    return "DELETE { GRAPH ?graph { ?oldSubj ?p ?oldObj }}
    INSERT { GRAPH ?graph { ?newSubj ?p ?newObj }}
    WHERE {
      GRAPH ?graph { ?oldSubj ?p ?oldObj }
      BIND(IRI(REPLACE(str(?oldSubj),\"$oddUri\",\"$graph\")) AS ?newSubj)
      BIND(IRI(REPLACE(str(?oldPred),\"$oddUri\",\"$graph\")) AS ?newPred)
      BIND(IF(
            ISIRI(?oldObj),
            IRI(REPLACE(str(?oldObj),\"$oddUri\",\"$graph\")),
            ?oldObj
            )
          AS ?newObj)
      FILTER(strStarts(str(?oldSubj), \"$oddUri\"))
    }";
  }
}
