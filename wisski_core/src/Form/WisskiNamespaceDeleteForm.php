<?php

namespace Drupal\wisski_core\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\wisski_core\WisskiNamespaceManager;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to remove a namespace.
 */
class WisskiNamespaceDeleteForm extends ConfirmFormBase {

  /**
   * The short name of the namespace to delete
   *
   * @var string
   */
  private string $short_name;

  /**
   * The namespace manager.
   *
   * @var Drupal\wisski_core\WisskiNamespaceManager
   */
  private WisskiNamespaceManager $namespace_manager;

  public function __construct(WisskiNamespaceManager $namespace_manager) {
    $this->namespace_manager = $namespace_manager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('wisski.wisski_core.namespace')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wisski_namespace_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('wisski.wisski_ontology.namespace');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the namespace %namespace?', ['%namespace' => $this->short_name]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $short_name = NULL) {
    $this->short_name = $short_name;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->namespace_manager->deleteNamespace($this->short_name);
    $form_state->setRedirect('wisski.wisski_ontology.namespace');
  }
}
