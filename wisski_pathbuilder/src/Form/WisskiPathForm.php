<?php

/**
 * @file
 * Contains \Drupal\wisski_pathbuilder\Form\WisskiPathForm
 */
 
namespace Drupal\wisski_pathbuilder\Form;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface; 

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

use Drupal\Core\Url;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\wisski_salz\EngineInterface;
use Drupal\wisski_pathbuilder\PathbuilderEngineInterface;
use Drupal\wisski_core\WisskiHelper;

/**
 * Class WisskiPathForm
 * 
 * Fom class for adding/editing WisskiPath config entities.
 */
 
class WisskiPathForm extends EntityForm {
      

  protected $engine = NULL;
  protected $path_array = NULL;

  #public function getFormId() {
  #  return 'wisski_path_form';
  #}  
  /**
   * @{inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $wisski_pathbuilder = NULL) { 

    // the form() function will not accept additional args,
    // but this function does
    // so we have to override this one to get hold of the pb id
    // load the pb entity this path currently is attached to 
    // we found this out by the url we're coming from!
    $pb = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::load($wisski_pathbuilder);

    // load the adapter of the pb
    $adapter = \Drupal\wisski_salz\Entity\Adapter::load($pb->getAdapterId());
    
    // load and register the engine
    $this->engine = $adapter->getEngine();    

#    dpm($this->pb, 'pb');
#    drupal_set_message('BUILD: ' . serialize($form_state));
    return parent::buildForm($form, $form_state, $wisski_pathbuilder);
    
  }

  public function form(array $form, FormStateInterface $form_state) {
  
    $path = $this->entity;
        
    // the name for this path
    $form['name'] = array(
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('Name'),
      '#default_value' => empty($path->getName()) ? NULL : $path->getName(),
      '#attributes' => array('placeholder' => $this->t('Name for the path')),
      //'#description' => $this->t("Name of the path."),
      '#required' => true,
    );
    
    // automatically calculate a machine name based on the name field
    $form['id'] = array(
      '#type' => 'machine_name',
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#default_value' => $path->getID(),
      '#disabled' => !$path->isNew(),
      '#machine_name' => array(
        'source' => array('name'),
        'exists' => 'wisski_path_load',
      ),
      '#required' => TRUE,
    );
    
    // the name for this path
    $form['type'] = array(
      '#type' => 'select',
      '#title' => $this->t('Path Type'),
      '#options' => array("Path" => "Path", "Group" => "Group", "SmartGroup" => "SmartGroup"),
      '#default_value' => $path->getType(),
      '#description' => $this->t("Is this Path a group?"),
    );
    
    if ($this->engine->providesFastMode()) {
    
      $fast_label = $this->t('Fast Mode');
      $fast_text = $this->t('Setting path alternative detection to %fast_mode will yield much faster loading time but may result in <b>incomplete option lists</b> in the respective select boxes',array('%fast_mode' => $fast_label));
      $complete_label = $this->t('Complete Mode');
      $complete_text = $this->t('Setting path alternative detection to %complete_mode will yield the full list of options in the respective select boxes but may lead to <b>increased loading time</b>',array('%complete_mode' => $complete_label));
      $form['mode_selection'] = array(
        '#type' => 'details',
        '#title' => $this->t('Step alternative detection mode'),
        '#open' => TRUE,
      );
      //the reasoning mode for step alternatives
      $form['mode_selection']['fast_mode'] = array(
        '#type' => 'radios',
        '#options' => array(TRUE => $fast_label,FALSE => $complete_label),
        '#default_value' => TRUE,
      );
      $form['mode_selection']['fast_description'] = array(
        '#type' => 'item',
        '#markup' => $fast_text,
      );
      $form['mode_selection']['complete_description'] = array(
        '#type' => 'item',
        '#markup' => $complete_text,
      );
    }
    
    //first, set the default values
    if (!isset($this->path_array)) $this->path_array = $path->isNew() ? array() : $path->getPathArray();
    $selected_row = count($this->path_array);
    $fast_mode = FALSE;
    $consistent_change = FALSE;
    
    //dpm($this->path_array,'Before');
    
    //now let's see if someone triggered a change on those
    if ($trigger = $form_state->getTriggeringElement()) {
    
      $input = $form_state->getUserInput();
      //dpm($input,'user input');
      $fast_mode = $input['fast_mode'];
      //all of the path_array elements have their respective row number and trigger type stored in attributes
      $attributes = $trigger['#attributes'];
      //dpm($trigger,'Trigger');
      $row_selection = $attributes['data-wisski-row'];
      switch ($attributes['data-wisski-trigger-type']) {
        case 'operations': {
            //dpm($input,$trigger['#name']);
            $operation = $input[$trigger['#name']];
            $selected_row = -1;
            switch ($operation) {
              case 'cancel': break;
              case 'change': $selected_row = $row_selection; break;
              case 'consistent_change': $consistent_change = TRUE; $selected_row = $row_selection; break;
              case 'remove': $this->path_array = WisskiHelper::array_remove_part($this->path_array,$row_selection,2); break;
              case 'insert': $this->path_array = WisskiHelper::array_insert($this->path_array,array('empty','empty'),$row_selection+1); break;
            }
            break;
          }
        case 'select-box': {
            //user changed the entry in the selected row
            $selection = $input[$trigger['#name']];
            $this->path_array[$row_selection] = $selection;
            //set the following step to 'change' mode
            $selected_row = $row_selection + 1;
          }
      }
      
    }
    
    $last_row = count($this->path_array) - 1;
    //dpm($this->path_array,'After');
    
    $form['path_content'] = array(
      '#type' => 'container',
      '#prefix' => '<div id=wisski-path-content>',
      '#suffix' => '</div>',
    );
    
    $form['path_content']['path_array'] = array(
      '#type' => 'table',
      '#header' => array('step' => $this->t('Step'),'ops' => $this->t('Edit')),
    );
    
    for ($current_row = 0;$current_row <= count($this->path_array);$current_row++) {
      
      if (isset($this->path_array[$current_row]) && $this->path_array[$current_row] != 'empty') {
        $path_element = $this->path_array[$current_row];
        $element_options = array($path_element => $path_element);
      } else {
        $path_element = 'empty';
        $element_options = array();
      }
      $is_current = $current_row === $selected_row;
      if ($is_current) {
        $history = array_slice($this->path_array,0,$current_row);
        $future = $consistent_change ? array_slice($this->path_array,$current_row+1) : array();
        $element_options = $this->engine->getPathAlternatives($history,$future,$fast_mode); 
        //dpm($element_options,'options');
      }
      $form_path_elem['select_box'] = array(
        '#type' => 'select',
        '#name' => 'select_box_'.$current_row,
        '#options' => $element_options,
        '#empty_value' => 'empty',
        '#empty_option' => $this->t('please select'),
        '#disabled' => !$is_current,
        '#default_value' => 'empty',
        '#value' => $path_element,
        '#ajax' => array(
          'wrapper' => 'wisski-path-content',
          'callback' => array($this,'ajaxCallback'),
          'event' => 'change',
        ),
        '#attributes' => array(
          'data-wisski-row' => $current_row,
          'data-wisski-trigger-type' => 'select-box',
        ),
      );
      
      $operations = array();
      if ($is_current) $operations['cancel'] = $this->t('Cancel Edit');
      else {
        $operations['change'] = $this->t('Change');
        if (isset($this->path_array[$current_row+1]) && $this->path_array[$current_row+1] !== 'empty') $operations['consistent_change'] = $this->t('Change and keep future');
        if ($current_row < $last_row) {
          $operations['remove'] = $this->t('Remove this and next');
          $operations['insert'] = $this->t('Add two steps');
        }
        else $operations['remove'] = $this->t('Remove');
      }
      $form_path_elem['operations'] = array(
        '#type' => 'select',
        '#options' => $operations,
        '#name' => 'operations_'.$current_row,
        '#empty_option' => '-',
        '#empty_value' => 'nop',
        '#limit_validation_errors' => array(),
        '#ajax' => array(
          'wrapper' => 'wisski-path-content',
          'callback' => array($this,'ajaxCallback'),
        ),
        '#attributes' => array(
          'data-wisski-row' => $current_row,
          'data-wisski-trigger-type' => 'operations',
        ),
      );
      $form['path_content']['path_array'][$current_row] = $form_path_elem;
    }
    //dpm($path);
    if ($this->engine->providesDatatypeProperty() && !empty($this->path_array[$last_row]) && $this->path_array[$last_row] !== 'empty') {
      $options = $this->engine->getPrimitiveMapping($this->path_array[$last_row]);
      if (!empty($options)) {
        $form['path_content']['datatype_property'] = array(
          '#type' => 'select',
          '#title' => $this->t('Datatype Property'),
          '#name' => 'datatype_property',
          '#options' => $options,
          '#empty_value' => 'empty',
          '#empty_option' => ' - '.$this->t('select').' - ',
          '#default_value' => $path->getDatatypeProperty() ?:'empty',
        );
      }
    }
    
    if (!empty($this->path_array)) {
      $disamb_options = array();
      for($i = 0;$i<count($this->path_array);$i++) {
        if (!($i % 2) && $this->path_array[$i] !== 'empty') $disamb_options[$i] = $this->t('Step ').$i.': '.$this->path_array[$i];
      }
      $form['path_content']['disamb'] = array(
        '#type' => 'select',
        '#title' => $this->t('Disambiguation Point'),
        '#name' => 'disamb',
        '#options' => $disamb_options,
        '#empty_value' => 'empty',
        '#empty_option' => ' - '.$this->t('select').' - ',
        '#default_value' => $path->getDisamb() ?:'empty',
      );
    }
    
    //dpm($form,'Form Array');
    return $form;
  }

  public function ajaxCallback(array $form, FormStateInterface $form_state) {
    
    return $form['path_content'];
  }

  /**
   * {@inheritdoc}
   * overridden to ensure the correct mapping of form values to entity properties
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    
    $values = $form_state->getValues();

    //From parent, not sure what this is necessary for
    if ($this->entity instanceof EntityWithPluginCollectionInterface) {
      // Do not manually update values represented by plugin collections.
      $values = array_diff_key($values, $this->entity->getPluginCollections());
    }

    //dpm($values,__METHOD__.'::values');

    $path_array = array();
    
    foreach($values['path_array'] as $step) {
      $value = $step['select_box'];
      if ($value !== 'empty') $path_array[] = $value;
    }

#    dpm($path_array);
    $entity->setPathArray($path_array);
    //the $values do not accept the datatype_property value being named correctly, thus select is our desired goal
    $entity->setDatatypeProperty($values['datatype_property']);
    $entity->setID($values['id']);
    $entity->setName($values['name']);
    $entity->setType($values['type']);
    $entity->setDisamb($values['disamb']);
    //dpm($entity,__FUNCTION__.'::path');
  }


  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    //parent::save($form,$form_state);
    //$pb = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilder::load($this->pb);
#    dpm(array($this->entity,$this->pb),__METHOD__);

#    drupal_set_message("I saved!");
#    return;

    $path = $this->entity;
    
    $status = $path->save();
#dpm($path,'Saved path');    
    if($status) {
      // Setting the success message.
      drupal_set_message($this->t('Saved the path: @id.', array(
        '@id' => $path->getID(),
      )));
    } else {
      drupal_set_message($this->t('The path @id could not be saved.', array(
        '@id' => $path->getID(),
      )));
    }
        
    if(empty($this->pb))
      $pbid = $form_state->getBuildInfo()['args'][0];
    else
      $pbid = $this->pb;
          
    // load the pb
    $pb = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::load($pbid);
     
       
    // add the path to its tree if it was not there already
    if(is_null($pb->getPbPath($path->id())))
      $pb->addPathToPathTree($path->id(), 0, $path->isGroup());
      
    // save the pb
    $status = $pb->save();

    $form_state->setRedirect('entity.wisski_pathbuilder.edit_form',array('wisski_pathbuilder' => $pbid));
  }
 

}