<?php

namespace Drupal\wisski_doi\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\wisski_doi\Exception\WisskiDoiSettingsNotFoundException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * Handles the communication with DOI REST API.
 *
 * Contains function to create, read, update and delete DOIs.
 *
 * @package Drupal\wisski_doi\Controller
 */
class WisskiDoiRestController extends ControllerBase {

  /**
   * Guzzle\Client instance.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

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
   * Receive DOIs from repo.
   *
   * @param array $doiInfo
   *   The DOI Schema for the provider.
   *
   * @throws \GuzzleHttp\Exception\RequestException|\Exception
   *   Throws exception when response status 40x.
   */
  public function getDoi(array $doiInfo) {

    $body = [
      "data" => [
        "attributes" => [
          "event" => "publish",
          "creators" => [
            [
              "name" => $doiInfo['author'],
            ],
          ],
          "titles" => [
            [
              "title" => $doiInfo['title'],
            ],
          ],
          "dates" => [
            "dateType" => 'Created',
            "dateInformation" => $doiInfo['creationDate'],
          ],

          "prefix" => $this->doiSettings['doiPrefix'],
          "publisher" => $doiInfo['publisher'],
          "publicationYear" => substr($doiInfo['creationDate'], 6, 4),
          "language" => $doiInfo['language'],
          "types" => [
            "resourceTypeGeneral" => "Dataset",
          ],
          "url" => $doiInfo['revisionUrl'],
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
    catch (RequestException $error) {
      // Get the original response.
      $response = $error->getResponse();
      // Get the info returned from the remote server.
      $error_content = empty($response) ? ['errors' => [['status' => "500"]]] : json_decode($response->getBody()->getContents(), TRUE);

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

        case "404":
          $error_tip = 'Seems you have a typo in your DOI credentials,
          watch out for leading or trailing whitespaces.';
          break;

        case "500":
          $error_tip = 'There was no response at all, have you defined the base uri?';
        default:
          $error_tip = 'Sorry, no tip for this error code.';
      }

      // Error Code and Message.
      $message = $this->t('API connection error. Error code: %error_code. Error message: %error_message %error_tip', [
        '%error_code' => $error_content['errors'][0]['status'],
        '%error_message' => $error_content['errors'][0]['title'],
        '%error_tip' => $error_tip,
      ]);
      $this->messenger()
        ->addError($message);
      // Log the error.
      \Drupal::logger('wisski_doi')->error($message);
      return NULL;
    }

    // A non-Guzzle error occurred. The type of exception
    // is unknown, so a generic log item is created.
    catch (\Exception $error) {
      // Log the error.
      \Drupal::logger('wisski_doi')
        ->error($this->t('An unknown error occurred while trying to connect to the remote API. This is not a Guzzle error, nor an error in the remote API, rather a generic local error occurred. The reported error was @error', ['@error' => $error->getMessage()]));
      $response = $error->getResponse();
    }
    catch (GuzzleException $error) {
      $response = $error->getResponse();
    }

    /*
     * Write response to database table wisski_doi.
     */
    $dbData = [
      "doi" => $response['data']['id'],
      "vid" => $doiInfo['revisionId'] ?? NULL,
      "eid" => $doiInfo['entityID'],
      "type" => $response['data']['attributes']['state'],
      "revisionUrl" => $doiInfo['revisionUrl'],
    ];
    (new WisskiDoiDbController)->writeToDb($dbData);
  }

}
