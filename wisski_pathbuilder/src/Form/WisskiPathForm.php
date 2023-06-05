<?php

namespace Drupal\wisski_pathbuilder\Form;

use Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity;
use Drupal\wisski_salz\EngineInterface;
use Drupal\wisski_salz\Entity\Adapter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Url;
use Drupal\wisski_core\WisskiHelper;

/**
 * Class WisskiPathForm.
 *
 * Fom class for adding/editing WisskiPath config entities.
 */
class WisskiPathForm extends EntityForm {

  /**
   * The adapter engine.
   *
   * @var \Drupal\wisski_salz\EngineInterface
   */
  protected EngineInterface $engine;

  /**
   * The steps of the path.
   *
   * @var array
   */
  protected array $pathArray;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $wisski_pathbuilder = NULL) {

    // The form() function will not accept additional args,
    // but this function does
    // so we have to override this one to get hold of the pb id
    // load the pb entity this path currently is attached to
    // we found this out by the url we're coming from!
    $pb = WisskiPathbuilderEntity::load($wisski_pathbuilder);
    // dpm($pb,'before edit');
    // Load the adapter of the pb.
    $adapter = Adapter::load($pb->getAdapterId());

    // Load and register the engine.
    $this->engine = $adapter->getEngine();
    // dpm($this->pb, 'pb');
    // drupal_set_message('BUILD: ' . serialize($form_state));
    return parent::buildForm($form, $form_state, $wisski_pathbuilder);

  }

  /**
   *
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Return array();
    $path = $this->entity;
    // dpm(microtime(), "in");
    // The name for this path.
    $form['name'] = [
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('Name'),
      '#default_value' => empty($path->getName()) ? NULL : $path->getName(),
      '#attributes' => ['placeholder' => $this->t('Name for the path')],
      // '#description' => $this->t("Name of the path."),
      '#required' => TRUE,
    ];

    // Automatically calculate a machine name based on the name field.
    $form['id'] = [
      '#type' => 'machine_name',
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#default_value' => $path->getID(),
      '#disabled' => !$path->isNew(),
      '#machine_name' => [
        'source' => ['name'],
        'exists' => 'wisski_pathbuilder_path_load',
      ],
      '#required' => TRUE,
    ];

    // The name for this path.
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Path Type'),
      '#options' => ["Path" => "Path", "Group" => "Group", "SmartGroup" => "SmartGroup"],
      '#default_value' => $path->getType(),
      '#description' => $this->t("Is this Path a group?"),
    ];

    $cache_mode = FALSE;
    // dpm(microtime(), "in2");.
    if ($this->engine->providesCacheMode()) {
      $url = Url::fromRoute(
        'entity.wisski_salz_adapter.edit_form',
        ['wisski_salz_adapter' => $this->engine->adapterId()],
        ['fragment' => 'edit-reasoner']
      );
      $cache_info = [
        '#type' => 'details',
        'description' => [
          '#type' => 'item',
          '#markup' => $this->t('The connected adapter provides precomputation of domains and ranges.'),
        ],
        'link' => [
          '#type' => 'link',
          '#title' => $this->t('See the adapter\'s config page for details'),
          '#url' => $url,
        ],
      ];
      if ($this->engine->isCacheSet()) {
        $cache_mode = TRUE;
        $cache_info['#title'] = $this->t('Reasoner has run. Cache is prepared');
      }
      else {
        $cache_info['#title'] = $this->t('Reasoner has not run. No cache information available');
      }
      $form['cache_info'] = $cache_info;
    }

    if (!$cache_mode && $this->engine->providesFastMode()) {

      $fast_label = $this->t('Fast Mode');
      $fast_text = $this->t('Setting path alternative detection to %fast_mode will yield much faster loading time but may result in <b>incomplete option lists</b> in the respective select boxes', ['%fast_mode' => $fast_label]);
      $complete_label = $this->t('Complete Mode');
      $complete_text = $this->t('Setting path alternative detection to %complete_mode will yield the full list of options in the respective select boxes but may lead to <b>increased loading time</b>', ['%complete_mode' => $complete_label]);
      $form['mode_selection'] = [
        '#type' => 'details',
        '#title' => $this->t('Step alternative detection mode'),
        '#open' => TRUE,
      ];
      // The reasoning mode for step alternatives.
      $form['mode_selection']['fast_mode'] = [
        '#type' => 'radios',
        '#options' => [1 => $fast_label, 0 => $complete_label],
        '#default_value' => 0,
      ];

      // dpm($form['mode_selection']['fast_mode']);.
      $form['mode_selection']['fast_description'] = [
        '#type' => 'item',
        '#markup' => $fast_text,
      ];
      $form['mode_selection']['complete_description'] = [
        '#type' => 'item',
        '#markup' => $complete_text,
      ];
    }
    // dpm(microtime(), "in3");
    // first, set the default values.
    if (!isset($this->pathArray)) {
      $this->pathArray = $path->isNew() ? [] : $path->getPathArray();
    }
    $selected_row = 0;
    $fast_mode = FALSE;
    $consistent_change = FALSE;

    // dpm($this->path_array,'Before');
    // now let's see if someone triggered a change on those.
    if ($trigger = $form_state->getTriggeringElement()) {

      $input = $form_state->getUserInput();
      // dpm($input,'user input');.
      if (isset($input['fast_mode'])) {
        $fast_mode = $input['fast_mode'];
      }

      // All of the path_array elements have their respective row number and trigger type stored in attributes.
      $attributes = $trigger['#attributes'];
      // dpm($trigger,'Trigger');.
      $row_selection = $attributes['data-wisski-row'];
      switch ($attributes['data-wisski-trigger-type']) {
        case 'operations':{
          // dpm($input,$trigger['#name']);.
          $operation = $input[$trigger['#name']];
          $selected_row = -1;
          switch ($operation) {
            case 'cancel':
              break;

            case 'change': $selected_row = $row_selection;

              break;

            case 'consistent_change': $consistent_change = TRUE;
              $selected_row = $row_selection;

              break;

            case 'remove': $this->pathArray = WisskiHelper::array_remove_part($this->pathArray, $row_selection, 2);

              break;

            case 'insert': $this->pathArray = WisskiHelper::array_insert($this->pathArray, ['empty', 'empty'], $row_selection + 1);

              break;
          }
          break;
        }
        case 'select-box':{
          // User changed the entry in the selected row.
          $selection = $input[$trigger['#name']];
          $this->pathArray[$row_selection] = $selection;
          // Set the following step to 'change' mode.
          $selected_row = $row_selection + 1;
        }
      }

    }
    // Return $form;.
    $last_row = count($this->pathArray) - 1;
    // dpm($this->path_array,'After');
    // dpm(microtime(), "in4");.
    $form['path_content'] = [
      '#type' => 'container',
      '#prefix' => '<div id=wisski-path-content>',
      '#suffix' => '</div>',
    ];

    $form['path_content']['path_array'] = [
      '#type' => 'table',
      '#header' => ['step' => $this->t('Step'), 'ops' => $this->t('Edit')],
    ];
    // dpm($this->path_array);
    // return $form;.
    for ($current_row = 0; $current_row <= count($this->pathArray); $current_row++) {

      if (isset($this->pathArray[$current_row]) && $this->pathArray[$current_row] != 'empty') {
        $path_element = $this->pathArray[$current_row];
        $element_options = [$path_element => $path_element];
      }
      else {
        $path_element = 'empty';
        $element_options = [];
      }

      $is_current = $current_row === $selected_row;
      if ($is_current) {
        $history = array_slice($this->pathArray, 0, $current_row);
        $future = $consistent_change ? array_slice($this->pathArray, $current_row + 1) : [];
        $element_options = $this->engine->getPathAlternatives($history, $future, $fast_mode);
        // dpm($element_options,'options');
        // dpm($future, "fm");.
      }

      // If the engine has no ontology, it currently returns false which is evil as options.
      if ($element_options === FALSE) {
        $this->messenger()->addError($this->t("No path options for this path could be evaluated. Probably the ontology is missing in your store!"));
        $element_options = [];
      }

      $form_path_elem['select_box'] = [
        '#type' => 'select',
        '#name' => 'select_box_' . $current_row,
        '#options' => $element_options,
        '#empty_value' => 'empty',
        '#empty_option' => $this->t('please select'),
        '#disabled' => !$is_current,
        '#default_value' => 'empty',
        '#value' => $path_element,
        '#ajax' => [
          'wrapper' => 'wisski-path-content',
          'callback' => [$this, 'ajaxCallback'],
          'event' => 'change',
        ],
        '#attributes' => [
          'data-wisski-row' => $current_row,
          'data-wisski-trigger-type' => 'select-box',
        ],
      ];

      $operations = [];
      if ($is_current) {
        $operations['cancel'] = $this->t('Cancel Edit');
      }
      else {
        $operations['change'] = $this->t('Change');
        if (isset($this->pathArray[$current_row + 1]) && $this->pathArray[$current_row + 1] !== 'empty') {
          $operations['consistent_change'] = $this->t('Change and keep future');
        }
        if ($current_row < $last_row) {
          $operations['remove'] = $this->t('Remove this and next');
          $operations['insert'] = $this->t('Add two steps');
        }
        else {
          $operations['remove'] = $this->t('Remove');
        }
      }

      $form_path_elem['operations'] = [
        '#type' => 'select',
        '#options' => $operations,
        '#name' => 'operations_' . $current_row,
        '#empty_option' => '-',
        '#empty_value' => 'nop',
        '#limit_validation_errors' => [],
        '#ajax' => [
          'wrapper' => 'wisski-path-content',
          'callback' => [$this, 'ajaxCallback'],
        ],
        '#attributes' => [
          'data-wisski-row' => $current_row,
          'data-wisski-trigger-type' => 'operations',
        ],
      ];

      $form['path_content']['path_array'][$current_row] = $form_path_elem;

    }
    // dpm(microtime(), "in5");.
    if ($this->engine->providesDatatypeProperty() && !empty($this->pathArray[$last_row]) && $this->pathArray[$last_row] !== 'empty') {
      // dpm(microtime(), "in5.1");.
      $options = $this->engine->getPrimitiveMapping($this->pathArray[$last_row]);
      // dpm(microtime(), "in5.2");.
      if (!empty($options)) {
        $form['path_content']['datatype_property'] = [
          '#type' => 'select',
          '#title' => $this->t('Datatype Property'),
          '#name' => 'datatype_property',
          '#options' => $options,
          '#empty_value' => 'empty',
          '#empty_option' => ' - ' . $this->t('select') . ' - ',
          '#default_value' => $path->getDatatypeProperty() ?: 'empty',
        ];
      }
    }
    // dpm(microtime(), "in6");.
    if (!empty($this->pathArray)) {
      $disamb_options = [];
      for ($i = 0; $i < count($this->pathArray); $i++) {
        $pos = floor($i / 2) + 1;
        if (($i % 2 === 0) && $this->pathArray[$i] !== 'empty') {
          $disamb_options[$pos] = $this->t('Concept ') . $pos . ': ' . $this->pathArray[$i];
        }
      }
      $form['path_content']['disamb'] = [
        '#type' => 'select',
        '#title' => $this->t('Disambiguation Point'),
        '#name' => 'disamb',
        '#options' => $disamb_options,
        '#empty_value' => 'empty',
        '#empty_option' => ' - ' . $this->t('select') . ' - ',
        '#default_value' => $path->getDisamb() ?: 'empty',
      ];

      $form['path_content']['transitive'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Transitive'),
        '#default_value' => $path->getTransitive() ?: 0,
      ];

      $form['path_content']['irreflexive'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Irreflexive'),
        '#default_value' => $path->getIrreflexive() ?: 0,
      ];

    }
    // dpm(microtime(), "out");
    // dpm($form,'Form Array');.
    return $form;
  }

  /**
   *
   */
  public function ajaxCallback(array $form, FormStateInterface $form_state) {

    return $form['path_content'];
  }

  /**
   * {@inheritdoc}
   * overridden to ensure the correct mapping of form values to entity properties
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {

    $values = $form_state->getValues();

    // From parent, not sure what this is necessary for.
    if ($this->entity instanceof EntityWithPluginCollectionInterface) {
      // Do not manually update values represented by plugin collections.
      $values = array_diff_key($values, $this->entity->getPluginCollections());
    }

    $path_array = [];

    foreach ($values['path_array'] as $step) {
      $value = $step['select_box'];
      if ($value !== 'empty') {
        $path_array[] = $value;
      }
    }

    // dpm($path_array);
    $entity->setPathArray($path_array);
    // Some adapters do not support datatype_properties, so sometimes we have none set.
    if (isset($values['datatype_property'])) {
      $entity->setDatatypeProperty($values['datatype_property']);
    }
    $entity->setID($values['id']);
    $entity->setName($values['name']);
    $entity->setType($values['type']);
    $entity->setDisamb($values['disamb']);

    $entity->setTransitive($values['transitive']);
    $entity->setIrreflexive($values['irreflexive']);
    // dpm($entity,__FUNCTION__.'::path');.
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // parent::save($form,$form_state);
    // $pb = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilder::load($this->pb);.
    // dpm(array($this->entity,$this->pb),__METHOD__);.
    // drupal_set_message("I saved!");
    // return;.
    $path = $this->entity;

    $status = $path->save();
    // dpm($path,'Saved path');.
    if ($status) {
      // Setting the success message.
      $this->messenger()->addStatus($this->t('Saved the path: @id.', [
        '@id' => $path->getID(),
      ]));
    }
    else {
      $this->messenger()->addError($this->t('The path @id could not be saved.', [
        '@id' => $path->getID(),
      ]));
    }

    if (empty($this->pb)) {
      $pbid = $form_state->getBuildInfo()['args'][0];
    }
    else {
      $pbid = $this->pb;
    }

    // Load the pb.
    $pb = WisskiPathbuilderEntity::load($pbid);

    // Add the path to its tree if it was not there already.
    if (!$pb->hasPbPath($path->id())) {
      $pb->addPathToPathTree($path->id(), 0, $path->isGroup());
    }

    // Save the pb.
    $status = $pb->save();
    // dpm($pb,'after edit');.
    // $form_state->setRedirect('entity.wisski_pathbuilder.edit_form',array('wisski_pathbuilder' => $pbid));.
    $form_state->setRedirect('entity.wisski_pathbuilder.configure_field_form', ['wisski_pathbuilder' => $pbid, 'wisski_path' => $path->id()]);
  }

}
