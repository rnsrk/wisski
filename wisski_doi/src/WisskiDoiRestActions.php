<?php

namespace Drupal\wisski_doi;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
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
class WisskiDoiRestActions {
  use StringTranslationTrait;

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
   * The messenger service.
   *
   * @var mixed
   */
  private mixed $messenger;

  /**
   * Construct instance with DOI settings and check them.
   *
   * Create a GuzzleClient locally (may a service injection is better?)
   * Take settings from wisski_doi_settings form
   * (Configuration->[WISSKI]->WissKI DOI Settings)
   * Checks if settings are missing.
   */
  public function __construct(TranslationInterface $stringTranslation) {
    $this->stringTranslation = $stringTranslation;
    $this->messenger = \Drupal::service("messenger");
    $this->httpClient = \Drupal::httpClient();
    $settings = \Drupal::configFactory()
      ->getEditable('wisski_doi.wisski_doi_settings');

    $this->doiSettings = [
      "baseUri" => $settings->get('doi_base_uri'),
      "doiRepositoryId" => $settings->get('doi_repository_id'),
      "doiSchemaVersion" => $settings->get('doi_schema_version'),
      "doiPrefix" => $settings->get('doi_prefix'),
      "doiRepositoryPassword" => $settings->get('doi_repository_password'),
    ];
    try {
      (new WisskiDoiSettingsNotFoundException)->checkDoiSetting($this->doiSettings);
    }
    catch (WisskiDoiSettingsNotFoundException $error) {
      $this->messenger->addError($error->getMessage());
    }

  }

  /**
   * Receive DOIs from repo or update existing.
   *
   * @param array $doiInfo
   *   The DOI Schema for the provider.
   * @param bool $update
   *   True, if it is a update.
   *
   * @return array
   *   Data to write to DB.
   *   Contains dbData:
   *     eid: The entity ID as eid.
   *     doi: DOI string with prefix and suffix.
   *     vid: The revision ID as vid.
   *     state: The state of the DOI (draft, registered, findable).
   *     revisionUrl: Full external URL of the revision.
   *   and responseStatus with responseCode.
   *
   * @throws \GuzzleHttp\Exception\RequestException|\Exception|
   *   Throws exception when response status 40x.
   */
  public function createOrUpdateDoi(array $doiInfo, bool $update = FALSE) {

    // Future request body as array.
    $body = [
      "data" => [
        "attributes" => [
          "event" => $doiInfo['event'],
          "creators" => [
            [
              "name" => $doiInfo['author'],
            ],
          ],
          "contributors" => $doiInfo['contributors'],
          "titles" => [
            [
              "title" => $doiInfo['title'],
            ],
          ],
          "dates" => [
            [
              "dateType" => 'Created',
              "dateInformation" => $doiInfo['creationDate'],
            ],
          ],
          "prefix" => $this->doiSettings['doiPrefix'],
          "publisher" => $doiInfo['publisher'],
          "publicationYear" => substr($doiInfo['creationDate'], 6, 4),
          "language" => $doiInfo['language'],
          "types" => [
            "resourceTypeGeneral" => "Dataset",
          ],
          "url" => $doiInfo['revisionUrl'],
          "schemaVersion" => $this->doiSettings['doiSchemaVersion'],
        ],
      ],
    ];
    // Encode to json.
    $json_body = json_encode($body);
    // dpm(base64_encode($this->doiRepositoryId.":".$this->doiRepositoryPassword));.
    try {
      if ($update) {
        // If it is an update, use PUT method.
        $method = 'PUT';
        $uri = $this->doiSettings['baseUri'] . '/' . $doiInfo['doi'];
      }
      else {
        // Else POST.
        $method = 'POST';
        $uri = $this->doiSettings['baseUri'];
      }
      // Sending request.
      $response = $this->httpClient->request($method, $uri, [
        'body' => $json_body,
        'headers' => [
          'Authorization' => 'Basic ' . base64_encode($this->doiSettings['doiRepositoryId'] . ":" . $this->doiSettings['doiRepositoryPassword']),
          'Content-Type' => 'application/vnd.api+json',
        ],
        // Error handling on.
        'http_errors' => TRUE,
      ]);
      // Decode response to array.
      $responseContent = json_decode($response->getBody()->getContents(), TRUE);
      // Messaging.
      $action = $update ? 'updated' : 'requested';
      $this->messenger->addStatus($this->t('DOI has been %action', ['%action' => $action]));

      return [
        'dbData' => [
          "doi" => $responseContent['data']['id'],
          "vid" => $doiInfo['revisionId'] ?? NULL,
          "eid" => $doiInfo['entityId'],
          "state" => $responseContent['data']['attributes']['state'],
          "revisionUrl" => $doiInfo['revisionUrl'],
        ],
        'responseStatus' => $response->getStatusCode(),
      ];
    }
    /* Try to catch the GuzzleException. This indicates a failed
     * response from the remote API.
     */
    catch (RequestException $error) {
      \Drupal::logger('wisski_doi')
        ->error($this->t('Request error: @error', ['@error' => $error->getMessage()]));
      // $errorCode = $this->errorResponse($error)->getStatusCode() ?? '500';
      return [
        'dbDate' => NULL,
        'responseStatus' => $this->errorResponse($error),
      ];
    }

    // A non-Guzzle error occurred. The type of exception
    // is unknown, so a generic log item is created.
    catch (\Exception $error) {
      // Log the error.
      \Drupal::logger('wisski_doi')
        ->error($this->t('An unknown error occurred while trying to connect to the remote API. This is not a Guzzle error, nor an error in the remote API, rather a generic local error occurred. The reported error was @error', ['@error' => $error->getMessage()]));
      return [
        'dbDate' => NULL,
        'responseStatus' => $this->errorResponse($error),
      ];
    }
    catch (GuzzleException $error) {
    }
    // Log the error.
    \Drupal::logger('wisski_doi')
      ->error($this->t('An unknown error occurred while trying to connect to the remote API. This is not a Guzzle error, nor an error in the remote API, rather a generic local error occurred. The reported error was @error', ['@error' => $error->getMessage()]));
    return [
      'dbDate' => NULL,
      'responseStatus' => $this->errorResponse($error),
    ];
  }

