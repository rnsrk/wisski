<?php

namespace Drupal\wisski_doi\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBase;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;

/**
 * Class MyController.
 *
 * @package Drupal\mymodule\Controller
 */
class WisskiDOIRESTController extends ControllerBase {



  /**
   * {@inheritdoc}
   */
  public function __construct() {
    $this->httpClient = \Drupal::httpClient();
    $settings = \Drupal::configFactory()->getEditable('wisski_doi.wisski_doi_settings');
    $this->baseUri = $settings->get('doi_base_uri');
    $this->doiRepositoryId = $settings->get('doi_repository_id');
    $this->doiPrefix = $settings->get('doi_prefix');
    $this->doiRepositoryPassword = $settings->get('doi_repository_password');
  }

  /**
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getDraftDOI($body) {
    $body = array(
      "data" => array(
        "attributes" => array(
          "creators" => [
            array(
              "name"=>$body['#rows'][3][1]
            )
          ],
          "titles" => [
            array(
              "title" => $body['#rows'][4][1]
            )
          ],
          "prefix" => $this->doiPrefix,
          "publisher" => $body['#rows'][3][1],
          "publicationYear" => substr($body['#rows'][2][1], 6,4)
        )
      )
    );
    $json_body = json_encode($body);
    //dpm(base64_encode($this->doiRepositoryId.":".$this->doiRepositoryPassword));

    try {
      $response = $this->httpClient->request('POST', $this->baseUri, [
        'body' => $json_body,
        'headers' => [
          'Authorization' => 'Basic ' . base64_encode($this->doiRepositoryId.":".$this->doiRepositoryPassword),
          'Content-Type' => 'application/vnd.api+json',
        ],
      ]);
    }
    catch (\GuzzleHttp\Exception\ClientException $e) {
      $response = $e->getResponse();
      $responseBodyAsString = $response->getBody()->getContents();
    }
    dpm($response->getBody()->getContents());

  }
}
