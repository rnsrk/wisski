<?php

namespace Drupal\wisski_doi\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\wisski_doi\Controller\WisskiDoiDbActions;
use Drupal\wisski_doi\Controller\WisskiDoiRestActions;

/**
 * Provides a form for reverting a wisski_individual revision.
 *
 * @internal
 */
class WisskiDoiConfirmFormDeleteDoi extends ConfirmFormBase {

  /**
   * The WissKI individual.
   *
   * @var int
   */
  private mixed $wisski_individual;

  /**
   * The internal DOI ID.
   *
   * @var int
   */
  private int $did;

  /**
   * The DOI.
   *
   * @var string
   */
  private string $doi;

  /**
   * The machine name of the form.
   */
  public function getFormId() {
    return 'wisski_doi_delete_doi';
  }

  /**
   * The question of the confirm form.
   */
  public function getQuestion(): TranslatableMarkup {
    // Return $this->t('Do you want to delete DOI');.
    return $this->t('Do you want to delete DOI "%doi"?', ['%doi' => $this->doi]);
  }

  /**
   * Text on the submit button.
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Delete DOI');
  }

  /**
   * Details between title and body.
   */
  public function getDescription() {
    return $this->t('This deletes the DOI assigned to the WissKI individual from
    the provider database as well as from the local database.');
  }

  /**
   * The URL redirection in case of click cancel.
   */
  public function getCancelUrl() {
    return new Url('wisski_individual.doi.administration', ['wisski_individual' => $this->wisski_individual]);
  }

  /**
   * Delete the DOI from provider's and local DB.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param int|null $wisski_individual
   *   The entity id of the wisski individual.
   * @param int|null $did
   *   The internal DOI id in wisski_doi table.
   *
   * @throws \Exception
   *   Error if WissKI entity URI could not be loaded (?).
   */
  public function buildForm(array $form, FormStateInterface $form_state, $wisski_individual = NULL, $did = NULL): array {
    $this->wisski_individual = $wisski_individual;
    $this->did = $did;

    // Read the DOI record.
    $dbRecord = (new WisskiDoiDbActions())->readDoiRecords($wisski_individual, $did)[0];
    $this->doi = $dbRecord['doi'];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Deletes DOI record from local and remote DB.
   */
  public function submitForm(array &$form, $form_state) {
    // Invoke delete request.
    $response = (new WisskiDoiRestActions())->deleteDoi($this->doi);

    if ($response == 204) {
      // If it was successfully, delete local database record.
      (new WisskiDoiDbActions())->deleteDoiRecord($this->did);
    }
    else {
      // If not, log the error.
      $this->messenger()
        ->addError($this->t('Something went wrong while deleting the DOI from provider database.
        Leave the database record untouched.'));
    }
    // Redirect.
    $form_state->setRedirect(
      'wisski_individual.doi.administration', ['wisski_individual' => $this->wisski_individual]
    );
  }

}
