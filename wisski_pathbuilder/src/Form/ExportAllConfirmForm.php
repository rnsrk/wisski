<?php

namespace Drupal\wisski_pathbuilder\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use \Drupal\wisski_pathbuilder\PathbuilderManager;

/**
 * Exports the pathbuilders and ontologies.
 *
 * Zips all pathbuildes and all ontologies of all adapters
 * and saves it the public directory public://wisski_export/.
 */
class ExportAllConfirmForm extends ConfirmFormBase {

  /**
   * @var \Drupal\wisski_pathbuilder\PathbuilderManager
   */
  private PathbuilderManager $pathbuilderManager;

  /**
   * Create service container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The class container.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('wisski_pathbuilder.manager')
    );
  }

  /**
   * Constructs form variables.
   *
   * @param \Drupal\wisski_pathbuilder\PathbuilderManager $pathbuilderManager
   *   Performs file system operations and updates database records accordingly.
   */
  public function __construct(PathbuilderManager $pathbuilderManager,) {
    $this->pathbuilderManager = $pathbuilderManager;
  }

  /**
   * The question.
   */
  public function getQuestion() {
    return $this->t('Do you want to export all pathbuilders and related ontologies?');
  }

  /**
   * The route if you hit Cancel.
   */
  public function getCancelUrl() {
    return new Url('entity.wisski_pathbuilder.collection');
  }

  /**
   * The form id.
   */
  public function getFormId() {
    return 'wisski_pathbuilder_export_all_confirm_form';
  }

  /**
   * The description.
   */
  public function getDescription() {
    return 'This creates a zip file containing every pathbuilder and every ontology.';
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    return parent::buildForm($form, $form_state);

  }

  /**
   * Loads all pathbuilders and all ontologies and saves them.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->pathbuilderManager->exportPathbuildersAndOntology();
    $form_state->setRedirect(
      'entity.wisski_pathbuilder.collection');
  }

}
