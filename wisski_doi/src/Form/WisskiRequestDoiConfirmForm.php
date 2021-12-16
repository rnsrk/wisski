<?php

namespace Drupal\wisski_doi\Form;

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
   * The question of the confirm form.
   */
  public function getQuestion(): TranslatableMarkup {
    return t('Are you sure you want to request a draft DOI for the revision?');
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
    return t('Request Draft DOI');
  }

  /**
   * Details between title and body.
   */
  public function getDescription() {
  }

  /**
   * Build table from DOI settings and WissKI individual state.
   *
   * Load DOI settings from Manage->Configuration->WissKI:WissKI DOI Settings.
   * Store it in a table.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $wisski_individual = NULL): array {

    $this->wisskiIndividual = $this->wisskiStorage->load($wisski_individual);
    $form = parent::buildForm($form, $form_state);
    $doiSettings = \Drupal::configFactory()
      ->getEditable('wisski_doi.wisski_doi_settings');
      $revisionUser = $this->wisskiIndividual->getRevisionUser();
    if (!empty($revisionUser)) {
      $author = $revisionUser->getDisplayName();
    } else {
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
      "resourceType" => $this->t('Dataset'),
    ];

    $form['table'] = [
      '#type' => 'table',
      '#header' => [$this->t('Property'), $this->t('Value')],
      '#rows' => [
        [$this->t('BundleID'), $this->doiInfo['bundleId']],
        [$this->t('EntityID'), $this->doiInfo['entityID']],
        [
          $this->t('Creation Date'),
          $this->doiInfo['creationDate'],
        ],
        [
          $this->t('Author'),
          $this->doiInfo['author'],
        ],
        [$this->t('Title'), $this->doiInfo['title']],
        [$this->t('Publisher'), $this->doiInfo['publisher']],
        [$this->t('Language'), $this->doiInfo['language']],
        [$this->t('Resource type general'), $this->doiInfo['resourceType']],
      ],

      '#description' => $this->t('Revision Data'),
      '#weight' => 1,
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
     * Save two revisions, because current revision has no
     * revision URI. Start with first save process.
     */
    $doiRevision = $this->wisskiStorage->createRevision($this->wisskiIndividual);
    $doiRevision->setNewRevision(TRUE);
    $doiRevision->revision_log = t('DOI revision requested at %request_date.', [
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
    $wisskiDOIController = new WisskiDoiRestController();
    $wisskiDOIController->getDraftDoi($this->doiInfo);

    /*
     * Start second save process. This is the current revision now.
     */
    $doiRevision = $this->wisskiStorage->createRevision($this->wisskiIndividual);
    $doiRevision->revision_log = t('Revision copy, because of DOI request from %request_date.', [
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
