<?php

namespace Drupal\wisski_core\Plugin\Search;

//use Drupal\search\Plugin\SearchInterface;
use Drupal\search\Plugin\SearchPluginBase;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * @SearchPlugin(
 *   id = "wisski_individual_search",
 *   title = @Translation("Wisski Entities"),
 * )
 */
class WisskiEntitySearch extends SearchPluginBase {

  /**
   * Execute the search.
   *
   * This is a dummy search, so when search "executes", we just return a dummy
   * result containing the keywords and a list of conditions.
   *
   * @return array
   *   A structured list of search results
   */
  public function execute() {
    dpm($this,__METHOD__);
    $results = array();
    if (!$this->isSearchExecutable()) {
      return $results;
    }
    return array(
      array(
        'link' => Url::fromRoute('search.wisski.result')->toString(),
        'type' => 'Dummy result type',
        'title' => 'Dummy title',
        'snippet' => SafeMarkup::format("Dummy search snippet to display. Keywords: @keywords\n\nConditions: @search_parameters", ['@keywords' => $this->keywords, '@search_parameters' => print_r($this->searchParameters, TRUE)]),
      ),
    );
  }

  public function searchFormAlter(array &$form, FormStateInterface $form_state) {
  
    //dpm($form,__FUNCTION__);
    $form['entity_title'] = array(
      '#type' => 'textfield',
      '#autocomplete_route_name' => 'wisski.titles.autocomplete',
      '#autocomplete_route_parameters' => isset($selected_bundles)?array('bundles'=>$selected_bundles):array(),
      '#default_value' => '',
      '#title' => $this->t('Search by Entity Title'),
    );
    $storage = $form_state->getStorage();
    if (isset($storage['options'])) {
      $options = $storage['options'];
      $trigger = $form_state->getTriggeringElement();
      if ($trigger['#name'] == 'btn-add-bundle') {
        $input = $form_state->getUserInput()['bundles'];
        $new_bundle = $input['auto_bundle'];
        $selection = $input['select_bundles'];
        $matches = array();
        if (preg_match('/^(\w+)\s\((\w+)\)$/',$new_bundle,$matches)) {
          list(,$label,$id) = $matches;
          if (!isset($options[$id])) $options[$id] = $label;
          $selection[$id] = $id;
        }
      }
    } else {
      $bundle_ids = \Drupal::entityQuery('wisski_bundle')->range(0,1)->execute();
      $bundles = \Drupal\wisski_core\Entity\WisskiBundle::loadMultiple($bundle_ids);
      $options = array();
      foreach($bundles as $bundle_id => $bundle) $options[$bundle_id] = $bundle->label();
    }
    $storage['options'] = $options;
    $form_state->setStorage($storage);
    $form['bundles'] = array(
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#title' => $this->t('Search in Bundles'),
    );
    $form['bundles']['select_bundles'] = array(
      '#type' => 'checkboxes',
      '#options' => $options,
      '#title' => $this->t('Select Bundles'),
      '#prefix' => '<div id = wisski-search-bundles>',
      '#suffix' => '</div>',
    );
    if (isset($selection)) $form['bundles']['select_bundles']['#value'] = $selection;
    $form['bundles']['auto_bundle'] = array(
      '#type' => 'entity_autocomplete',
      '#target_type' => 'wisski_bundle',
      '#title' => $this->t('Find more Bundles'),
    );
    $form['bundles']['add_op'] = array(
      '#type' => 'button',
      '#value' => $this->t('Add'),
      '#limit_validation_errors' => array(),
      '#ajax' => array(
        'wrapper' => 'wisski-search-bundles',
        'callback' => 'Drupal\wisski_core\Plugin\Search\WisskiEntitySearch::ajaxCallback',
      ),
      '#name' => 'btn-add-bundle',
    );
    $form['fields']['#type'] = 'container';
    $form['fields']['#attributes']['id'] = 'wisski-search-fields';
    
  }

  public function buildSearchUrlQuery(FormStateInterface $form_state) {
    
    $parameter_keys = array(
      'keys',
      'bundles',
      'entity_title',
    );
    $parameters['keys'] = array();
    $vals = $form_state->getValues();
    //dpm($vals,__METHOD__);
    return array_intersect_key($vals,array_flip($parameter_keys));
    
  }

  public function ajaxCallback(array $form,FormStateInterface $form_state) {
    
    return $form['bundles']['select_bundles'];
  }

}