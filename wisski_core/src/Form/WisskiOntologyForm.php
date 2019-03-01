<?php

namespace Drupal\wisski_core\Form;

use Drupal\wisski_salz\Entity\Adapter;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Overview form for ontology handling.
 *
 * @return form
 *   Form for the ontology handling menu
 *
 * @author Mark Fichtner
 */
class WisskiOntologyForm extends FormBase {

  /**
   * {@inheritdoc}.
   * The Id of every WissKI form is the name of the form class.
   */
  public function getFormId() {
    return 'WisskiOntologyForm';
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    // In wisski d8 there will be no local stores anymore,
    // we assume that every store could load an ontology
    // we load all store entities and
    // have to choose for which store we want to load an ontology.
    $adapters = Adapter::loadMultiple();

    $adapterlist = [];

    // Create a list of all adapters to choose from.
    foreach ($adapters as $adapter) {
      // If an adapter is not writable, it should not be allowed to load an ontology for that store.
      if ($adapter->getEngine()->isWritable()) {

        $adapterlist[$adapter->id()] = $adapter->label();
      }
    }

    // Check if there is a selected store.
    $selected_store = "";

    $selected_store = !empty($form_state->getValue('select_store')) ? $form_state->getValue('select_store') : "0";

    // Generate a select field.
    $form['select_store'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the store for which you want to load an ontology.'),
      '#default_value' => $selected_store,
      '#options' => array_merge(["0" => 'Please select.'], $adapterlist),
      '#ajax' => [
        'callback' => 'Drupal\wisski_core\Form\WisskiOntologyForm::ajaxStores',
        'wrapper' => 'select_store_div',
        'event' => 'change',
    // 'effect' => 'slide',.
      ],
    ];

    // Ajax wrapper.
    $form['stores'] = [
      '#type' => 'markup',
    // The prefix/suffix provide the div that we're replacing, named by
    // #ajax['wrapper'] below.
      '#prefix' => '<div id="select_store_div">',
      '#suffix' => '</div>',
      '#value' => "",
    ];

    // If there is already a store selected.
    if (!empty($form_state->getValue('select_store'))) {

      // If there is a selected store - check if there is an ontology in the store.
      $selected_id = $form_state->getValue('select_store');
      $selected_name = $adapterlist[$selected_id];
      // Load the store adapter entity object by means of the id of the selected store.
      $selected_adapter = Adapter::load($selected_id);
      // drupal_set_message('Current selected adapter: ' . serialize($selected_adapter));
      // load the engine of the adapter.
      $engine = $selected_adapter->getEngine();

      // If the engine is of type sparql11_with_pb we can load the existing ontologies.
      if ($engine->getPluginId() === 'sparql11_with_pb') {

        // drupal_set_message('Type: ' . $engine->getPluginId());
        $infos = $engine->getOntologies();
        // drupal_set_message(serialize($infos));
        // dpm($infos);
        // There already is an ontology.
        if (!empty($infos) && count($infos) > 0) {
          $form['stores']['header'] = [
            '#type' => 'item',
            '#markup' => '<b>Currently loaded Ontology:</b><br/>',
          ];

          $table = "<table><tr><th>Name</th><th>Iri</th><th>Version</th><th>Graph</th></tr>";
          foreach ($infos as $ont) {
            // $table .= "<tr><td>" . $ont->ont . "</td><td>" . $ont->iri . "</td><td>" . $ont->ver . "</td><td>" . $ont->graph . "</td></tr>";.
            $table .= "<tr><td>" . $ont->ont . "</td><td>" . $ont->iri . "</td><td>" . $ont->ver . "</td><td>" . $ont->graph . "</td></tr>";
          }

          $table .= "</table>";

          $form['stores']['table'] = [
            '#type' => 'item',
            '#markup' => $table,
          ];

          $form['stores']['delete_ont'] = [
            '#type' => 'submit',
            '#name' => 'Delete Ontology',
            '#value' => 'Delete Ontology',
            '#submit' => ['::deleteOntology'],
          ];

          $ns = "";
          $ns = $engine->getNamespaces();

          $tablens = "<table><tr><th>Short Name</th><th>URI</th></tr>";
          foreach ($ns as $key => $value) {
            $tablens .= "<tr><td>" . $key . "</td><td>" . $value . "</td></tr>";
          }
          $tablens .= "</table>";

          $form['stores']['ns_table'] = [
            '#type' => 'item',
            '#markup' => $tablens,
          ];

        }
        else {
          // No ontology was found.
          $form['stores']['load_onto'] = [
            '#type' => 'textfield',
            '#title' => "Load Ontology for store <em> $selected_name </em>:",
            '#description' => 'Please give the URL to a loadable ontology.',
          ];

          $form['stores']['load_onto_submit'] = [
            '#type' => 'submit',
            '#name' => 'Load Ontology',
            '#value' => 'Load Ontology',
          // '#submit' => array('wisski_core_load_ontology'),.
          ];
        }

      }
    }

    return $form;

  }

  /**
   *
   */
  public static function ajaxStores(array $form, FormStateInterface $form_state) {
    // dpm("yay!");.
    return $form['stores'];
  }

  /**
   *
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // drupal_set_message('hello');.
  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // If there is a selected store - check if there is an ontology in the store.
    $selected_id = $form_state->getValue('select_store');
    // Load the store adapter entity object by means of the id of the selected store.
    $selected_adapter = Adapter::load($selected_id);
    // Load the engine of the adapter.
    $engine = $selected_adapter->getEngine();
    // drupal_set_message('hello submit engine ' . $engine->getPluginId());
    // If the engine is of type sparql11_with_pb we can load the existing ontologies.
    if ($engine->getPluginId() === 'sparql11_with_pb') {
      $infos = $engine->getOntologies();
      // drupal_set_message('infos in submit' . serialize($infos));
      // redirect to the wisski config ontology page
      // $form_state->setRedirectUrl('/dev/admin/config/wisski/ontology');
      // rebuild the form to display the information regarding the selected store.
      $form_state->setRebuild();
      // $form_state->setUserInput($form_state->getValue('select_store'));.
      $engine->addOntologies($form_state->getValue('load_onto'));
    }
    return;

  }

  /**
   *
   */
  public function deleteOntology(array &$form, FormStateInterface $form_state) {
    $selected_id = $form_state->getValue('select_store');
    // Load the store adapter entity object by means of the id of the selected store.
    $selected_adapter = Adapter::load($selected_id);
    // Load the engine of the adapter.
    $engine = $selected_adapter->getEngine();
    // drupal_set_message('hello engine ' . $engine->getPluginId());
    // If the engine is of type sparql11_with_pb we can load the existing ontologies.
    if ($engine->getPluginId() === 'sparql11_with_pb') {
      $infos = $engine->getOntologies();

      // There already is an ontology and we want to delete it.
      if (!empty($infos)) {
        foreach ($infos as $ont) {
          if (strval($ont->graph) != "default") {
            $engine->deleteOntology(strval($ont->graph));
            drupal_set_message('Successfully deleted ontology ' . $ont->graph);
          }
          else {
            $engine->deleteOntology(strval($ont->ont), 'no-graph');
            drupal_set_message('Successfully deleted ontology ' . $ont->ont);
          }
          // Redirect to the wisski config ontology page
          // $form_state->setRedirectUrl('/dev/admin/config/wisski/ontology');
          // rebuild the form to display the information regarding the selected store.
          $form_state->setRebuild();
        }
      }

    }

  }

}
