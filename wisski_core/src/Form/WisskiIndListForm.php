<?php

namespace Drupal\wisski_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Pager\PagerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\wisski_core\WisskiIndividualListDbActions;

/**
 * Controller to render WissKI Individual List table.
 */
class WisskiIndListForm extends ConfigFormBase {

  
/**
 * Batch metadata config name.???
 *
 * @var string
 */
const SELECTED_INDIVIDUALS = 'wisski_core.individual_list';

/**
 * Batch metadata config name.???
 *
 * @var string
 */
const ALL_RECORDS = 'wisski_core.all_records';

  protected WisskiIndividualListDbActions $wisskiIndividualListDbActions;

  /**
   * The service to interact with the database.
   *
   * @var \Drupal\Core\Pager\PagerManager
   */
  private PagerManager $pagerManager;

  /**
   * The WissKI bundle id.
   *
   * @var string
   */
  private string $wisskiBundleId;

  /**
   * Construct the WisskiIndividualListForm class.
   */
  public function __construct(WisskiIndividualListDbActions $wisskiIndividualListActions, PagerManager $pagerManager) {
    $this->wisskiIndividualListDbActions = $wisskiIndividualListActions;
    $this->pagerManager = $pagerManager;
  }

  /**
   * Get the services from the container.
   */
  public static function create(ContainerInterface $container) {
    $wisskiIndividualListActions = $container->get('wisski.wisski_core.database_actions');
    $pagerManager = $container->get('pager.manager');
    return new static($wisskiIndividualListActions, $pagerManager);
  }

  /**
   * The machine name of the form.
   */
  public function getFormId() {
    return 'wisski_individual_list_form';
  }

  private function batchDeleteIndividuals() {
      //TODO
  }

    /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [
      static::SELECTED_INDIVIDUALS,
    ];
  }

  /**
   * TODO
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $wisski_bundle = NULL) {
    $this->wisskiBundleId = $wisski_bundle;
    $this->config(static::SELECTED_INDIVIDUALS);
    $records = \Drupal::service('wisski.wisski_core.database_actions')->readBundleRecords($wisski_bundle);
    $this->configFactory->getEditable(static::SELECTED_INDIVIDUALS)
    // Set the submitted configuration setting.
    ->set('allRecords', $records)
    ->save();

    $chunk = $this->pagerArray($records, 25);
    if(empty($chunk)){
      $chunk = [];
    }
    // Build form.
    $form['table'] = [
      '#type' => 'tableselect',
      '#header' => [
        'eid' => $this->t('EID'),
        // we prefer the term "Title" for display but however title is a reserved
        // variable, why we have to name it "label" in 
        // WisskiIndividualListDbActions.php where the information is built up
        'label' => $this->t('Title'),
      ],
      '#options' => $chunk,
      '#empty' => $this
        ->t('No entities found.'),
    ];

    $form['pager'] = [
      '#type' => 'pager',
      '#attributes' => ['class' => 'wisski-individual-list-pager'],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete'),
    ];
    /*
    $form['actions']['submitFormToDeleteIndividualList'] = [
      '#type' => 'submit',
      '#value' => $submitFormToDeleteIndividualList,
      '#button_type' => 'primary',
      '#submit' => [[$this, 'submitFormToGetDois4Current']],
    ];
    */
    return $form;
    #return parent::buildForm($form, $form_state);
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

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SELECTED_INDIVIDUALS)
    // Set the submitted configuration setting.
    ->set('wisskiIndividuals', $form_state->getValue('table'))
    ->save();

  $form_state->setRedirect(
    'entity.wisski_bundle.individual_list.delete', ['wisski_bundle' => $this->wisskiBundleId]
  );
  }

}
