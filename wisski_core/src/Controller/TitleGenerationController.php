<?php

namespace Drupal\wisski_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 *
 */
class TitleGenerationController extends ControllerBase {

  /**
   *
   */
  public function generateByBundle($bundle) {

    if (!is_object($bundle)) {
      $bundle = entity_load('wisski_bundle', $bundle);
    }
    if (empty($bundle)) {
      throw new \InvalidArgumentException("You must specify a valid bundle");
    }
    // Set up a batch job as there may be many individuals to process.
    $batch = [
      'operations' => [
    // First and only operation.
        [
          [static::class, 'processBatch'],
          [$bundle->id()],
        ],
      ],
      'title' => $this->t('Generating titles for all %bundle'),
      'progressive' => TRUE,
      'progress_message' => '@current processed. Time elapsed: @elapsed',
      'finished' => [static::class, 'finishBatch'],
    ];
    batch_set($batch);
    // Start the batch job right now.
    return batch_process(Url::fromRoute('<front>'));

  }

  /**
   * Implements callback_batch_operation() - perform processing on each batch.
   *
   * Updates the titles of entities.
   *
   * @param string $bundle_id
   *   ID of the bundle of which all entities are updated.
   * @param mixed $context
   *   Batch context information.
   */
  public static function processBatch($bundle_id, &$context) {
    // 500 per turn.
    $amount = 500;
    $query = \Drupal::entityQuery('wisski_individual');
    $query->condition('bundle', $bundle_id);
    $offset = isset($context['sandbox']['progress']) ? $context['sandbox']['progress'] : 0;
    $query->range($offset, $amount);
    $eids = $query->execute();
    if (empty($eids)) {
      $context['finished'] = 1;
      $context['results'] = [
        'total' => $offset,
      ];
    }
    else {
      foreach ($eids as $eid) {
        wisski_core_generate_title($eid, NULL, TRUE, $bundle_id);
      }
      $context['sandbox']['progress'] = $offset + count($eids);
      // We do not know the total number of individuals so we just
      // asymptotically grow towards 1...
      $context['finished'] = 1 - ($amount / (1 + $context['sandbox']['progress']));
    }
  }

  /**
   * Implements callback for batch finish.
   *
   * @param bool $success
   *   Indicates whether we hit a fatal PHP error.
   * @param array $results
   *   Contains batch results.
   * @param array $operations
   *   If $success is FALSE, contains the operations that remained unprocessed.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Where to redirect when batching ended.
   */
  public static function finishBatch($success, $results, $operations) {
    if ($success) {
      drupal_set_message(t('Updated titles of @total entities', ['@total' => $results['total']]));
    }
    else {
      drupal_set_message(t('An error occurred while updating the titles.'), 'error');
    }
  }

}
