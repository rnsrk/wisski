<?php

namespace Drupal\wisski_doi\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\wisski_doi\Controller\WisskiDOIRESTController;
use Kint;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reverting a wisski_individual revision.
 *
 * @internal
 */
class WisskiRequestDOIConfirmForm extends ConfirmFormBase {

  /**
   * The WisskiEntity revision.
   *
   * @var \Drupal\wisski_core\WisskiEntityInterface
   */
  protected $revision;

  /**
   * The WissKI storage.
   *
   * @var \Drupal\wisski_core\WisskiStorageInterface
   */
  protected $wisskiStorage;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new NodeRevisionRevertForm.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   The wisski storage.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(EntityStorageInterface $wisski_storage, DateFormatterInterface $date_formatter, TimeInterface $time) {
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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wisski_doi_request_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to request a draft DOI for the revision?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.wisski_individual.version_history', ['wisski_individual' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Request Draft DOI');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $wisski_individual_revision = NULL) {
    $this->revision = $this->wisskiStorage->loadRevision($wisski_individual_revision);
    $form = parent::buildForm($form, $form_state);
    $doi_settings = \Drupal::configFactory()->getEditable('wisski_doi.wisski_doi_settings');
    $form['table'] = [
      '#type' => 'table',
      '#header' => [$this->t('Property'), $this->t('Value')],
      '#rows' => [
        [$this->t('BundleID'), $this->revision->bundle()],
        [$this->t('EntityID'), $this->revision->id()],
        [$this->t('Date'), $this->dateFormatter->format($this->revision->getRevisionCreationTime(), 'custom', 'd.m.Y H:i:s')],
        [$this->t('Author'), $this->revision->getRevisionUser()->getDisplayName()],
        [$this->t('Title'), $this->revision->label()],
        [$this->t('Publisher'), $doi_settings->get('data_publisher')]
        ],

      '#description' => $this->t('Revision Data'),
      '#weight' => 1,
    ];
    //dpm($form['table']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $wisskiDOIController = new WisskiDOIRESTController();
    $wisskiDOIController->getDraftDOI($form['table']);

    $original_revision_timestamp = $this->revision->getRevisionCreationTime();
    $this->logger('content')->notice('@type: reverted %title revision %revision.', ['@type' => $this->revision->bundle(), '%title' => $this->revision->label(), '%revision' => $this->revision->getRevisionId()]);
    $this->messenger()
      ->addStatus($this->t('DOI has been requested for %title from %revision-date', [
        '%title' => $this->revision->label(),
        '%revision-date' => $this->dateFormatter->format($original_revision_timestamp),
      ]));
    $form_state->setRedirect(
      'entity.wisski_individual.version_history',
      ['wisski_individual' => $this->revision->id()]
    );
  }

}
