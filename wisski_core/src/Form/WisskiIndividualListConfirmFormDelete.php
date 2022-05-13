<?php

namespace Drupal\wisski_core\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\wisski_doi\WisskiDoiDbActions;
use Drupal\wisski_doi\WisskiDoiDataciteRestActions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Provides a form for deleting the individual list.
*
* @internal
*/
class WisskiIndividualListConfirmFormDelete extends ConfirmFormBase {

  private string $wisskiBundleId;
  private int $numberOfIndividuals;
  private array $deleteIndividualsWithLabel;

  /**
   * Form for removing a draft DOI from the provider and the local database.
   *
   * @param \Drupal\wisski_doi\WisskiDoiDataciteRestActions $wisskiDoiRestActions
   *   The WissKi DOI Rest Service.
   * @param \Drupal\wisski_doi\WisskiDoiDbActions $wisskiDoiDbActions
   *   The WissKI DOI database Service.
   */
  public function __construct() {

  }

  private function countIndividuals() {
    return $this->numberOfIndividuals;
  }

  private function listDeleteIndividualsWithLabel() {
    foreach ($this->deleteIndividualsWithLabel as $key => $element) {
      unset($element['link']);
      $this->deleteIndividualsWithLabel[$key] = $element;
    }
    return $this->deleteIndividualsWithLabel;
  }


  /**
   * The machine name of the form.
   */
  public function getFormId() {
    return 'wisski_individual_list_confirm_form_delete';
  }

  /**
   * The question of the confirm form.
   */
  public function getQuestion(): TranslatableMarkup {
    // Return $this->t('Do you want to delete DOI');.
    return $this->t('Do you want to delete all the selected "%count" individuals?', ['%count' => $this->countIndividuals()]);
  }

  /**
   * Text on the submit button.
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Delete individuals');
  }

  /**
   * Details between title and body.
   */
  public function getDescription() {
    return $this->t('This deletes all the selected individuals from the local database which are shown in the table above.');
  }

  /**
   * The URL redirection in case of click cancel.
   */
  public function getCancelUrl() {
    return new Url('entity.wisski_bundle.individual_list', ['wisski_bundle' => $this->wisskiBundleId]);
  }


  /**
   * Delete the selected individuals from the local DB.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param ?string $wisskiBundleId
   *   The bundle id of the wisski individual.
   * 
   * @throws \Exception
   *   Error if WissKI entity URI could not be loaded (?).
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $wisski_bundle = NULL): array {
    $this->wisskiBundleId = $wisski_bundle;
    $individualList = \Drupal::configFactory()
      ->getEditable(WisskiIndListForm::SELECTED_INDIVIDUALS)->get('wisskiIndividuals');
    $individualListAsMap = array_map(function($a){
      return $a;
    },array_filter($individualList));

    $allRecords = \Drupal::configFactory()
      ->getEditable(WisskiIndListForm::ALL_RECORDS)->get('allRecords');
    $this->deleteIndividualsWithLabel = array_intersect_key($allRecords, $individualListAsMap);
    $this->numberOfIndividuals = count($individualListAsMap);

    $form['table'] = [
      '#type' => 'table',
      '#header' => [
        'eid' => $this->t('EID'),
        'label' => $this->t('Label'),
      ],
      '#rows' => $this->listDeleteIndividualsWithLabel(),
      '#empty' => $this
        ->t('No entities found.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Deletes DOI record from local and remote DB.
   */
  public function submitForm(array &$form, $form_state) {
    \Drupal::service('wisski.wisski_core.database_actions')->deleteBundleRecords();
    // Redirect.
    $form_state->setRedirect(
      'entity.wisski_bundle.individual_list', ['wisski_bundle' => $this->wisskiBundleId]
    );
  }
}