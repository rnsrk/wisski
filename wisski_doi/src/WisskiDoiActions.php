<?php

namespace Drupal\wisski_doi;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\user\Entity\User;
use Drupal\wisski_core\WisskiEntityInterface;
use Drupal\wisski_core\WisskiStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for DOI actions.
 */
class WisskiDoiActions {
  use StringTranslationTrait;
  /**
   * The WisskiEntity revision.
   *
   * @var \Drupal\wisski_core\WisskiEntityInterface
   */
  protected WisskiEntityInterface $revision;

  /**
   * The WissKI storage.
   *
   * @var \Drupal\wisski_core\WisskiStorage
   */
  protected WisskiStorage $wisskiStorage;

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
   * Populate the reachable variables from services.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The class container.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('string_translation'),
      $container->get('date.formatter'),
      $container->get('datetime.time'),
      $container->get('wisski_doi.wisski_doi_rest_actions'),
      $container->get('wisski_doi.wisski_doi_db_actions'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Constructs a new form to request a DOI for a static revision.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The WissKI Storage service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\wisski_doi\WisskiDoiRestActions $wisskiDoiRestActions
   *   The WissKi DOI Rest service.
   * @param \Drupal\wisski_doi\WisskiDoiDbActions $wisskiDoiDbActions
   *   The WissKI DOI database service.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The Drupal entity type manager service.
   */
  public function __construct(TranslationInterface $stringTranslation,
                              DateFormatterInterface $date_formatter,
                              TimeInterface $time,
                              WisskiDoiRestActions $wisskiDoiRestActions,
                              WisskiDoiDbActions $wisskiDoiDbActions,
                              EntityTypeManager $entityTypeManager,
                              ) {
    $this->stringTranslation = $stringTranslation;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
    $this->wisskiDoiRestActions = $wisskiDoiRestActions;
    $this->wisskiDoiDbActions = $wisskiDoiDbActions;
    $this->wisskiStorage = $entityTypeManager->getStorage('wisski_individual');
  }

  /**
   * Assembles metadata from WissKI individual.
   *
   * @param \Drupal\wisski_core\WisskiEntityInterface $wisskiIndividual
   *   The WissKI Individual.
   *
   * @return array
   *   The metadata of the WissKI individual.
   */
  public function getWisskiIndividualMetadata(WisskiEntityInterface $wisskiIndividual) {
    $revisionUser = $wisskiIndividual->getRevisionUser();
    if (!empty($revisionUser)) {
      $author = $revisionUser->getDisplayName();
    }
    else {
      $uid = $wisskiIndividual->get('uid')->getValue()[0]['target_id'];
      $author = User::load($uid)->getDisplayName();
    }
    return [
      "bundleId" => $wisskiIndividual->bundle(),
      'entityId' => $wisskiIndividual->id(),
      'author' => $author,
      'title' => $wisskiIndividual->label(),
      'creationDate' => date('d.m.Y H:i:s', $wisskiIndividual->getRevisionCreationTime()),
      'language' => $wisskiIndividual->language()->getId(),
    ];
  }

  /**
   * Requests a DOI for a static revision.
   *
   * Saves two revisions, to yield a static revision,
   * requests a DOI for that revision and saves DOI data
   * to local database.
   *
   * @param \Drupal\wisski_core\WisskiEntityInterface $wisskiIndividual
   *   The WissKI individual.
   * @param array $doiMetadata
   *   The DOI metadata.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function getStaticDoi(WisskiEntityInterface $wisskiIndividual, array $doiMetadata) {

    // Load metadata of WissKI individual.
    $wisskiIndividualMetaData = $this->getWisskiIndividualMetadata($wisskiIndividual);

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
    ]);
    $doiRevision->save();
  }

}
