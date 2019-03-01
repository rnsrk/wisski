<?php

namespace Drupal\wisski_textanly\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 *
 */
class TestPage extends ControllerBase {

  /**
   *
   */
  public function testPage() {

    $service = \Drupal::service('wisski_pipe.pipe');
    $pipes = $service->loadMultiple();
    $options = [];
    foreach ($pipes as $pipe) {
      $options[$pipe->id()] = $pipe->label();
    }

    $form['text'] = [
      '#type' => 'textarea',
      '#title' => t('Text'),
      '#attributes' => ['id' => 'analyse_text'],
    ];
    $form['pipe'] = [
      '#type' => 'select',
      '#title' => t('Pipe'),
      '#options' => $options,
      '#attributes' => ['id' => 'analyse_pipe'],
    ];
    $form['analyse'] = [
      '#markup' => '<p><a id="analyse_do" href="#">Analyse</a></p>',
    ];
    $form['result'] = [
      '#type' => 'fieldset',
      '#title' => t('Result'),
    // Drupal 8 way to add css and js files, see .libraries.yml file.
      '#attached' => [
        'library' => ['wisski_textanly/test_page'],
      ],
    ];
    $form['result']['value'] = [
      '#prefix' => '<div><pre id="analyse_result" class="json_dump"></pre></div>',
      '#value' => '',
    ];
    $form['logs'] = [
      '#type' => 'fieldset',
      '#title' => t('Logs'),
    ];
    $form['logs']['value'] = [
      '#prefix' => '<div><pre id="analyse_log" class="json_dump"></pre></div>',
      '#value' => '',
    ];

    return $form;
  }

}
