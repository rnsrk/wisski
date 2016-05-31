<?php

namespace Drupal\wisski_core\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

use Drupal\Core\Ajax\AjaxResponse;

class WisskiTitlePatternForm extends EntityForm {

  private $path_options;
  
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
  
//    dpm(func_get_args(),__METHOD__);
    
    $form = parent::form($form, $form_state);
    
    /** @var \Drupal\media_entity\MediaBundleInterface $bundle */
    $form['#entity'] = $bundle = $this->entity;
    
    $form['#title'] = $this->t('Edit title pattern for bundle %label', array('%label' => $bundle->label()));

    $options = $this->getPathOptions($bundle->id());
    
    $form_storage = $form_state->getStorage();
    if (isset($form_storage['cached_pattern']) && !empty($form_storage['cached_pattern'])) {
      $pattern = $form_storage['cached_pattern'];
    } else {
      $pattern = $bundle->getTitlePattern();
    }

    $max_id = -1;
    if (isset($pattern['max_id'])) {
      $max_id = $pattern['max_id'];
      unset($pattern['max_id']);
    }
    $count = count($pattern)-1;

    //if user added or removed a new title element, find out the type and add a template with standard values
    $trigger = $form_state->getTriggeringElement();
    if (!is_null($trigger)) {
      $trigger = $trigger['#name'];
      dpm($trigger,'Trigger');
      if ($trigger === 'new-text-button') {
        $id = 't'.++$max_id;
        $pattern[$id] = array(
          'weight' => $count,
          'label' => '',
          'type' => 'text',
          'id' => $id,
          'parents' => '',
          'name' => 'text'.$id,
        );
      } elseif ($trigger === 'path_select_box') {
        $selection = $form_state->getValue('path_select_box');
        if (!empty($selection) && $selection !== 'empty') {
          if ($selection === 'uri') $label = 'URI';
          else {
            dpm($options,$selection);
            list($pb_id) = explode('.',$selection);
            $label = $options[$pb_id][$selection];
          }
          $id = 'p'.++$max_id;
          $pattern[$id] = array(
            'type' => 'path',
            'name' => $selection,
            'label' => $label,
            'weight' => $count,
            'optional' => TRUE,
            'cardinality' => 1,
            'delimiter' => ', ',
            'id' => $id,
            'parents' => '',
          );
        } else {
          //this may not happen
          drupal_set_message($this->t('Please choose a path to add'),'error');
        }
      } else {
        $xpl = explode(':',$trigger);
        if ($xpl[0] === 'remove' && isset($xpl[1])) {
          if (isset($pattern[$xpl[1]])) {
            $max_id--;
            unset($pattern[$xpl[1]]);
          }
        }
      }
    }

    $header = array(
      $this->t('ID'),
      $this->t('Content'),
      $this->t('Options'),
      $this->t('Show #'),
      $this->t('Delimiter'),
      $this->t('Dependencies'),
      $this->t('Weight'),
      '',
      '',
      '',
    );

    $form['pattern'] = array(
      '#type' => 'table',
      //'#theme' => 'table__menu_overview',
      '#caption' => $this->t('Title Pattern'),
      '#header' => $header,
      '#empty' => $this->t('This bundle has no title pattern, yet'),
      '#prefix' => '<div id=\'wisski-title-table\'>',
      '#suffix' => '</div>',
      '#tabledrag' => array(
        // @TODO ! WATCH OUT we use the group name 'row-weight'
        // hard-coded in the buildRow function again
        array(
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'row-weight',
        ),
      ),
    );
    foreach ($pattern as $key => $attributes) {
      $form['pattern'][$key] = $this->renderRow($key,$attributes);
    }
    $form['path_select_box'] = array(
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Add a path'),
      '#ajax' => array(
        'callback' => 'Drupal\wisski_core\Form\WisskiTitlePatternForm::ajaxResponse',
        'wrapper' => 'wisski-title-table'
      ),
    );
    $form['new_text'] = array(
      '#type' => 'button',
      '#value' => $this->t('Add a text block'),
      '#ajax' => array(
        'callback' => 'Drupal\wisski_core\Form\WisskiTitlePatternForm::ajaxResponse',
        'wrapper' => 'wisski-title-table'
      ),
      '#name' => 'new-text-button',
    );

