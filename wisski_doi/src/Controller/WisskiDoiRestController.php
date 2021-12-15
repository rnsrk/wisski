<?php

namespace Drupal\wisski_doi\Controller;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Drupal\Core\Controller\ControllerBase;
use Drupal\wisski_doi\Exception\WisskiDoiSettingsNotFoundException;

/**
 * Handles the communication with DOI REST API.
 *
 * Contains function to create, read, update and delete DOIs.
 *
 * @package Drupal\wisski_doi\Controller
 */
class WisskiDoiRestController extends ControllerBase {

  /**
   * Drupal http Rest Client.
   *
   * @var \GuzzleHttp\Client
   */

  private Client $httpClient;

  /**
   * Settings from DOI Configuration page.
   *
   * @var array
   */

  private array $doiSettings;

  /**
   * Construct instance with DOI settings and check them.
   *
   * Create a GuzzleClient localy (may a service injection is better?)
   * Take settings from wisski_doi_settings form
   * (Configuration->[WISSKI]->WissKI DOI Settings)
   * Checks if settings are missing.
   */
  public function __construct() {
    $this->httpClient = \Drupal::httpClient();
    $settings = \Drupal::configFactory()
      ->getEditable('wisski_doi.wisski_doi_settings');

    $this->doiSettings = [
      "baseUri" => $settings->get('doi_base_uri'),
      "doiRepositoryId" => $settings->get('doi_repository_id'),
      "doiPrefix" => $settings->get('doi_prefix'),
      "doiRepositoryPassword" => $settings->get('doi_repository_password'),
    ];
    try {
      (new WisskiDoiSettingsNotFoundException)->checkDoiSetting($this->doiSettings);
    }
    catch (WisskiDoiSettingsNotFoundException $error) {
      $this->messenger()
        ->addError($error->getMessage());
    }

  }

  /**
   * Receive draft DOIs from repa.
   *
   * @param array $table
   *   The DOI Schema for the provider.
   *
   * @var array $body
   *   Scheme for DOI provider.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   *   Throws exception when respons status 40x.
   */
  public function getDraftDoi(array $table) {

    $body = [
      "data" => [
        "attributes" => [
          "creators" => [
            [
              "name" => $table['#rows'][3][1],
            ],
          ],
          "titles" => [
            [
              "title" => $table['#rows'][4][1],
            ],
          ],
          "dates" => [
            "dateType" => 'Created',
            "dateInformation" => $table['#rows'][2][1],
          ],

          "prefix" => $this->doiSettings['doiPrefix'],
          "publisher" => $table['#rows'][5][1],
          "publicationYear" => substr($table['#rows'][2][1], 6, 4),
          "language" => $table['#rows'][6][1],
          "types" => [
            "resourceTypeGeneral" => "Dataset",
          ],
          "url" => $table['#rows'][8][1],
          "schemaVersion" => "http://datacite.org/schema/kernel-4",
        ],
      ],
    ];
    $json_body = json_encode($body);
    // dpm(base64_encode($this->doiRepositoryId.":".$this->doiRepositoryPassword));.
    try {
      $response = $this->httpClient->request('POST', $this->doiSettings['baseUri'], [
        'body' => $json_body,
        'headers' => [
          'Authorization' => 'Basic ' . base64_encode($this->doiSettings['doiRepositoryId'] . ":" . $this->doiSettings['doiRepositoryPassword']),
          'Content-Type' => 'application/vnd.api+json',
        ],
        'http_errors' => TRUE,
      ]);

      $this->messenger()
        ->addStatus($this->t('DOI has been requested'));
      $response = json_decode($response->getBody()->getContents(), TRUE);
    }

    /* Try to catch the GuzzleException. This indicates a failed
     * response from the remote API.
     */
    catch (GuzzleException $error) {
      // Get the original response.
      $response = $error->getResponse();
      // Get the info returned from the remote server.
      $error_content = json_decode($response->getBody()->getContents(), TRUE);

      /*
       * Match only works in PHP 8
       * $error_tip = match ($error_content['errors'][0]['status']) {
       * "400" => 'Your used doi scheme or content data may be faulty.',
       *  default => 'Sorry, no tip for this error code.',
       * };
       */

      switch ($error_content['errors'][0]['status']) {
        case "400":
          $error_tip = 'Your used doi scheme or content data may be faulty.';
          break;

        default:
          $error_tip = 'Sorry, no tip for this error code.';
      }

      // Error Code and Message.
      $message = $this->t('API connection error. Error code: %error_code. Error message: %error_message. %error_tip', [
        '%error_code' => $error_content['errors'][0]['status'],
        '%error_message' => $error_content['errors'][0]['title'],
        '%error_tip' => $error_tip,
      ]);
      $this->messenger()
        ->addError($message);
      // Log the error.
      \Drupal::logger('wisski_doi')->error($message);
    }

    // A non-Guzzle error occurred. The type of exception
    // is unknown, so a generic log item is created.
    catch (\Exception $error) {
      // Log the error.
      \Drupal::logger('wisski_doi')
        ->error($this->t('An unknown error occurred while trying to connect to the remote API. This is not a Guzzle error, nor an error in the remote API, rather a generic local error occurred. The reported error was @error', ['@error' => $error->getMessage()]));

    }

    /*
     * Write response to database table wisski_doi.
     */
    if (isset($response)) {
      (new WisskiDoiDbController)->writeDoi($response,);
    }
    else {
      \Drupal::logger('wisski_doi')
        ->error($this->t('No valid response from DOI API'));
    }
  }

}
