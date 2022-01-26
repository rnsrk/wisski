<?php

namespace Drupal\wisski_doi\Form;

use Drupal\wisski_core\WisskiStorageInterface;
use Drupal\wisski_core\WisskiEntityInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\wisski_doi\WisskiDoiActions;
use Drupal\wisski_doi\WisskiDoiDbActions;
use Drupal\wisski_doi\WisskiDoiRestActions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reverting a wisski_individual revision.
 *
 * @internal
 */
class WisskiDoiBatch4StaticRevisionsConfirmForm extends ConfirmFormBase {

  /**
   * The WisskiEntity revision.
   *
   * @var \Drupal\wisski_core\WisskiEntityInterface
   */
  protected WisskiEntityInterface $revision;

  /**
   * The WissKI storage.
   *
   * @var \Drupal\wisski_core\WisskiStorageInterface
   */
  protected WisskiStorageInterface $wisskiStorage;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;


  /**
   * The service to management DOI metadata.
   *
   * @var \Drupal\wisski_doi\WisskiDoiActions
   */
  private WisskiDoiActions $wisskiDoiActions;

  /**
   * The service to interact with the REST API .
   *
   * @var \Drupal\wisski_doi\WisskiDoiRestActions
   */
  protected WisskiDoiRestActions $wisskiDoiRestActions;

  /**
   * The service to interact with the database.
   *
   * @var \Drupal\wisski_doi\WisskiDoiDbActions
   */
  protected WisskiDoiDbActions $wisskiDoiDbActions;

  /**
   * The metadata for the batch.
   *
   * @var array
   */
  protected array $batchMetadata;

  /**
   * The WissKI bundle.
   *
   * @var string
   */
  private string $wisskiBundleId;

  /**
   * The selected WissKI individuals.
   *
   * @var array|null
   */
  private ?array $wisskiIndividualIds;

  /**
   * Constructs a new form to request a DOI for a static revision.
   *
   * @param \Drupal\wisski_core\WisskiStorageInterface $wisski_storage
   *   The WissKI Storage service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\wisski_doi\WisskiDoiActions $wisskiDoiActions
   *   The WissKi DOI Service.
   * @param \Drupal\wisski_doi\WisskiDoiRestActions $wisskiDoiRestActions
   *   The WissKi DOI Rest Service.
   * @param \Drupal\wisski_doi\WisskiDoiDbActions $wisskiDoiDbActions
   *   The WissKI DOI database Service.
   */
  public function __construct(WisskiStorageInterface $wisski_storage,
                              DateFormatterInterface $date_formatter,
                              TimeInterface $time,
                              WisskiDoiActions $wisskiDoiActions,
                              WisskiDoiRestActions $wisskiDoiRestActions,
                              WisskiDoiDbActions $wisskiDoiDbActions) {
    $this->wisskiStorage = $wisski_storage;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
    $this->wisskiDoiActions = $wisskiDoiActions;
    $this->wisskiDoiRestActions = $wisskiDoiRestActions;
    $this->wisskiDoiDbActions = $wisskiDoiDbActions;
  }

  /**
   * Populate the reachable variables from services.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The class container.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('wisski_individual'),
      $container->get('date.formatter'),
      $container->get('datetime.time'),
      $container->get('wisski_doi.wisski_doi_actions'),
      $container->get('wisski_doi.wisski_doi_rest_actions'),
      $container->get('wisski_doi.wisski_doi_db_actions'),
    );
  }

  /**
   * The machine name of the form.
   *
   * @return string
   *   The form id.
   */
  public function getFormId() {
    return 'wisski_doi_batch_form_for_static_revisions';
  }

  /**
   * Storage of the contributor names.
   *
   * @return array
   *   The list of storage items.
   */
  protected function getEditableConfigNames() {
    return [
      'contributor.items',
    ];
  }

  /**
   * The question of the confirm form.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The confirmation questions.
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to request DOIs for static revisions of the selected WissKI individuals?');
  }

  /**
   * Route, if you hit chancel.
   *
   * @return \Drupal\Core\Url
   *   The Chancel URL.
   */
  public function getCancelUrl() {
    return new Url('entity.wisski_bundle.doi_batch', ['wisski_bundle' => $this->wisskiBundleId]);
  }

  /**
   * Text on the submit button.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The submit button text.
   */
  public function getConfirmText() {
    return $this->t('Request DOIs');
  }

