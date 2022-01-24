<?php

namespace Drupal\wisski_doi\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Pager\PagerManager;
use Drupal\wisski_doi\WisskiDoiDbActions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller to render DOI batch table.
 */
class WisskiDoiBatchForm extends FormBase {

  /**
   * The service to interact with the database.
   *
   * @var \Drupal\wisski_doi\WisskiDoiDbActions
   */
  private WisskiDoiDbActions $wisskiDOiDbActions;

  /**
   * The service to interact with the database.
   *
   * @var \Drupal\Core\Pager\PagerManager
   */
  private PagerManager $pagerManager;

  /**
   * Construct the WisskiDoiAdministration class.
   */
  public function __construct(WisskiDoiDbActions $wisskiDOiDbActions, PagerManager $pagerManager) {
    $this->wisskiDOiDbActions = $wisskiDOiDbActions;
    $this->pagerManager = $pagerManager;
  }

  /**
   * Get the services from the container.
   */
  public static function create(ContainerInterface $container) {
    $wisskiDOiDbActions = $container->get('wisski_doi.wisski_doi_db_actions');
    $pagerManager = $container->get('pager.manager');
    return new static($wisskiDOiDbActions, $pagerManager);
  }

  /**
   * The machine name of the form.
   */
  public function getFormId() {
    return 'wisski_doi_batch_form';
  }

  /**
   * The machine name of the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $wisski_bundle = NULL) {
    $records = $this->wisskiDOiDbActions->readBundleRecords($wisski_bundle);
    $chunks = $this->pagerArray($records, 10);
    dpm($records);
    // Build form.
    $form['table'] = [
      '#type' => 'tableselect',
      '#header' => [
        'label' => $this->t('Label'),
        'link' => $this->t('Link'),
      ],
      '#options' => $chunks,
      '#empty' => $this
        ->t('No entities found.'),
    ];
    $form['pager'] = [
      '#type' => 'pager',
    ];
    return $form;
  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo Implement submitForm() method.
  }

  /**
   * Returns pager array.
   */
  public function pagerArray($items, $itemsPerPage) {
    // Get total items count.
    $total = count($items);
    // Get the number of the current page.
    $currentPage = $this->pagerManager->createPager($total, $itemsPerPage)->getCurrentPage();
    // Split an array into chunks.
    $chunks = array_chunk($items, $itemsPerPage);
    // Return current group item.
    return $chunks[$currentPage];
  }
}