    $pattern['max_id'] = $max_id;    
    $form_storage['cached_pattern'] = $pattern;
    $form_state->setStorage($form_storage);

    return $form;
  }
  
  /**
   *
   */
  private function renderRow($key,array $attributes) {
    //dpm($attributes,__METHOD__.' '.$key);  
    $rendered = array();
  
    $rendered['#attributes']['class'][] = 'draggable';
    
    $rendered['id'] = array(
      '#type' => 'item',
      '#value' => $attributes['id'],
      '#markup' => $attributes['id'],
      '#attributes' => array('class' => array('row-id')),
    );
    
    if ($attributes['type'] === 'path') {
      $rendered['label'] = array(
        '#type' => 'item',
        '#markup' => $attributes['label'],
        '#value' => $attributes['label'],
      );
      $rendered['optional'] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('optional'),
        '#title_display' => 'after',
        '#default_value' => $attributes['optional'],
      );
      static $cardinalities = array(1=>1,2=>2,3=>3,-1=>'all');
      $rendered['cardinality'] = array(
        '#type' => 'select',
        '#title' => $this->t('cardinality'),
        '#title_display' => 'invisible',
        '#options' => $cardinalities,
        '#default_value' => $attributes['cardinality'],
      );
      $rendered['delimiter'] = array(
        '#type' => 'textfield',
        '#size' => 4,
        '#title' => $this->t('delimiter'),
        '#title_display' => 'invisible',
        '#default_value' => isset($attributes['delimiter'])? $attributes['delimiter']: ', ',
      );
    }
    if ($attributes['type'] === 'text') {
      //put a text field here, so that fixed strings can be added to the title
      $rendered['label'] = array(
        '#type' => 'textfield',
        '#default_value' => $attributes['label'],
        '#title' => $this->t('Text'),
        '#title_display' => 'invisible',
      );
      //make sure we have all cells filled
      foreach(array('optional','cardinality','delimiter') as $placeholder) {
        $rendered[$placeholder] = array('#type' => 'hidden');
      }
    }
    
    $rendered['parents'] = array(
      '#type' => 'textfield',
      '#default_value' => $attributes['parents'],
      '#size' => 8,
    );
        
    $rendered['weight'] = array(
      '#type' => 'weight',
      '#delta' => 51,
      '#attributes' => array('class' => array('row-weight')),
      '#default_value' => 0,
    );
    
    $rendered['#weight'] = $attributes['weight'];
    
    $rendered['type'] = array(
      '#type' => 'hidden',
      '#value' => $attributes['type'],
      //'#markup' => $attributes['type'],
    );
    
    $rendered['name'] = array(
      '#type' => 'hidden',
      '#value' => $attributes['name'],
    );
    
    $rendered['remove_op'] = array(
      '#type' => 'button',
      '#name' => 'remove:'.$key,
      '#value' => 'remove',
      '#ajax' => array(
        'callback' => 'Drupal\wisski_core\Form\WisskiTitlePatternForm::ajaxResponse',
        'wrapper' => 'wisski-title-table'
      ),
      '#limit_validation_errors' => array(),
    );
