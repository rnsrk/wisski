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
        foreach ($parameters[$bundle_id]['paths'] as list($path_id,$search_string,$operator)) {
          //dpm($operator.' '.$search_string,'Setting condition');
          $group = $group->condition($path_id,$search_string,$operator);
        }
        $query->condition($group);
        //dpm($query);
      }
      $results = $query->execute();
    }
    dpm($results,__METHOD__.'::results');
    $return = array();
    foreach ($results as $entity_id) {
      $title = \Drupal\wisski_core\WisskiCacheHelper::getEntityTitle($entity_id);
      if (is_null($title)) $title = $entity_id;
      $return[] = array(
        'link' => Url::fromRoute('entity.wisski_individual.canonical',array('wisski_individual'=>$entity_id))->toString(),
        'type' => 'Wisski Entity',
        'title' => $title,
      );
    }
    return $return;
  }

  public function searchFormAlter(array &$form, FormStateInterface $form_state) {
  
    //dpm($form,__FUNCTION__);
    //dpm($this,__METHOD__);
    unset($form['basic']);

    if (!empty($_GET)) $defaults = $_GET;
    elseif (!empty($_POST)) $defaults = $_POST;
    //if (isset($defaults)) dpm($defaults,'Defaults');
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
    //dpm($input,'User input');
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
      // don't load only bundle_limit amount of bundles
      #$bundle_ids = \Drupal::entityQuery('wisski_bundle')->range(0,$this->bundle_limit)->execute();
      // load all
      $bundle_ids = \Drupal::entityQuery('wisski_bundle')->execute();
      
      // now filter them again
      // get all top groups from pbs
      $parents = \Drupal\wisski_core\WisskiHelper::getTopBundleIds();
        
      // only show top groups
      foreach($bundle_ids as $bundle_id => $label) {
        if(!in_array($bundle_id, $parents))
          unset($bundle_ids[$bundle_id]);
      }
                            
      if (isset($defaults['bundles'])) $bundle_ids = array_unique(array_merge($bundle_ids,array_values($defaults['bundles'])));
      $bundles = \Drupal\wisski_core\Entity\WisskiBundle::loadMultiple($bundle_ids);
      $options = array();
      $selection = array();
      foreach($bundles as $bundle_id => $bundle) {
        $options[$bundle_id] = $bundle->label();
        $paths[$bundle_id] = WisskiHelper::getPathOptions($bundle_id);
        if (isset($defaults['bundles'][$bundle_id])) $selection[$bundle_id] = $bundle_id;
        else $selection[$bundle_id] = 0;
      }
    }
    
    //dpm($selection,'selection');
    $storage['paths'] = $paths;
    //dpm($paths,'Paths');
    $storage['options'] = $options;
    $form_state->setStorage($storage);
    $form['advanced'] = array(
      '#type' => 'details',
      '#tree' => TRUE,
      '#title' => $this->t('Advanced Search'),
      '#open' => isset($defaults['bundles']),
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
      '#default_value' => $selection,
      '#prefix' => '<div id = wisski-search-bundles>',
      '#suffix' => '</div>',
      '#ajax' => array(
        'wrapper' => 'wisski-search-paths',
        'callback' => array($this,'replacePaths'),
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
          'callback' => array($this,'replaceSelectBoxes'),
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
            if (isset($defaults[$bundle_id]['paths'][$pb])) $bundle_path_defaults[$pb] = $defaults[$bundle_id]['paths'][$pb];
          } else {
            foreach ($pb_paths as $path_id => $path_label) {
              $bundle_path_options[$path_id] = "$path_label ($path_id)";
            }
          }
        }
        if (isset($defaults[$bundle_id]['paths'])) $bundle_path_defaults = $defaults[$bundle_id]['paths'];
        else $bundle_path_defaults = array();
        //dpm($bundle_path_defaults,'defaults '.$bundle_id);
        for ($i = 0; $i < $this->path_limit && $i < count($bundle_path_options); $i++) {
          $list = each($bundle_path_defaults);
          $def_input = '';
          $def_operator = '=';
          if ($list) list(,list($path_id,$def_input,$def_operator)) = $list;
          else {
            $list = each($bundle_path_options);
            if ($list) list($path_id) = $list;
          }
          if ($list !== FALSE) {
            $form['advanced']['paths'][$bundle_id][$i] = array(
              '#type' => 'container',
              '#attributes' => array('class' => 'container-inline', 'data-wisski' => $bundle_id.'.'.$i),
              '#tree' => TRUE,
              'path_selection' => array(
                '#type' => 'select',
                '#options' => $bundle_path_options,
                '#default_value' => $path_id,
                '#weight' => 1,
              ),
              'operator' => array(
                '#type' => 'select',
                '#options' => $this->getSearchOperators(),
                '#default_value' => $def_operator,
                '#weight' => 2,
              ),
              'input_field' => array(
                '#type' => 'textfield',
                '#default_value' => $def_input,
                '#size' => 30,
                '#weight' => 3,
              ),
              '#element_validate' => array(array($this,'validateChoice')),
            );
          }
        }
        $form['advanced']['paths'][$bundle_id]['query_type'] = array(
          '#type' => 'container',
          '#attributes' => array('class' => 'container-inline'),
          'selection' => array(
            '#type' => 'radios',
            '#options' => array('AND' => $this->t('All'),'OR' => $this->t('Any')),
            '#default_value' => isset($defaults[$bundle_id]['query_type']) ? $defaults[$bundle_id]['query_type'] : 'AND',
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

  protected function getSearchOperators() {
  
    return array(
      '=' => $this->t('exactly'),
      '<>' => $this->t('not equal'),
      '>' => '>',
      '>=' => '>=',
      '<' => '<',
      '<=' => '<=',
      'STARTS_WITH' => $this->t('Starts with'),
      'CONTAINS' => $this->t('Contains'),
      'ENDS_WITH' => $this->t('Ends with'),
      'ALL' => $this->t('all of'),
      'IN' => $this->t('one of'),
      'NOT_IN' => $this->t('none of'),
      'BETWEEN' => $this->t('between'),
    );
    
  }
  
  public function validateChoice(array $element, FormStateInterface $form_state, array $form) {
  
    //dpm(func_get_args(),__METHOD__);
    list($bundle_id,$row_num) = explode('.',$element['#attributes']['data-wisski']);
    $vals = $form_state->getValue(array('advanced','paths',$bundle_id,$row_num));
    $input = $vals['input_field'];
    switch ($vals['operator']) {
      case '=':
      case '<>':
      case '>':
      case '>=':
      case '<':
      case '<=':
      case 'STARTS_WITH':
      case 'CONTAINS':
      case 'ENDS_WITH': {    
        if (!empty($input) && strlen($input) < 3) {
          $form_state->setError(
            $element['input_field'],
            $this->t('Search string must consists of at least three (3) characters')
          );
          //dpm($vals,__FUNCTION__.'::values');
        }
        break;
      }
      case 'ALL':
      case 'IN':
      case 'NOT IN': break;
      case 'BETWEEN': {
        if (!empty($input) && !preg_match('/^\s*\S+\s*\,\s*\S+\s*$/',$input)) {
          $form_state->setError(
            $element['input_field'],
            $this->t(
              'For the %between query, the search string must contain exactly two values divided by a comma (,)',
              array('%between' => $this->getSearchOperators()['BETWEEN'])
            )
          );
          //dpm($vals,__FUNCTION__.'::values');
        }
        break;
      }
    }
    
  }

  public function buildSearchUrlQuery(FormStateInterface $form_state) {
    
    $vals = $form_state->getValues();
    dpm($vals,__FUNCTION__.'::values');
    $keys = '';
    foreach($vals['advanced']['paths'] as $bundle_id => $paths) {
      $return[$bundle_id]['query_type'] = $paths['query_type']['selection'];
      unset($paths['query_type']);
      foreach ($paths as $path_parameters) {
        if ($path_parameters['input_field']) $keys[] = $return[$bundle_id]['paths'][] = array($path_parameters['path_selection'],trim($path_parameters['input_field']),$path_parameters['operator']);
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