<?php

namespace Drupal\wisski_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\wisski_core\WisskiNamespaceManager;

use Symfony\Component\DependencyInjection\ContainerInterface;

class WisskiNamespaceForm extends FormBase {

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
    return 'wisski_namespace_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['add_namespace'] = array(
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => '+ Add Namespace',
      '#submit' => [[$this, 'addNamespace']],
    );

    $namespaces = $this->namespace_manager->getAllNamespaces();

    if (!$namespaces) {
      $form['no_namespaces'] = array(
        '#type' => 'item',
        '#title' => 'No namespaces found!',
      );
      return $form;
    }

    // Add the Operation menu to each row.
    foreach ($namespaces as $short_name => $long_name) {
      $rows[] = [$short_name, $long_name, $this->buildOperations($short_name)];
    }

    $form['table'] = array(
      '#type' => 'table',
      '#header' => ['Short name (prefix label)', 'Long name (IRI)', 'Operations'],
      '#rows' => $rows,
      '#cache' => ['max-age' => 0],
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('wisski.wisski_ontology.namespace');
  }

  public function addNamespace(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('wisski.wisski_ontology.namespace.add');
  }

  /**
   * Build the operation menu for each namespace row.
   *
   * @param string $short_name
   *   The short name of the namespace.
   *
   * @return array
   *  Drupal render array.
   */
  private function buildOperations(string $short_name): array{
    // MyFi: define a button to delete and edit namespaces
    // later this button will added to each row of the namespace list
    $links['edit'] = [
      'title' => $this->t('Edit'),
      'url' => Url::fromRoute('wisski.wisski_ontology.namespace.edit', ['short_name' => $short_name]),
    ];
    $links['delete'] = [
      'title' => $this->t('Delete'),
      'url' => Url::fromRoute('wisski.wisski_ontology.namespace.delete', ['short_name' => $short_name]),
    ];

    return [
      'data' => [
        '#type' => 'operations',
        '#links' => $links,
      ]
    ];
  }
}
