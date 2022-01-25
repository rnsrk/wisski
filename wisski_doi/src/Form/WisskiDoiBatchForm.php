<?php

namespace Drupal\wisski_doi\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Pager\PagerManager;
use Drupal\wisski_doi\WisskiDoiDbActions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller to render DOI batch table.
 */
class WisskiDoiBatchForm extends ConfigFormBase {

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
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [
      'wisski_doi_batch_form_api.settings',
    ];
  }

  /**
   * The machine name of the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $wisski_bundle = NULL) {
    $records = $this->wisskiDOiDbActions->readBundleRecords($wisski_bundle);
    $chunk = $this->pagerArray($records, 25);
    foreach ([0, 1] as $isCurrent) {
      $this->doiAnnotation($chunk, $isCurrent);
    }
    // Build form.
    $form['table'] = [
      '#type' => 'tableselect',
      '#header' => [
        'eid' => $this->t('EID'),
        'label' => $this->t('Label'),
        'link' => $this->t('Link'),
        'currentDoi' => $this->t('Current DOI'),
        'latestStaticDoi' => $this->t('Latest Static DOI'),
      ],
      '#options' => $chunk,
      '#empty' => $this
        ->t('No entities found.'),
    ];
    $form['pager'] = [
      '#type' => 'pager',
      '#attributes' => ['class' => 'wisski-doi-pager'],
    ];

    $form['actions']['submitToGetDois4Current'] = [
      '#type' => 'submit',
      '#value' => $this->t('Get DOIs for current revision'),
      "#weight" => 1,
      '#submit' => [[$this, 'submitFormToGetDois4Current']],
      '#limit_validation_errors' => [],
    ];
    $form['actions']['submitToGetDois4Static'] = [
      '#type' => 'submit',
      '#value' => $this->t('Get DOIs for static revision'),
      "#weight" => 1,
      '#submit' => [[$this, 'submitFormToGetDois4Static']],
      '#limit_validation_errors' => [],
    ];
    return $form;
  }

  /**
   *
   */
  public function submitFormToGetDois4Current(array &$form, FormStateInterface $form_state) {
    // @todo Implement submitForm() method.
    parent::submitForm($form, $form_state);
  }

  /**
   *
   */
  public function submitFormToGetDois4Static(array &$form, FormStateInterface $form_state) {
    // @todo Implement submitForm() method.
    parent::submitForm($form, $form_state);
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
    return $chunk[$currentPage];
  }

  /**
   * Annotate the chunk with DOI data.
   *
   * @param array $chunk
   *   The chunk REFERENCE to render.
   * @param int $isCurrent
   *   Flag, if we are looking for DOIs for static (0) or current (1) revision.
   */
  public function doiAnnotation(array &$chunk, int $isCurrent) {
    foreach ($chunk as $record) {
      $cssClass = $isCurrent ? 'current' : 'latest-static';
      $key = $isCurrent ? 'currentDoi' : 'latestStaticDoi';
      $doiRecords = $this->wisskiDOiDbActions->readLatestDoiRecords($record['eid'], $isCurrent);
      if ($doiRecords) {
        $doiLink = 'https://doi.org/' . $doiRecords['doi'];
        dpm(date('d.M.Y h:i:s', strtotime($doiRecords['created'])));
        $chunk[$record['eid']][$key] = [
          'data' => $this->t('<span><a href=":doiLink" class="wisski-:currentFlag-doi-link">:doiLink</a> (:state) from %created</span>', [
            ':doiLink' => $doiLink,
            ':currentFlag' => $cssClass,
            ':state' => $doiRecords['state'],
            '%created' => date('d.M.Y h:i:s', strtotime($doiRecords['created'])),
          ]),
        ];
      }
      else {
        $chunk[$record['eid']][$key] = 'No DOI assigned';
      }
    }
  }

}
