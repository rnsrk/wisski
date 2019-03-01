<?php

namespace Drupal\wisski_pipe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\wisski_apus\StackingLogger;
use Psr\Log\LogLevel;

/**
 *
 */
class Analyser extends ControllerBase {

  /**
   * Web service callback: Analyse a text and return a json object
   * containing proposed annotations.
   *
   * Supports parameters in $q. All are optional but 'pipe'.
   *
   * @author Martin Scholz
   */
  public function ajaxAnalyse() {

    $q = (object) [
      'ticket' => NULL,
      'data' => NULL,
      'pipe' => NULL,
      'log' => TRUE,
    ];
    \Drupal::service('wisski_apus.util')->parseHttpParams($q, "query");

    if (empty($q->pipe)) {
      // Found no text parameter.
      throw new \RuntimeException("no pipe argument given or invalid value");
    }

    $ticket = $q->ticket;
    if (empty($ticket)) {
      $uuid_service = \Drupal::service('uuid');
      $ticket = $uuid_service->generate();
    }

    // We store the log messages for one day
    // should be enough for a testing page.
    $logger = $q->log ? new StackingLogger(NULL, "wisski_pipe_log_$ticket") : NULL;

    // Run the pipe.
    $data = \Drupal::service('wisski_pipe.pipe')->run($q->pipe, $q->data, $ticket, $logger);

    if ($q->log) {
      $log_stack = $logger->getStack();
    }

    // Print result.
    $json = [
      'ticket' => $ticket,
      'data' => $data,
    ];

    if ($q->log) {
      $json['log'] = $logger->getStack();
    }

    // The symfony resonse object sets the right header.
    return new JsonResponse($json);

  }

  /**
   * Web service callback: Return the logs of an analysis as a json object.
   *
   * Supported parameters in $q are 'ticket' (mandatory) and 'logs' (optional).
   * The latter is an array of log levels to return. By default only returns info level.
   *
   * @author Martin Scholz
   */
  public function jsonLog() {

    $q = (object) [
      'ticket' => NULL,
      'levels' => NULL,
    ];
    \Drupal::service('wisski_apus.util')->parseHttpParams($q, "query");

    $ticket = $q->ticket;
    if ($ticket === NULL) {
      // Found no text parameter.
      new \RuntimeException("no ticket specified");
    }

    if ($q->levels == 'all') {
      $levels = [];
    }
    elseif (is_scalar($q->levels)) {
      $levels = [$q->levels];
    }
    elseif (is_array($q->levels)) {
      $levels = $q->levels;
    }
    else {
      $levels = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
      ];
    }

    // Prepare json object that is to be returned.
    $json = ['ticket' => $ticket, 'log' => []];

    // Restore the logging stack.
    $logger = new StackingLogger(NULL, "wisski_pipe_log_$ticket");
    $logger->restoreFromCache();

    $stack = $logger->getStack();
    if (!empty($stack)) {
      if (!empty($levels)) {
        foreach ($stack as $entry) {
          if (in_array($entry['level'], $q->levels)) {
            $json['log'][] = $entry;
          }
        }
      }
      else {
        $json['log'] = $stack;
      }
    }

    // The symfony resonse object set the right header.
    return new JsonResponse($json);

  }

  /**
   * Display the log messages for a certain ticket on an HTML page.
   *
   * We just sent a stub blank page and let JS fetch the log entries.
   *
   * @author Martin Scholz
   */
  public function htmlLog($ticket) {

    if ($ticket === NULL) {
      // Found no text parameter.
      throw new RuntimeException("no ticket specified");
    }

    $page = [];
    $page['#attached']['library'][] = 'wisski_pipe/log_page';
    $page['#attached']['drupalSettings']['WissKI']['pipe']['log_page']['ticket'] = $ticket;
    $page['#markup'] = '<pre id="analyse_log" class="json_dump"></pre>';

    return $page;

  }

}
