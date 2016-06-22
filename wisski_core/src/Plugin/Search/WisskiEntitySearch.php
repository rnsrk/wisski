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
   * Maximum number of bundles to show on initial page
   */
  private $bundle_limit = 16;
  
  /**
   * Maximum number of paths to show for each bundle
   */
  private $path_limit = 10;

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
    
    //dpm($this,__METHOD__);
    $results = array();
    if ($this->isSearchExecutable()) {
      $query = \Drupal::entityQuery('wisski_individual');
      $query->setPathQuery();
      $parameters = $this->getParameters();
      dpm($parameters,__FUNCTION__.'::parameters');
      foreach ($parameters['bundles'] as $bundle_id) {
        if (!isset($parameters[$bundle_id])) continue;
        
        switch ($parameters[$bundle_id]['query_type']) {
          case 'AND': $group = $query->andConditionGroup(); break;
          case 'OR' : 
          default: $group = $query->orConditionGroup();
        }
        $qroup = $group->condition('bundle',$bundle_id);
        foreach ($parameters[$bundle_id]['paths'] as $path_id => $search_string) {
          $group = $group->condition($path_id,$search_string);
        }
        $query->condition($group);
      }
      $results = $query->execute();
    }
    dpm($results,__METHOD__.'::results');
    $return = array();
    foreach ($results as $entity_id) {
      $return[] = array(
        'link' => Url::fromRoute('entity.wisski_individual.view',array('wisski_individual'=>$entity_id))->toString(),
        'type' => 'Wisski Entity',
        'title' => $entity_id,
      );
    }
    return $return;
  }

  public function searchFormAlter(array &$form, FormStateInterface $form_state) {
  
    //dpm($form,__FUNCTION__);
    dpm($this,__METHOD__);
    unset($form['basic']);
    
    $form['entity_title'] = array(
      '#type' => 'textfield',
      '#autocomplete_route_name' => 'wisski.titles.autocomplete',
      '#autocomplete_route_parameters' => isset($selected_bundles)?array('bundles'=>$selected_bundles):array(),
      '#default_value' => '',
      '#title' => $this->t('Search by Entity Title'),
      '#description' => $this->t('Finds titles from the cache table'),
      '#attributes' => array('placeholder' => $this->t('Entity Title')),
    );
    $storage = $form_state->getStorage();
    $paths = (isset($storage['paths'])) ? $storage['paths']: array();
    $input = $form_state->getUserInput();
    //dpm($input);
    if (isset($input['advanced']['bundles']['select_bundles'])) {
      $selection = $input['advanced']['bundles']['select_bundles'];
    } else $selection = array();
    if (isset($storage['options'])) {
      $options = $storage['options'];
      $trigger = $form_state->getTriggeringElement();
      if ($trigger['#name'] == 'btn-add-bundle') {    
        $new_bundle = $input['advanced']['bundles']['auto_bundle']['input_field'];
        $matches = array();
        if (preg_match('/^(.+)\s\((\w+)\)$/',$new_bundle,$matches)) {
          list(,$label,$id) = $matches;
          if (!isset($options[$id])) $options[$id] = $label;
          $paths[$id] = WisskiHelper::getPathOptions($id);
        }
      }
    } else {
      $bundle_count = \Drupal::entityQuery('wisski_bundle')->count()->execute();
      $bundle_ids = \Drupal::entityQuery('wisski_bundle')->range(0,$this->bundle_limit)->execute();
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
    $form['advanced'] = array(
      '#type' => 'details',
      '#tree' => TRUE,
      '#title' => $this->t('Advanced Search'),
    );
    /*
    $form['advanced']['keys'] = array(
      '#type' => 'search',
      '#title' => $this->t('Search for keywords'),
      '#size' => 60,
    );
    */
    $form['advanced']['bundles'] = array(
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#title' => $this->t('in Bundles')
    );
    $form['advanced']['bundles']['select_bundles'] = array(
      '#type' => 'checkboxes',
      '#options' => $options,
      '#prefix' => '<div id = wisski-search-bundles>',
      '#suffix' => '</div>',
      '#ajax' => array(
        'wrapper' => 'wisski-search-paths',
        'callback' => 'Drupal\wisski_core\Plugin\Search\WisskiEntitySearch::replacePaths',
      ),
    );
    if ($bundle_count > $this->bundle_limit) {
      $form['advanced']['bundles']['auto_bundle'] = array(
        '#type' => 'container',
        '#attributes' => array('class' => 'container-inline','title' => $this->t('Find more Bundles')),
        
      );
      $form['advanced']['bundles']['auto_bundle']['input_field'] = array(
        '#type' => 'entity_autocomplete',
        '#target_type' => 'wisski_bundle',
        '#size' => 48,
        '#attributes' => array('placeholder' => $this->t('Bundle Name')),
      );
      $form['advanced']['bundles']['auto_bundle']['add_op'] = array(
        '#type' => 'button',
        '#value' => '+',
        '#limit_validation_errors' => array(),
        '#ajax' => array(
          'wrapper' => 'wisski-search-bundles',
          'callback' => 'Drupal\wisski_core\Plugin\Search\WisskiEntitySearch::replaceSelectBoxes',
        ),
        '#name' => 'btn-add-bundle',
      );
    } else $form['advanced']['bundles']['auto_bundle']['#type'] = 'hidden';
    //dpm(array($selection,$paths));
    $selection = array_filter($selection);
    $selected_paths = array_intersect_key($paths,$selection);
    $form['advanced']['paths'] = array(
      '#type' => 'hidden',
      '#prefix' => '<div id=wisski-search-paths>',
      '#suffix' => '</div>',
      '#tree' => TRUE,
      '#title' => $this->t('in Paths'),
    );
    if (!empty($selected_paths)) {
      $form['advanced']['paths']['#type'] = 'fieldset';
      foreach ($selected_paths as $bundle_id => $bundle_paths) {
        $bundle_path_options = array();
        $form['advanced']['paths'][$bundle_id] = array(
          '#type' => 'fieldset',
          '#tree' => TRUE,
          '#title' => $options[$bundle_id],
        );
        foreach ($bundle_paths as $pb => $pb_paths) {
          if (is_string($pb_paths)) {
            //this is a global pseudo-path like 'uri'
            $bundle_path_options[$pb] = $pb_paths;
          } else {
            foreach ($pb_paths as $path_id => $path_label) {
              $bundle_path_options[$path_id] = "$path_label ($path_id)";
            }
          }
        }
        for ($i = 0; $i < $this->path_limit && $i < count($bundle_path_options); $i++) {
          if ($list = each($bundle_path_options)) {
            list($path_id) = $list;
            $form['advanced']['paths'][$bundle_id][$i] = array(
              '#type' => 'container',
              '#attributes' => array('class' => 'container-inline'),
              '#tree' => TRUE,
              'input_field' => array(
                '#type' => 'search',
                '#default_value' => '',
                '#size' => 30,
                '#weight' => 1,
              ),
              'in' => array(
                '#markup' => '&nbsp; '.$this->t('in').' &nbsp;',
                '#weight' => 2,
              ),
              'path_selection' => array(
                '#type' => 'select',
                '#options' => $bundle_path_options,
                '#default_value' => $path_id,
                '#weight' => 3,
              ),
              
            );
          }
        }
        $form['advanced']['paths'][$bundle_id]['query_type'] = array(
          '#type' => 'container',
          '#attributes' => array('class' => 'container-inline'),
          'selection' => array(
            '#type' => 'radios',
            '#options' => array('AND' => $this->t('All'),'OR' => $this->t('Any')),
            '#default_value' => 'AND',
            '#title' => $this->t('Match'),
          ),
        );
      }
    }
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Search Wisski Entities'),
    );
    //dpm($form);
  }

  public function buildSearchUrlQuery(FormStateInterface $form_state) {
    
    $vals = $form_state->getValues();
    $keys = '';
    foreach($vals['advanced']['paths'] as $bundle_id => $paths) {
      $return[$bundle_id]['query_type'] = $paths['query_type']['selection'];
      unset($paths['query_type']);
      foreach ($paths as $path_parameters) {
        if ($path_parameters['input_field']) $keys[] = $return[$bundle_id]['paths'][$path_parameters['path_selection']] = trim($path_parameters['input_field']);
      }
    }
    $return['bundles'] = array_filter($vals['advanced']['bundles']['select_bundles']);
    $return['entity_title'] = $vals['entity_title'];
    // 'keys' must be set for the Search Plugin, don't know why
    $return['keys'] = implode(', ',$keys);
    return $return;    
  }

  public function replaceSelectBoxes(array $form,FormStateInterface $form_state) {
    return $form['advanced']['bundles']['select_bundles'];
  }
  
  public function replacePaths(array $form,FormStateInterface $form_state) {
    
    return $form['advanced']['paths'];
  }
}