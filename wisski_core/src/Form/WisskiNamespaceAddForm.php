<?php

namespace Drupal\wisski_core\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\wisski_core\WisskiNamespaceManager;

use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Provides add a namespace.
 */
class WisskiNamespaceAddForm extends ConfirmFormBase {

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
    return 'wisski_namespace_add_form';
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
    return $this->t('Add a new namespace');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['short_name'] = array(
      '#type' => 'textfield',
      '#title' => "Short name",
      '#description' => 'The short name that will be used to abbreviate the full IRI.',
    );

    $form['long_name'] = array(
      '#type' => 'textfield',
      '#title' => "Long name (IRI)",
      '#description' => 'The IRI that will be abbreviated by the short name.',
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $short_name = $form_state->getValue('short_name');
    $long_name = $form_state->getValue('long_name');

    if (!WisskiNamespaceManager::isValidShortName($short_name)) {
      $form_state->setErrorByName('short_name', $this->t('Short name may not contain non-alphanumeric characters (spaces, special characters, - + * etc.) or longer than 32 characters.'));
    }
    // Check if there's already a namespace with the same short name
    $iri = $this->namespace_manager->getIRI($short_name);
    if ($iri) {
      $form_state->setErrorByName('short_name', $this->t('The short name "%short_name" is already used for IRI "%iri". Please pick another one', [
        '%short_name' => $short_name,
        '%iri' => $iri,
      ]));
    }

    if (!WisskiNamespaceManager::isValidIRI($long_name)) {
      $form_state->setErrorByName('long_name', $this->t('Invalid long name (IRI)'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->namespace_manager->insertNamespace($form_state->getValue('long_name'), $form_state->getValue('short_name'));
    $form_state->setRedirect('wisski.wisski_ontology.namespace');
  }
}