  /**
   * Details between title and body.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The description texts.
   */
  public function getDescription() {
    return $this->t('This saves a revision and assigns a DOI to each of the selected
    WissKI individuals.
    The DOI points only to this revision, you can not change the data of the
    dataset afterwards (only the metadata of the DOI). If you like to assign a DOI which points
    always to the current state of the dataset, please use "Get DOIs for current revisions". <br>
    <b>Following data will be received from the data records:</b> <br>
    <ul>
    <li>author</li>
    <li>title</li>
    <li>revision creation date</li>
    <li>language</li>
    </ul>');
  }

  /**
   * Build table from DOI settings and WissKI individual state.
   *
   * Load DOI settings from Manage->Configuration->WissKI:WissKI DOI Settings.
   * Store it in a table.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param int $wisski_individual
   *   The WissKI Entity ID.
   *
   * @return array
   *   The form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $wisskiBundleId = NULL) {

    // Assign WissKI bundle to class property.
    $this->wisskiBundleId = $wisskiBundleId;

    /* #tree will ensure the HTML elements get named distinctively.
     * Not just name=[name] but name=[container][123][name].
     */
    $form['#tree'] = TRUE;

    /*
     * Load existing form data.
     */

    // General form.
    $form = parent::buildForm($form, $form_state);

    // DOI Settings from config page.
    $doiSettings = \Drupal::configFactory()
      ->getEditable('wisski_doi.wisski_doi_settings');

    // Contributors from form.
    $contributorItems = $this->config('contributor.items');

    // Selected WissKI individuals.
    $this->wisskiIndividualIds = \Drupal::configFactory()
      ->getEditable('wisski_doi_batch_form.storage')->get('wisskiIndividuals');

    // Remove keys with empty values.
    $this->wisskiIndividualIds = array_filter($this->wisskiIndividualIds);
    // Batch metadata.
    $this->batchMetadata = [
      "event" => 'draft',
      "contributors" => $contributorItems->get('contributors'),
      "publisher" => $doiSettings->get('data_publisher'),
      "resourceType" => 'Dataset',
    ];

    // Resource type option from DataCite schema.
    $resourceTypeOptions = [
      'Audiovisual' => 'Audiovisual',
      'Collection' => 'Collection',
      'DataPaper' => 'DataPaper',
      'Dataset' => 'Dataset',
      'Event' => 'Event',
      'Image' => 'Image',
      'InteractiveResource' => 'InteractiveResource',
      'Model' => 'Model',
      'PhysicalObject' => 'PhysicalObject',
      'Service' => 'Service',
      'Software' => 'Software',
      'Sound' => 'Sound',
      'Text' => 'Text',
      'Workflow' => 'Workflow',
      'Other' => 'Other',
    ];

    /*
     * publish - Triggers a state move from draft or registered to findable.
     * register - Triggers a state move from draft to registered (register)
     * or from findable to registered (hide).
     * draft - Triggers a state move from findable to registered.
     */
    $doiEvents = [
      'draft' => 'draft',
      'register' => 'register',
      'hide' => 'hide',
      'publish' => 'publish',
    ];

    $form['count'] = [
      '#type' => 'item',
      '#value' => count($this->wisskiIndividualIds),
      '#markup' => count($this->wisskiIndividualIds),
      '#title' => $this->t('Count of selected WissKI individuals'),
    ];

    $form['event'] = [
      '#type' => 'select',
      '#title' => $this->t('Event'),
      '#options' => $doiEvents,
      '#default_value' => $this->batchMetadata['event'],
      '#description' => $this->t('The event for the DOI. If you register or publish the DOI, it can not be deleted!'),
    ];

    $form['contributors'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contributors'),

    ];
    $form['contributors']['contributorGroup'] = [
      '#type' => 'fieldgroup',
      '#attributes' => ['class' => ['wisski-doi-contributorGroup']],
    ];

    $form['contributors']['contributorGroup']['contributor'] = [
      '#type' => 'textfield',
      '#description' => $this->t('Additional Contributors like previous editors of the dataset.'),
    ];
    $form['contributors']['contributorGroup']['submit'] = [
      '#type' => 'button',
      '#ajax' => [
        'callback' => [
          '\Drupal\wisski_doi\Form\WisskiDoiConfirmFormRequestDoiForStaticRevision',
          'addContributor',
        ],
        'wrapper' => 'contributor-list',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Adding contributor...'),
        ],
      ],
      '#value' => $this->t('Add'),
    ];

    $form['contributors']['contributorTable'] = [
      '#type' => 'item',
      '#markup' => WisskiDoiConfirmFormRequestDoiForStaticRevision::renderContributors($contributorItems->get('contributors')),
    ];

