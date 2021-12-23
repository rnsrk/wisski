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
class WisskiDoiConfirmFormUpdateMetadata extends WisskiDoiConfirmFormRequestDoiForStaticRevision {

  private array $dbRecord;

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
   *
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $continue = TRUE;
    if (!($this->dbRecord['type'] == 'draft') && $form_state->getValue('type') == 'draft') {
      $form_state->setErrorByName('entityId', $this->t('You can not change a registered or findable DOI back to draft, sorry. Pick a suitable type'));
      $continue = FALSE;
    }
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
    $this->dbRecord = (new WisskiDoiDbController())->readDoiRecords($wisski_individual, $did)[0];
    if ($this->dbRecord['type'] == 'findable') {
      $doiInfo = (new WisskiDoiRestController())->readMetadata($this->dbRecord['doi']);
      $form_state->set('doiInfo', [
        "entityId" => $wisski_individual,
        "creationDate" => $doiInfo['data']['attributes']['dates'][0]['dateInformation'],
        "type" => 'publish',
        "author" => $doiInfo['data']['attributes']['creators'][0]['name'],
        "contributors" => $doiInfo['data']['attributes']['contributors'],
        "title" => $doiInfo['data']['attributes']['titles'][0]['title'],
        "publisher" => $doiInfo['data']['attributes']['publisher'],
        "language" => $doiInfo['data']['attributes']['language'],
        "resourceType" => $doiInfo['data']['attributes']['types']['resourceTypeGeneral'],
      ]);
    }

    return parent::buildForm($form, $form_state, $wisski_individual);
  }

  /**
   *
   * @throws \Exception
   */
  public function submitForm(array &$form, $form_state) {

    // Get new values from form state.
    $doiInfo = $form_state->cleanValues()->getValues();

    // Get DOI.
    $doiInfo += [
      "doi" => $this->dbRecord['doi'],
    ];

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
    $response = (new WisskiDoiRestController())->createOrUpdateDoi($doiInfo, TRUE);
    $response['responseStatus'] == 200 ? (new WisskiDoiDBController())->updateDbRecord($doiInfo['type'], intval($this->dbRecord['did'])) : \Drupal::logger('wisski_doi')
      ->error($this->t('Something went wrong Updating the DOI. Leave the database untouched'));
    // Redirect to DOI administration.
    $form_state->setRedirect(
      'wisski_individual.doi.administration', ['wisski_individual' => $this->wisski_individual->id()]
    );
  }

}
