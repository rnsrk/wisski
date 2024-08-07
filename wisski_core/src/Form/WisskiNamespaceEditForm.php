<?php

namespace Drupal\wisski_core\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\wisski_core\WisskiNamespaceManager;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to edit a namespace.
 */
class WisskiNamespaceEditForm extends ConfirmFormBase {

  /**
   * The short name of the namespace to edit
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
    return 'wisski_namespace_edit_form';
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
    return $this->t('Edit %namespace', ['%namespace' => $this->short_name]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $short_name = NULL) {
    $this->short_name = $short_name;

    $form['short_name'] = array(
      '#type' => 'textfield',
      '#default_value' => $short_name,
      '#title' => "New short name for <em>$short_name</em>:",
      '#description' => 'The short name that will be used to abbreviate the full IRI.',
    );

    $long_name = $this->namespace_manager->getIRI($short_name);
    $form['long_name'] = array(
      '#type' => 'textfield',
      '#default_value' => $long_name,
      '#title' => "New long name (IRI) for <em>$short_name</em>:",
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
    // In case're not just redefining the IRI
    if ($short_name != $this->short_name) {
      // Check if there's already a namespace with the same short name
      $iri = $this->namespace_manager->getIRI($short_name);
      if ($iri) {
        $form_state->setErrorByName(
          'short_name',
          $this->t('The short name "%short_name" is already used for IRI "%iri". Please pick another one', [
            '%short_name' => $short_name,
            '%iri' => $iri,
          ])
        );
      }
    }

    if (!WisskiNamespaceManager::isValidIRI($long_name)) {
      $form_state->setErrorByName('long_name', $this->t('Invalid long name (IRI)'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->namespace_manager->editNamespace($this->short_name, $form_state->getValue('short_name'), $form_state->getValue('long_name'));
    $form_state->setRedirect('wisski.wisski_ontology.namespace');
  }
}
