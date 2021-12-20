<?php

namespace Drupal\wisski_doi\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\Entity\User;
use Drupal\wisski_core\WisskiStorageInterface;
use Drupal\wisski_core\WisskiEntityInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\wisski_doi\Controller\WisskiDoiRestController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a form for reverting a wisski_individual revision.
 *
 * @internal
 */
class WisskiRequestDoiConfirmForm extends ConfirmFormBase {

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
   * The WissKI Individual.
   *
   * @var \Drupal\wisski_core\WisskiEntityInterface
   */
  private WisskiEntityInterface $wisskiIndividual;

  /**
   * All information for the DOI request and write process to wisski_doi table.
   *
   * @var array
   */
  private array $doiInfo;

  /**
   * The logging text of the revision.
   *
   * @var string
   */
  protected string $revision_log;

  /**
   * Constructs a new NodeRevisionRevertForm.
   *
   * @param \Drupal\wisski_core\WisskiStorageInterface $wisski_storage
   *   The WissKI Storage.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(WisskiStorageInterface $wisski_storage, DateFormatterInterface $date_formatter, TimeInterface $time) {
    $this->wisskiStorage = $wisski_storage;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('wisski_individual'),
      $container->get('date.formatter'),
      $container->get('datetime.time')
    );
  }

  /**
   * The machine name of the form.
   */
  public function getFormId(): string {
    return 'wisski_doi_request_form';
  }

  /**
   *
   */
  protected function getEditableConfigNames() {
    return [
      'contributor.items',
    ];
  }

  /**
   * The question of the confirm form.
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to request a findable DOI for this revision?');
  }

  /**
   * Route, if you hit chancel.
   */
  public function getCancelUrl(): Url {
    return new Url('entity.wisski_individual.canonical', ['wisski_individual' => $this->wisskiIndividual->id()]);
  }

  /**
   * Text on the submit button.
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Request DOI for this revision');
  }

  /**
   * Details between title and body.
   */
  public function getDescription() {
  }

  /**
   *
   */
  public static function addContributor(array &$form, FormStateInterface $form_state) : AjaxResponse {

    $contributor = $form_state->getValue('contributor');
    $contributorItems = \Drupal::configFactory()->getEditable('contributor.items');
    $contributors = $contributorItems->get('contributors');
    $error = NULL;

    try {
      /* Validate for duplicates.
       */

      if (is_null($contributors)) {
        $contributors = [];
      }
      if (!in_array($contributor, $contributors)) {
        $contributors[] = $contributor;
      }
      else {
        $error = t('Contributor %contributor already exists in this list', ['%contributor' => $contributor]);
      }
    }

    catch (\Exception $e) {
      $error = t('Wrong text format. Enter a valid text format.');
    }

    $contributorItems->set('contributors', $contributors)->save();
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#contributor-list', WisskiRequestDoiConfirmForm::renderContributors($contributors, $error)));

    return $response;
  }

  /**
   * Delete the specified contributor.
   */
  public static function removeContributor(string $contributor, Request $request) : AjaxResponse {
    $contributorItems = \Drupal::configFactory()->getEditable('contributor.items');
    $contributors = $contributorItems->get('contributors');

    if (!is_null($contributors) && ($ind = array_search($contributor, $contributors)) !== FALSE) {
      unset($contributors[$ind]);
      $contributorItems->set('contributors', $contributors)->save();
    }

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#contributor-list', WisskiRequestDoiConfirmForm::renderContributors($contributors)));

    return $response;
  }

  /**
   * Delete all dates.
   */
  public static function clearContributors(Request $request) : AjaxResponse {
    $contributorItems = \Drupal::configFactory()->getEditable('contributor.items');
    $contributorItems->set('contributors', NULL)->save();
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#contributor-list', WisskiRequestDoiConfirmForm::renderContributors(NULL)));
    return $response;
  }

  /**
   *
   */
  protected static function renderContributors($contributors, $error = NULL) {
    $contributors = ['person a'];
    $theme = [
      '#theme' => 'contributor-list',
      '#contributors' => $contributors,
      '#error' => $error,
    ];
    $renderer = \Drupal::service('renderer');

    return $renderer->render($theme);
  }

  /**
   * Build table from DOI settings and WissKI individual state.
   *
   * Load DOI settings from Manage->Configuration->WissKI:WissKI DOI Settings.
   * Store it in a table.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $wisski_individual = NULL): array {
    /* #tree will ensure the HTML elements get named distinctively.
     * Not just name=[name] but name=[container][123][name].
     */
    $form['#tree'] = TRUE;

    $this->wisskiIndividual = $this->wisskiStorage->load($wisski_individual);
    $form = parent::buildForm($form, $form_state);
    $doiSettings = \Drupal::configFactory()
      ->getEditable('wisski_doi.wisski_doi_settings');
    $revisionUser = $this->wisskiIndividual->getRevisionUser();
    if (!empty($revisionUser)) {
      $author = $revisionUser->getDisplayName();
    }
    else {
      $uid = $this->wisskiIndividual->get('uid')->getValue()[0]['target_id'];
      $author = User::load($uid)->getDisplayName();
    }

