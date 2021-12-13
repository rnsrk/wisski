<?php

namespace Drupal\wisski_doi\Form;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\wisski_core\WisskiStorageInterface;
use Drupal\wisski_core\WisskiEntityInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\wisski_doi\Controller\WisskiDOIRESTController;
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
  private $wisskiIndividual;

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
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'wisski_doi_request_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return t('Are you sure you want to request a draft DOI for the revision?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('entity.wisski_individual.canonical', ['wisski_individual' => $this->wisskiIndividual->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
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
  public function buildForm(array $form, FormStateInterface $form_state, $wisski_individual = NULL): array {
    $this->wisskiIndividual = $this->wisskiStorage->load($wisski_individual);
    //$this->revision = $this->wisskiStorage->loadRevision($this->wisskiIndividual->getRevisionId());
    $form = parent::buildForm($form, $form_state);
    $doi_settings = \Drupal::configFactory()
      ->getEditable('wisski_doi.wisski_doi_settings');

    $form['table'] = [
      '#type' => 'table',
      '#header' => [$this->t('Property'), $this->t('Value')],
      '#rows' => [
        [$this->t('BundleID'), $this->wisskiIndividual->bundle()],
        [$this->t('EntityID'), $this->wisskiIndividual->id()],
        [
          $this->t('Date'),
          $this->dateFormatter->format($this->wisskiIndividual->getRevisionCreationTime(), 'custom', 'd.m.Y H:i:s'),
        ],
        [
          $this->t('Author'),
          $this->wisskiIndividual->getRevisionUser()->getDisplayName(),
        ],
        [$this->t('Title'), $this->wisskiIndividual->label()],
        [$this->t('Publisher'), $doi_settings->get('data_publisher')],
      ],

      '#description' => $this->t('Revision Data'),
      '#weight' => 1,
    ];
    // dpm($form['table']);.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $wisskiDOIController = new WisskiDOIRESTController();
    $wisskiDOIController->getDraftDOI($form['table']);

    $original_revision_timestamp = $this->revision->getRevisionCreationTime();
    /*
    $form_state->setRedirect(
    'entity.wisski_individual.version_history',

    [
    'wisski_individual' => $this->revision->id(),
    ]

    );
     */
  }

}
