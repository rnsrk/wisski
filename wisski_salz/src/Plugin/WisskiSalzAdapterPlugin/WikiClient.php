<?php

/**
 * @file
 * Contains \Drupal\external_entities\Plugin\ExternalEntityStorageClient\RestClient.
 */

namespace Drupal\external_entities\Plugin\ExternalEntityStorageClient;

use Drupal\external_entities\ExternalEntityStorageClientBase;

/**
 * Wiki implementation of an external entity storage client.
 *
 * @ExternalEntityStorageClient(
 *   id = "wiki_client",
 *   name = "Wiki"
 * )
 */
class WikiClient extends ExternalEntityStorageClientBase {

  /**
   * {@inheritdoc}
   */
  public function delete(\Drupal\external_entities\ExternalEntityInterface $entity) {
    $this->httpClient->delete(
      $this->configuration['endpoint'] . '/' . $entity->externalId(),
      ['headers' => $this->getHttpHeaders()]
    );
  }

  /**
   * {@inheritdoc}
   */
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

  /**
   * {@inheritdoc}
   */
  public function save(\Drupal\external_entities\ExternalEntityInterface $entity) {
  
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

  /**
   * {@inheritdoc}
   */
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

}
