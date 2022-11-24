<?php

/**
 * @file
 * Provide views data for wisski_statistics.module.
 */

/**
 * Implements hook_views_data().
 */
function wisski_statistics_views_data() {
  $data = [];

  $requests = 'wisski_statistics_requests';
  // Fields.
  $data[$requests]['id'] = [
    'title' => t('ID of the request.'),
    'help' => t('Access ID.'),
    'field' => [
      'id' => 'numeric',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
  ];
  $data[$requests]['url'] = [
    'title' => t('URL'),
    'help' => t('The accessed URL.'),
    'field' => [
      'id' => 'standard',
    ],
  ];
  $data[$requests]['timestamp'] = [
    'title' => t('Timestamp'),
    'help' => t('The timestamp of the request.'),
    'field' => [
      'id' => 'date',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'date',
    ],
  ];


  $pageVisits = 'wisski_statistics_page_visits';
  // Fields.
  $data[$pageVisits]['url'] = [
    'title' => t('URL'),
    'help' => t('The accessed URL.'),
    'field' => [
      'id' => 'standard',
    ],
  ];
  $data[$pageVisits]['visits'] = [
    'title' => t('Visits'),
    'help' => t('The number of times this page was visites.'),
    'field' => [
      'id' => 'numeric',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'numeric',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
  ];
  return $data;
}
