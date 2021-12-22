<?php

namespace Drupal\wisski_doi\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\wisski_doi\Controller\WisskiDoiDbController;
use Drupal\wisski_doi\Controller\WisskiDoiRestController;
use Drupal\wisski_salz\AdapterHelper;

/**
 * Provides a form for reverting a wisski_individual revision.
 *
 * @internal
 */
class WisskiDoiUpdateMetadata extends WisskiConfirmFormDoiRequestForStaticRevision {

  /**
   * The machine name of the form.
   */
  public function getFormId() {
    return 'wisski_doi_edit_form_for_doi_metadata';
  }

  /**
   * The question of the confirm form.
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Make your changes!');
  }

  /**
   * Text on the submit button.
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Update DOI metadata');
  }

  /**
   * Details between title and body.
   */
  public function getDescription() {
    return $this->t('This updates the DOI metadata at your provider.
    It will NOT changes the dataset in WissKI.');
  }

  /**
   * Save to revisions and request a DOI for one.
   *
   * First save a DOI revision to receive a revision id,
   * request a DOI for that revision,then save a second
   * time to store the revision and DOI in Drupal DB.
   *
   * @throws \Exception
   *   Error if WissKI entity URI could not be loaded (?).
   */
  public function buildForm(array $form, FormStateInterface $form_state, $wisski_individual = NULL, $did = NULL): array {
    $dbRecord = (new WisskiDoiDbController())->readDoiRecords($wisski_individual, $did)[0];
    $doiInfo = (new WisskiDoiRestController())->readMetadata($dbRecord['doi']);
    dpm($doiInfo);
    $form_state->set('doiInfo', [
      "entityId" => $wisski_individual,
      "creationDate" => $doiInfo['data']['attributes']['dates'][0]['dateInformation'],
      "author" => $doiInfo['data']['attributes']['creators'][0]['name'],
      "contributors" => $doiInfo['data']['attributes']['contributors'],
      "title" => $doiInfo['data']['attributes']['titles'][0]['title'],
      "publisher" => $doiInfo['data']['attributes']['publisher'],
      "language" => $doiInfo['data']['attributes']['language'],
      "resourceType" => $doiInfo['data']['attributes']['types']['resourceTypeGeneral'],
    ]);

    return parent::buildForm($form, $form_state, $wisski_individual);
  }

  /**
   *
   */
  public function submitForm(array &$form, $form_state) {

    // Get new values from form state.
    $doiInfo = $form_state->cleanValues()->getValues();

    // Get WissKI entity URI.
    $target_uri = AdapterHelper::getOnlyOneUriPerAdapterForDrupalId($this->wisski_individual->id());
    $target_uri = current($target_uri);
    $doiInfo += [
      "entityUri" => $target_uri,
    ];

    /*
     * No need to save a revision, because the revisionUrl points to the
     * resolver with the entity URI and not to a "real" revision URL, like
     * http://{domain}/wisski/navigate/{entity_id}/revisions/{revision_id}/view
     */

    // Assemble revision URL and store it in form.
    $http = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
    $doiCurrentRevisionURL = $http . $_SERVER['HTTP_HOST'] . '/wisski/get?uri=' . $doiInfo["entityUri"];

    // Append revision info to doiInfo.
    $doiInfo += [
      "revisionUrl" => $doiCurrentRevisionURL,
    ];
    // Request DOI.
    // (new WisskiDoiRestController())->getDoi($doiInfo);
    // Redirect to DOI administration.
    $form_state->setRedirect(
      'wisski_individual.doi.administration', ['wisski_individual' => $this->wisski_individual->id()]
    );
  }

}
