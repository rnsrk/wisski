<?php

namespace Drupal\wisski_adapter_sparql11_pb\Controller;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use \Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\wisski_core;
use Drupal\wisski_salz\AdapterHelper;
use Drupal\wisski_salz\Plugin\wisski_salz\Engine\Sparql11Engine;
use Drupal\Core\Controller\ControllerBase;


class Sparql11TriplesTabController extends ControllerBase {

  public function forward($wisski_individual,$wisski_bundle) {

    $storage = \Drupal::entityManager()->getStorage('wisski_individual');

    $entity = $storage->load($wisski_individual);

    $uris = AdapterHelper::getUrisForDrupalId($entity->id());

    // first, list all the URIs associated with this entity
    $form['uris'] = array(
      '#type' => 'table',
      '#caption' => $this->t('Associated URI(s)'),
      '#rows' => array_map(function ($a) { return array(
        '#value' => $a,
      ); }, $uris),
    );

    // build a table of incoming and outgoing triples
    $in_triples = array(); // subj pred adapter
    $out_triples = array(); // pred obj adapter
    
    $adapters = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->loadMultiple();
    foreach ($adapters as $a) {
      $label = $a->label();
      $e = $a->getEngine();
      if ($e instanceof Sparql11Engine) {
        $values = 'VALUES ?x { <' . join('> <', $uris) .'> } ';
        $q = "SELECT ?s ?sp ?po ?o WHERE { $values { { ?s ?sp ?x } UNION { ?x ?po ?o } } }";
        $results = $e->directQuery($q);
        foreach ($results as $result) {
#var_dump($result);
          if (isset($result->sp)) {
            $in_triples[] = array(
              "<" . $result->s->getUri() . ">",
              "<" . $result->sp->getUri() . ">",
              $label
            );
          } else {
            $out_triples[] = array(
              "<" . $result->po->getUri() . ">",
              $result->o instanceof \EasyRdf_Resource ? "<" . $result->o->getUri() . ">" : '"' . $result->o->getValue() . '"',
              $label
            );
          }
        }
      }
    }
    
    $form['in_triples'] = array(
      '#type' => 'table',
      '#caption' => $this->t('In-coming triples'),
      '#header' => array('Subject', 'Predicate', 'Adapter'),
      '#rows' => $in_triples,
    );
    $form['out_triples'] = array(
      '#type' => 'table',
      '#caption' => $this->t('Out-going triples'),
      '#header' => array('Predicate', 'Object', 'Adapter'),
      '#rows' => $out_triples,
    );

    return $form;

  }
}