<?php

namespace Drupal\wisski_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\wisski_core\WisskiBundleInterface;

/**
 *
 */
class WIsskiEntityController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function content() {
    $form = [];
    $form[] = [
      '#type' => 'markup',
      '#markup' => t('Hello World!'),
    ];
    $form[] = [
      '#type' => 'textfield',
      '#default_value' => 'murks',
    ];
    return $form;
  }

  /**
   *
   */
  public function add(WisskiBundleInterface $wisski_bundle) {
    // dpm(microtime(), "before");.
    $entity = $this->entityManager()->getStorage('wisski_individual')->create(
        [
          'bundle' => $wisski_bundle->id(),
        ]
    );
    // dpm(microtime(), "in");.
    $form = $this->entityFormBuilder()->getForm($entity);

    // dpm(microtime(), "after");.
    return $form;
  }

}
