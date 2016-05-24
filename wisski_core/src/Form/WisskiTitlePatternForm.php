<?php

namespace Drupal\wisski_core\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

use Drupal\Core\Ajax\AjaxResponse;

class WisskiTitlePatternForm extends EntityForm {
  
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
  
//    dpm(func_get_args(),__METHOD__);
    
    $form = parent::form($form, $form_state);
    
    /** @var \Drupal\media_entity\MediaBundleInterface $bundle */
    $form['#entity'] = $bundle = $this->entity;
    
    $available_fields = \Drupal::entityManager()->getFieldDefinitions('wisski_individual',$bundle->id());
    
    $form['#title'] = $this->t('Edit title pattern for bundle %label', array('%label' => $bundle->label()));
    
    $options = array();
    foreach ($available_fields as $field_name => $field_def) {
      $options[$field_name] = $field_def->getLabel().' ('.$field_name.')';
    }
    
    $form_storage = $form_state->getStorage();
    if (isset($form_storage['cached_pattern']) && !empty($form_storage['cached_pattern'])) {
      $pattern = $form_storage['cached_pattern'];
    } else {
      $pattern = $bundle->getTitlePattern();
    }

    $count = count($pattern);

    //if user added a new title element, find out the type and add a template with standard values
    $trigger = $form_state->getTriggeringElement();
    if ($trigger['#name'] === 'new-text-button') {
      $pattern['text'.$count] = array(
        'weight' => $count,
        'label' => '',
        'type' => 'text',
      );
    }
    if ($trigger['#name'] === 'field_select_box') {
      $selection = $form_state->getValue('field_select_box');
      if (!empty($selection) && $selection !== 'empty') {
        $pattern[$selection] = array(
          'type' => 'field',
          'name' => $selection,
          'label' => $available_fields[$selection]->getLabel(),
          'weight' => $count,
          'optional' => TRUE,
          'cardinality' => 1,
          'delimiter' => ', ',
        );
      } else {
        //this may not happen
        drupal_set_message($this->t('Please choose a field to add'),'error');
      }
    }
    
    $form_storage['cached_pattern'] = $pattern;
    $form_state->setStorage($form_storage);

    $header = array(
      '',
      $this->t('Type'),
      $this->t('Content'),
      $this->t('Options'),
      $this->t('Show #'),
      $this->t('Delimiter'),
      $this->t('Weight'),
      '',
      '',
    );

    $form['pattern'] = array(
      '#type' => 'table',
      //'#theme' => 'table__menu_overview',
      '#caption' => $this->t('Title Pattern'),
      '#header' => $header,
      '#empty' => $this->t('This bundle has no title pattern, yet'),
      '#attributes' => array('id' => 'wisski-title-table'),
      '#tabledrag' => array(
        // @TODO ! WATCH OUT we use the group and source names 'row-parent','row-id', and 'row-weight'
        // hard-coded in the buildRow function again
        array(
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'row-parent',
          'subgroup' => 'row-parent',
          'source' => 'row-id',
          'hidden' => TRUE,
          'limit' => 9,
        ),
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
    $form['field_select_box'] = array(
      '#type' => 'select',
      '#options' => array('empty'=>' - '.$this->t('None').' - ') + $options,
      '#title' => $this->t('Add another field'),
      '#ajax' => array(
        'callback' => 'Drupal\wisski_core\Form\WisskiTitlePatternForm::ajaxResponse',
        'wrapper' => 'wisski-title-table'
      ),
      //'#name' => 'field-select-box',
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
//    dpm($form,'after');
    return $form;
  }
  
  /**
   *
   */
  private function renderRow($key,array $attributes) {
    //dpm(func_get_args(),__METHOD__);  
    $rendered = array();
  
    $rendered['#attributes']['class'][] = 'draggable';
      
    $rendered['id'] = array(
      '#type' => 'hidden',
      '#attributes' => array('class' => array('row-id')),
      '#value' => $key,
    );
    $rendered['type'] = array(
      '#markup' => $attributes['type'],
    );
      
    if ($attributes['type'] === 'field') {
      $label = $key;
      if (isset($attributes['name'])) $label = $attributes['name'];
      $print_label = $label;
      if (isset($attributes['label'])) {
        $label = $attributes['label'];
        $print_label = $attributes['label'].' ('.$print_label.')';
      }
      $rendered['label'] = array(
        '#type' => 'item',
        '#markup' => $print_label,
        '#value' => $label,
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
        '#size' => 8,
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
      //make sure we have four cells filled
      foreach(array('optional','cardinality','delimiter') as $placeholder) {
        $rendered[$placeholder] = array('#type' => 'hidden');
      }
    }
    $rendered['weight'] = array(
      '#type' => 'weight',
      '#delta' => 51,
      '#attributes' => array('class' => array('row-weight')),
      '#default_value' => 0,
    );
    
    $rendered['#weight'] = $attributes['weight'];
    
    $rendered['parent'] = array(
      '#type' => 'hidden',
      '#attributes' => array('class' => array('row-parent')),
      '#value' => isset($attributes['parent'])? $attributes['parent'] : 0,
    );
    $rendered['type'] = array(
      '#type' => 'hidden',
      '#value' => $attributes['type'],
    );

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
      '#submit' => array("::deletePattern"),
    );
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var  \Drupal\wisski_core\WisskiBundleInterface $bundle */
    $bundle = $this->entity;
    
    $pattern = $form_state->getValue('pattern');

    $valid = $bundle->setTitlePattern($pattern);

    if ($valid) {
      $bundle->save();

      drupal_set_message(t('The title pattern for bundle %name has been updated.', array('%name' => $bundle->label())));

      $form_state->setRedirectUrl($bundle->urlInfo('edit-form'));
    }
  }
  
  public function deletePattern(array $form, FormStateInterface $form_state) {
    
    $bundle = $this->entity;
    $bundle->removeTitlePattern();
    $bundle->save();
    drupal_set_message(t('Removed title pattern for bundle %name.', array('%name' => $bundle->label())));
    $form_state->setRedirectUrl($bundle->urlInfo('edit-form'));
  }
  
}