    $this->doiInfo = [
      "bundleId" => $this->wisskiIndividual->bundle(),
      "entityID" => $this->wisskiIndividual->id(),
      "creationDate" => $this->dateFormatter->format($this->wisskiIndividual->getRevisionCreationTime(), 'custom', 'd.m.Y H:i:s'),
      "author" => $author,
      "title" => $this->wisskiIndividual->label(),
      "publisher" => $doiSettings->get('data_publisher'),
      "language" => $this->wisskiIndividual->language()->getId(),
      "resourceType" => 'Dataset',
    ];

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

    $form['entityID'] = [
      '#type' => 'item',
      '#value' => $this->doiInfo['entityID'],
      '#markup' => $this->doiInfo['entityID'],
      '#title' => $this->t('Entity ID'),
      '#description' => $this->t('The entity ID of the WissKI individual.'),
    ];
    $form['creationDate'] = [
      '#type' => 'item',
      '#value' => $this->doiInfo['creationDate'],
      '#title' => $this->t('Creation date'),
      '#markup' => $this->doiInfo['creationDate'],
      '#description' => $this->t('The datetime, when the revision was created.'),
    ];
    $form['author'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Author'),
      '#default_value' => $this->doiInfo['author'],
      '#description' => $this->t('The author of the selected revision.'),
    ];

    $contributorItems = $this->config('contributor.items');

    $form['contributors'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contributors'),
      '#description' => $this->t('Additional Contributors like previous editors of the dataset.'),
    ];
    $form['contributors']['contributor'] = [
      '#type' => 'textfield',
      '#suffix' => WisskiRequestDoiConfirmForm::renderContributors($contributorItems->get('contributors')),
      '#ajax' => [
        'callback' => 'Drupal\wisski_doi\Form\WisskiRequestDoiConfirmForm::addContributor',
        'wrapper' => 'contributor-list',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Adding contributor...'),
        ],
      ],
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $this->doiInfo['title'],
      '#description' => $this->t('The title, resolved from title pattern.'),
    ];
    $form['publisher'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publisher'),
      '#default_value' => $this->doiInfo['publisher'],
      '#description' => $this->t('The publisher of the database.'),
    ];
    $form['language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Language'),
      '#default_value' => $this->doiInfo['language'],
      '#description' => $this->t('The language of the dataset.'),
    ];
    $form['resourceType'] = [
      '#type' => 'select',
      '#title' => $this->t('Type of record'),
      '#options' => $resourceTypeOptions,
      '#default_value' => 'Dataset',
      '#description' => $this->t('The type of data in DOI terms, usually "Dataset".'),
    ];

    return $form;
  }

  /**
   * Save to revisions and request a DOI for one.
   *
   * First save a DOI revision to receive a revision id,
   * request a DOI for that revision,then save a second
   * time to store the revision and DOI in Drupal DB.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    /*
     * Get new values from form state
     */
    $newVals = $form_state->cleanValues()->getValues();
    $this->doiInfo = $newVals;

    /*
     * Save two revisions, because current revision has no
     * revision URI. Start with first save process.
     */
    $doiRevision = $this->wisskiStorage->createRevision($this->wisskiIndividual);
    $doiRevision->setNewRevision(TRUE);
    $doiRevision->revision_log = $this->t('DOI revision requested at %request_date.', [
      '%request_date' => $this->dateFormatter->format($this->time->getCurrentTime(), 'custom', 'd.m.Y H:i:s'),
    ]);
    $doiRevision->save();

    /*
     * Assemble revision URL and store it in form.
     */
    $http = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
    $doiRevisionId = $doiRevision->getRevisionId();
    $doiRevisionURL = $http . $_SERVER['HTTP_HOST'] . '/wisski/navigate/' . $this->wisskiIndividual->id() . '/revisions/' . $doiRevisionId . '/view';

    /*
     * Append revision info to doiInfo.
     */
    $this->doiInfo += [
      "revisionId" => $doiRevisionId,
      "revisionUrl" => $doiRevisionURL,
    ];

    /*
     * Request draft DOI.
     */
    $wisskiDoiRestController = new WisskiDoiRestController();
    $wisskiDoiRestController->getDraftDoi($this->doiInfo);

    /*
     * Start second save process. This is the current revision now.
     */
    $doiRevision = $this->wisskiStorage->createRevision($this->wisskiIndividual);
    $doiRevision->revision_log = $this->t('Revision copy, because of DOI request from %request_date.', [
      '%request_date' => $this->dateFormatter->format($this->time->getCurrentTime(), 'custom', 'd.m.Y H:i:s'),
    ],
    );
    $doiRevision->save();

    /*
     * Redirect to version history.
     */
    $form_state->setRedirect(
      'entity.wisski_individual.version_history',
      [
        'wisski_individual' => $this->wisskiIndividual->id(),
      ]
    );
  }

}