//    dpm(array('attributes'=>$attributes,'result'=>$rendered),__METHOD__);    
    return $rendered;
  }

  /**
   * AJAX response for Field Selection
   */
  public function ajaxResponse(array &$form, FormStateInterface $form_state) {

	  //dpm($form_state->getStorage()['cached_pattern'],'Cached Pattern');
  	return $form['pattern'];
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save pattern'),
      '#submit' => array("::submitForm","::save"),
    );
    $actions['delete'] = array(
      '#value' => t('Delete pattern'),
      '#type' => 'submit',
      '#limit_validation_errors' => array(),
      '#submit' => array("::deletePattern"),
    );
    return $actions;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {

    $pattern = $form_state->getValue('pattern');
    dpm($pattern);
    $max_id = 0;

    $errors = array();
    if (isset($title_pattern['max_id'])) unset($title_pattern['max_id']);

    $children = array();
    
    foreach ($pattern as $row_id => &$attributes) {
      if (!isset($attributes['type'])) 
        $errors[] = array($row_id,'not set','type');
      elseif ($attributes['type'] === 'path') {
        if (empty($attributes['name'])) 
          $errors[] = array($row_id,'empty','name');
        elseif (!preg_match('/^[a-z0-9_]+\.[a-z0-9_]+|uri$/',$attributes['name'])) 
          $errors[] = array($row_id,'invalid','name');
        if (!in_array($attributes['cardinality'],array(-1,1,2,3))) 
          $errors[] = array($row_id.'][cardinality','invalid');
        if (empty($attributes['delimiter']))
          $errors[] = array($row_id.'][delimiter','empty');
      } elseif ($attributes['type'] === 'text') {
        if (empty($attributes['label'])) 
          $errors[] = array($row_id.'][label','empty');
      } else $errors[] = array($row_id,'invalid','type');
      
      if (isset($attributes['parents']) && $attributes['parents'] !== '') {
        $parents = explode(',',$attributes['parents']);
        foreach ($parents as $parent) {
          $t_parent = trim($parent);
          if (array_key_exists($t_parent,$pattern)) $children[$t_parent][] = $row_id;
          else $errors[] = array($row_id.'][parents','invalid');
        }
      }
      $num_id = intval(substr($attributes['id'],1));
      if ($num_id > $max_id) $max_id = $num_id;
    }
    $pattern['max_id'] = $max_id;

    foreach ($children as $row_id => $row_children) {
      $pattern[$row_id]['children'] = $row_children;
    }

    if (empty($errors)) {
      $form_state->setValue('pattern',$pattern);
    } else {
      foreach ($errors as $error_array) {
        dpm($error_array,'Errors');
        list($element,$error_type,$category) = $error_array;
        $t_error_type = $this->tError($error_type);
        $form_state->setErrorByName('pattern]['.$element,$t_error_type.' '.$category);
      }
    }
  }

  protected function tError($error_type) {
    
    switch ($error_type) {
      case 'invalid': return $this->t('Invalid');
      case 'not set': return $this->t('Not Set');
      case 'empty': return $this->t('Empty');
      case 'cyclic': return $this->t('Cyclic Dependency');
      default: return $this->t('Wrong');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    
    /** @var  \Drupal\wisski_core\WisskiBundleInterface $bundle */
    $bundle = $this->entity;
    $pattern = $form_state->getValue('pattern');
    
    $bundle->setTitlePattern($pattern);
    $bundle->save();
    
    drupal_set_message(t('The title pattern for bundle %name has been updated.', array('%name' => $bundle->label())));

    $form_state->setRedirectUrl($bundle->urlInfo('edit-form'));
  }
  
  public function deletePattern(array $form, FormStateInterface $form_state) {
    $form_state->setRedirectUrl($this->entity->urlInfo('delete-title-form'));
  }  
  
  private function getPathOptions($bundle_id) {
    
    $options = &$this->path_options;
    //if we already gathered the data, we can stop here
    if (!isset($options)) {
      $options['empty'] = ' - '.$this->t('select').' - ';
      $options['uri'] = 'URI';
      //find all paths from all active pathbuilders
      $pbs = \Drupal::entityManager()->getStorage('wisski_pathbuilder')->loadMultiple();
      $paths = array();
      foreach ($pbs as $pb_id => $pb) {
        $pb_paths = $pb->getAllPaths();
        foreach ($pb_paths as $path) {
          $path_id = $path->getID();
          if ($bundle_id === $pb->getBundle($path_id))
            $options[$pb_id][$pb_id.'.'.$path_id] = $path->getName();
        }
      }
    }
    //dpm(array('$bundle_id'=>$bundle_id,'result'=>$options),__METHOD__);
    return $options;
  }
}
