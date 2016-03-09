<?php

/**
 * @file
 * Contains \Drupal\wisski_salz\Plugin\WisskiSalzAdapterPlugin\WisskiSparql11Plugin.
 */

namespace Drupal\wisski_salz\Plugin\WisskiSalzAdapterPlugin;

use Drupal\wisski_salz\WisskiSalzAdapterPluginBase;

/**
 * Wiki implementation of an external entity storage client.
 *
 * @WisskiSalzAdapterPlugin(
 *   id = "wisski_sparql11",
 *   name = "Sparql 1.1"
 * )
 */
class WisskiSparql11Plugin extends WisskiSalzAdapterPluginBase {

  /**
   * {@inheritdoc}
   */
/*
  public function delete(\Drupal\wisski_salz\ExternalEntityInterface $entity) {
    $this->httpClient->delete(
      $this->configuration['endpoint'] . '/' . $entity->externalId(),
      ['headers' => $this->getHttpHeaders()]
    );
  }
*/

  /**
   * {@inheritdoc}
   */
/*
  public function load($id) {
    $options = [
      'headers' => $this->getHttpHeaders(),
      'query' => [
        'pageids' => $id,
      ],
    ];
    if ($this->configuration['parameters']['single']) {
      $options['query'] += $this->configuration['parameters']['single'];
    }
    $response = $this->httpClient->get(
      $this->configuration['endpoint'],
      $options
    );
    $result = $this->decoder->getDecoder($this->configuration['format'])->decode($response->getBody());
    return (object) $result['query']['pages'][$id];
  }
*/

  /**
   * {@inheritdoc}
   */
/*
  public function save(\Drupal\wisski_salz\ExternalEntityInterface $entity) {
  
    drupal_set_message(serialize($entity->getMappedObject()));
    return;
  
    if ($entity->externalId()) {
      $response = $this->httpClient->put(
        $this->configuration['endpoint'] . '/' . $entity->externalId(),
        [
          'body' => (array) $entity->getMappedObject(),
          'headers' => $this->getHttpHeaders()
        ]
      );
      $result = SAVED_UPDATED;
    }
    else {
      $response = $this->httpClient->post(
        $this->configuration['endpoint'],
        [
          'body' => (array) $entity->getMappedObject(),
          'headers' => $this->getHttpHeaders()
        ]
      );
      $result = SAVED_NEW;
    }

    // @todo: is it standard REST to return the new entity?
    $object = (object) $this->decoder->getDecoder($this->configuration['format'])->decode($response->getBody());
    $entity->mapObject($object);
    return $result;
  }
*/

  /**
   * {@inheritdoc}
   */
/*
  public function query(array $parameters) {
#    drupal_set_message('param: ' . serialize($parameters+ $this->configuration['parameters']['list']));
#    drupal_set_message(serialize($this->configuration['endpoint']));
    try {
    $response = $this->httpClient->get(
      $this->configuration['endpoint'],
      [
        'query' => $parameters + $this->configuration['parameters']['list'],
        'headers' => $this->getHttpHeaders()
      ]
    );
#    drupal_set_message(serialize($response->getBody()));
    $results = $this->decoder->getDecoder($this->configuration['format'])->decode($response->getBody());
    drupal_set_message(serialize($results));
    $results = $results['query']['categorymembers'];
    } catch ( Exception $e ) {
      drupal_set_message(serialize($e));
    }

    foreach ($results as &$result) {
      $result = ((object) $result);
    }
    return $results;
  }
*/
}