  /**
   * Read the metadata from DOI provider.
   *
   * @param string $doi
   *   The DOI, like 10.82102/rhwt-d19.
   *
   * @return string
   *   The response status code.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function readMetadata(string $doi) {
    try {
      $url = $this->doiSettings['baseUri'] . '/' . $doi;
      $response = $this->httpClient->request('GET', $url, [
        'headers' => [
          'Accept' => 'application/vnd.api+json',
        ],
      ]);

      $this->messenger->addStatus($this->t('Reached DOI provider, got data.'));
      return json_decode($response->getBody()->getContents(), TRUE);
    }
    /* Try to catch the GuzzleException. This indicates a failed
     * response from the remote API.
     */
    catch (RequestException $error) {
      return $this->errorResponse($error);
    }
  }

  /**
   * Delete DOI from provider DB.
   *
   * @param string $doi
   *   The DOI.
   *
   * @return string
   *   The response status code.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   */
  public function deleteDoi(string $doi) {
    try {
      $url = $this->doiSettings['baseUri'] . '/' . $doi;

      $response = $this->httpClient->request('DELETE', $url, [
        'headers' => [
          'Authorization' => 'Basic ' . base64_encode($this->doiSettings['doiRepositoryId'] . ":" . $this->doiSettings['doiRepositoryPassword']),
        ],
      ]);

      $this->messenger->addStatus($this->t('Deleted DOI from provider.'));
      return $response->getStatusCode();
    }
    /* Try to catch the GuzzleException. This indicates a failed
     * response from the remote API.
     */
    catch (RequestException $error) {
      return $this->errorResponse($error);
    }
  }

  /**
   * Provide some readable information of errors.
   *
   * @param \GuzzleHttp\Exception\RequestException $error
   *   The GuzzleHttp error response.
   *
   * @return string
   *   Error status code.
   */
  private function errorResponse(RequestException $error) {
    // Get the original response.
    $response = $error->getResponse();
    // Get the info returned from the remote server.
    $error_content = empty($response) ? ['errors' => [['status' => "500"]]] : json_decode($response->getBody()
      ->getContents(), TRUE);
    /*
     * Match only works in PHP 8
     * $error_tip = match ($error_content['errors'][0]['status']) {
     * "400" => 'Your used doi scheme or content data may be faulty.',
     *  default => 'Sorry, no tip for this error code.',
     * };
     */

    switch ($error_content['errors'][0]['status']) {
      case "400":
        $error_tip = 'Provider can not read your request. Your content data or scheme may be faulty.';
        break;

      case "401":
        $error_tip = 'Check your username and password.';
        break;

      case "403":
        $error_tip = 'Have you the full permissions to delete something? Check the Username!';
        break;

      case "404":
        $error_tip = 'Seems you have a typo in your DOI credentials,
          watch out for leading or trailing whitespaces.';
        break;

      case "405":
        $error_tip = 'Are you trying to delete a registered or findable DOI?';
        break;

      case "422":
        $error_tip = 'Your JSON values are not accepted, check your schema version!';
        break;

      case "500":
        $error_tip = 'There was no response at all, have you defined the base uri? Or maybe it is a timeout';
        break;

      default:
        $error_tip = 'Sorry, no tip for this error code.';
    }

    // Error Code and Message.
    $message = $this->t('API connection error. Error code: %error_code. Error message: %error_message %error_tip', [
      '%error_code' => $error_content['errors'][0]['status'],
      '%error_message' => $error_content['errors'][0]['title'] ?? 'no message',
      '%error_tip' => $error_tip,
    ]);
    $this->messenger->addError($message);
    // Log the error.
    \Drupal::logger('wisski_doi')->error($message);
    return $error_content['errors'][0]['status'];
  }

}
