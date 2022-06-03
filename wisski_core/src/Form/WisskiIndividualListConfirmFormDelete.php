<?php

namespace Drupal\wisski_core\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\wisski_doi\WisskiDoiDbActions;
use Drupal\wisski_doi\WisskiDoiDataciteRestActions;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Pager\PagerManager;

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
   * 
   *
   */
  public function __construct(PagerManager $pagerManager) {
    $this->pagerManager = $pagerManager;
  }

    /**
   * Get the services from the container.
   */
  public static function create(ContainerInterface $container) {
    $pagerManager = $container->get('pager.manager');
    return new static($pagerManager);
  }

  private function countIndividuals() {
    return $this->numberOfIndividuals;
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

    // since in $individualList only the id is present, we have to do an array_intersect_key 
    // with the full list containing all entities to get the eid, label and link of
    // the selected entities
    // then we can show we table with all this information again to let the users
    // double-check if they really wants to delete these entities
    $allRecords = \Drupal::configFactory()
      ->getEditable(WisskiIndListForm::SELECTED_INDIVIDUALS)->get('allRecords');
    $allRecordsNew = [];
    foreach($allRecords as $record){
      $allRecordsNew[$record['eid']] = [
        'eid' => $record['eid'], 
        'label' => [
          'data' => $this->t('<a href=":entityLink" class="wisski-entity-link">:entityTitle</a>', 
            [':entityLink' => $record['entityLink'], ':entityTitle' => $record['entityTitle']]
          )
        ]]; 
    }

    $this->deleteIndividualsWithLabel = array_intersect_key($allRecordsNew, $individualListAsMap);
    $this->numberOfIndividuals = count($individualListAsMap);

    $chunk = $this->pagerArray($this->deleteIndividualsWithLabel, 25);
    if(empty($chunk)){
      $chunk = [];
    }

    $form['table'] = [
      '#type' => 'table',
      '#header' => [
        'eid' => $this->t('EID'),
        'label' => $this->t('Title'),
      ],
      '#rows' => $chunk,
      '#empty' => $this
        ->t('No entities found.'),
    ];

    return parent::buildForm($form, $form_state);
  }

    /**
   * Returns pager array.
   *
   * @param array $items
   *   All records to render.
   * @param int $itemsPerPage
   *   The page limits.
   *
   * @return array
   *   The chunk to render.
   */
  public function pagerArray(array $items, int $itemsPerPage) {
    // Get total items count.
    $total = count($items);
    // Get the number of the current page.
    $currentPage = $this->pagerManager->createPager($total, $itemsPerPage)
      ->getCurrentPage();
    // Split an array into chunks.
    $chunk = array_chunk($items, $itemsPerPage, TRUE);
    // Return current group item.

    if(count($items) == 0){
      return [];
    }
    return $chunk[$currentPage];
  }

  /**
   * Deletes DOI record from local and remote DB.
   */
  public function submitForm(array &$form, $form_state) {
    \Drupal::service('wisski.wisski_core.database_actions')->deleteBundleRecords('wisskiIndividuals');
    // Redirect.
    $form_state->setRedirect(
      'entity.wisski_bundle.individual_list', ['wisski_bundle' => $this->wisskiBundleId]
    );
  }
}