    $form['publisher'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publisher'),
      '#default_value' => $this->batchMetadata['publisher'],
      '#description' => $this->t('The publisher of the database.'),
    ];

    $form['resourceType'] = [
      '#type' => 'select',
      '#title' => $this->t('Type of record'),
      '#options' => $resourceTypeOptions,
      '#default_value' => 'Dataset',
      '#description' => $this->t('The type of data in DOI terms, usually "Dataset".'),
    ];
    $form['#attached']['library'][] = 'wisski_doi/wisskiDoi';
    return $form;
  }

  /**
   * Save to revisions and request a DOI for one.
   *
   * First save a DOI revision to receive a revision id,
   * request a DOI for that revision,then save a second
   * time to store the revision and DOI in Drupal DB.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get new values from form state.
    $newValues = $form_state->cleanValues()->getValues();
    $doiMetaData = $newValues;

    // Get AJAX info.
    $contributorItems = \Drupal::configFactory()
      ->getEditable('contributor.items');

    // Have to overwrite contributors cause AJAX mess up the form_state.
    $doiMetaData['contributors'] = $contributorItems->get('contributors');

    $batchStateStaticRevisions = \Drupal::state()->get('wisski_doi_batch_array_for_static_revisions');

    $wisskiIndividualsBatch = $batchStateStaticRevisions ?: $this->wisskiIndividualIds;
    //\Drupal::state()->set('wisski_doi_batch_array_for_static_revisions', $this->wisskiIndividualIds);
    // Iterate over selected WissKI individuals.
    if ($wisskiIndividualsBatch) {
      foreach ($wisskiIndividualsBatch as $wisskiIndividualId) {
        unset($wisskiIndividualsBatch[$wisskiIndividualId]);
        dpm($wisskiIndividualsBatch);
        $wisskiIndividual = $this->wisskiStorage->load($wisskiIndividualId);
        //$this->getStaticDoi($wisskiIndividual, $doiMetaData);
        \Drupal::state()->set('wisski_doi_batch_array_for_static_revisions',$wisskiIndividualsBatch);
      }
      \Drupal::state()->delete('wisski_doi_batch_array_for_static_revisions');
    }


    // Redirect to batch overview.
    $form_state->setRedirect(
      'entity.wisski_bundle.doi_batch', ['wisski_bundle' => $this->wisskiBundleId]
     );
  }

  /**
   *
   */
  public function getStaticDoi(WisskiEntityInterface $wisskiIndividual, array $doiMetadata) {

    // Load metadata of WissKI individual.
    $wisskiIndividualMetaData = $this->wisskiDoiActions->getWisskiIndividualMetadata($wisskiIndividual);

    // Assemble DOI metadata with WissKI individual metadata.
    $doiMetadata += $wisskiIndividualMetaData;
    /*
     * Save two revisions, because current revision has no
     * revision URI. Start with first save process.
     */
    $doiRevision = $this->wisskiStorage->createRevision($wisskiIndividual);
    $doiRevision->setNewRevision(TRUE);
    $doiRevision->revision_log = $this->t('DOI revision requested at %request_date.', [
      '%request_date' => $this->dateFormatter->format($this->time->getCurrentTime(), 'custom', 'd.m.Y H:i:s'),
    ]);
    $doiRevision->save();

    // Assemble revision URL and store it in form.
    $http = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
    $doiRevisionId = $doiRevision->getRevisionId();
    $doiRevisionURL = $http . $_SERVER['HTTP_HOST'] . '/wisski/navigate/' . $wisskiIndividual->id() . '/revisions/' . $doiRevisionId . '/view';

    // Append revision info to doiInfo.
    $doiMetadata += [
      "revisionId" => $doiRevisionId,
      "revisionUrl" => $doiRevisionURL,
    ];
    // Request DOI.
    $response = $this->wisskiDoiRestActions->createOrUpdateDoi($doiMetadata);
    // Safe to db if successfully.
    $response['responseStatus'] == 201 ? $this->wisskiDoiDbActions->writeToDb($response['dbData']) : \Drupal::logger('wisski_doi')
      ->error($this->t('Something went wrong creating the DOI. Leave the database untouched'));

    // Start second save process. This is the current revision now.
    $doiRevision = $this->wisskiStorage->createRevision($wisskiIndividual);
    $doiRevision->revision_log = $this->t('Revision copy, because of DOI request from %request_date.', [
      '%request_date' => $this->dateFormatter->format($this->time->getCurrentTime(), 'custom', 'd.m.Y H:i:s'),
    ],
      );
    $doiRevision->save();
  }

}
