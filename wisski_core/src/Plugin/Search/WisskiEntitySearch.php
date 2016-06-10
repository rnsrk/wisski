<?php

namespace Drupal\wisski_core\Plugin\Search;

//use Drupal\search\Plugin\SearchInterface;
use Drupal\search\Plugin\SearchPluginBase;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

use Drupal\wisski_core\WisskiHelper;

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
    if ($this->isSearchExecutable()) {
      $query = \Drupal::entityQuery('wisski_individual');
      foreach ($this->getParameters() as $key => $condition) {
        if (is_array($condition)) {
          $group = $query->andConditionGroup();
          foreach ($condition as $subkey => $subcondition) {
            $query->condition($key,$subkey);
            $group->condition($subkey,$subcondition);
          }
          $query->condition($group);
        } else $query->condition($key,$condition);
      }
      return $query->execute();
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
    $paths = (isset($storage['paths'])) ? $storage['paths']: array();
    $input = $form_state->getUserInput();
    if (isset($input['bundles']['select_bundles'])) {
      $selection = $input['bundles']['select_bundles'];
    } else $selection = array();
    if (isset($storage['options'])) {
      $options = $storage['options'];
      $trigger = $form_state->getTriggeringElement();
      if ($trigger['#name'] == 'btn-add-bundle') {    
        $new_bundle = $input['bundles']['auto_bundle']['input_field'];
        $matches = array();
        if (preg_match('/^(\w+)\s\((\w+)\)$/',$new_bundle,$matches)) {
          list(,$label,$id) = $matches;
          if (!isset($options[$id])) $options[$id] = $label;
          $paths[$id] = WisskiHelper::getPathOptions($id);
        }
      }
    } else {
      $bundle_ids = \Drupal::entityQuery('wisski_bundle')->range(0,1)->execute();
      $bundles = \Drupal\wisski_core\Entity\WisskiBundle::loadMultiple($bundle_ids);
      $options = array();
      foreach($bundles as $bundle_id => $bundle) {
        $options[$bundle_id] = $bundle->label();
        $paths[$bundle_id] = WisskiHelper::getPathOptions($bundle_id);
      }
    }
    $storage['paths'] = $paths;
    //dpm($paths,'Paths');
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
      '#prefix' => '<div id = wisski-search-bundles>',
      '#suffix' => '</div>',
      '#ajax' => array(
        'wrapper' => 'wisski-search-paths',
        'callback' => 'Drupal\wisski_core\Plugin\Search\WisskiEntitySearch::replacePaths',
      ),
    );
    $form['bundles']['auto_bundle'] = array(
      '#type' => 'container',
      '#attributes' => array('class' => 'container-inline','title' => $this->t('Find more Bundles')),
      
    );
    $form['bundles']['auto_bundle']['input_field'] = array(
      '#type' => 'entity_autocomplete',
      '#target_type' => 'wisski_bundle',
      '#size' => 48,
    );
    $form['bundles']['auto_bundle']['add_op'] = array(
      '#type' => 'button',
      '#value' => '+',
      '#limit_validation_errors' => array(),
      '#ajax' => array(
        'wrapper' => 'wisski-search-bundles',
        'callback' => 'Drupal\wisski_core\Plugin\Search\WisskiEntitySearch::replaceSelectBoxes',
      ),
      '#name' => 'btn-add-bundle',
    );
    $selection = array_filter($selection);
    $selected_paths = array_intersect_key($paths,$selection);
    $form['paths'] = array(
      '#type' => 'hidden',
      '#prefix' => '<div id=wisski-search-paths>',
      '#suffix' => '</div>',
      '#tree' => TRUE,
      '#title' => $this->t('Search in Paths'),
    );
    if (!empty($selected_paths)) {
      $form['paths']['#type'] = 'fieldset';
      foreach ($selected_paths as $bundle_id => $bundle_paths) {
        $form['paths'][$bundle_id] = array(
          '#type' => 'fieldset',
          '#tree' => TRUE,
          '#title' => $options[$bundle_id],
        );
        foreach ($bundle_paths as $pb => $pb_paths) {
          if (is_string($pb_paths)) {
            //this is a global pseudo-path like 'uri'
            $form['paths'][$bundle_id][$pb] = array(
              '#type' => 'textfield',
              '#title' => $pb_paths,
            );
          } else {
            foreach ($pb_paths as $path_id => $path_label) {
              $form['paths'][$bundle_id][$path_id] = array(
                '#type' => 'textfield',
                '#title' => $path_label,
                '#description' => $path_id,
              );
            }
          }
        }
      }
    }
  }

  public function buildSearchUrlQuery(FormStateInterface $form_state) {
    
    $parameter_keys = array(
      'keys',
      'bundles',
      'entity_title',
      'paths',
    );
    $parameters['keys'] = array();
    $vals = $form_state->getValues();
    //dpm($vals,__METHOD__);
    return array_intersect_key($vals,array_flip($parameter_keys));
    
  }

  public function replaceSelectBoxes(array $form,FormStateInterface $form_state) {
  drupal_set_message('AJAX calling');    
    return $form['bundles']['select_bundles'];
  }
  
  public function replacePaths(array $form,FormStateInterface $form_state) {
    
    return $form['paths'];
  }

}