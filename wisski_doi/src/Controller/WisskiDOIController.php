<?php

namespace Drupal\wisski_doi\Controller;

/**
 * Controller to render main admin page
 */

class WisskiDOIController {

  /**
   * Main tab for provider credentials.
   * @return string[]
   */
  public function doiRepositorySettings() {
    return [
      '#markup' => 'Repository settings',
    ];
  }

  /**
   * Tab to request DOIs for selected individual.
   * @return string[]
   */
  public function addSingleDraftDOI() {
    $httpClient = \Drupal::httpClient();
    dpm('doiIndividualRequest');
    $response = $httpClient->request('POST', 'https://api.test.datacite.org/dois', [

      'body' => '{"data":{"attributes":{"creators":[{"name":"WissKI Dev"},{}],"titles":[{"title":"WissKI Dataset Title"}],"prefix":"10.82102","publisher":"WissKI","publicationYear":2021}}}',

      'headers' => [

        'Authorization' => 'Basic '. base64_encode("user:password"),

        'Content-Type' => 'application/vnd.api+json',

      ],

    ]);
  }
}
