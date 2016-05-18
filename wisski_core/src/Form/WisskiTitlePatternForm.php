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
    
    $form_storage = $form_state->getStorage();
    $pattern = empty($form_storage['cached_pattern']) ? $bundle->getTitlePattern() : $form_storage['cached_pattern'];
/*
    $pattern['dummy_field'] = array(
      'name' => 'dummy_field',
      'weight' => 0,
      'optional' => FALSE,
      'type' => 'field',
    );
    $pattern['bummy_field'] = array(
      'name' => 'bummy_field',
      'weight' => 1,
      'optional' => TRUE,
      'type' => 'field',
    );
    $pattern['text2'] = array(
      'type' => 'text',
      'text' => '>>>',
      'weight' => 2,
    );
*/
    $count = count($pattern);

    $trigger = $form_state->getTriggeringElement();
    if ($trigger['#name'] === 'new-text-button') {
      $pattern['text'.$count] = array(
        'weight' => $count,
        'text' => '',
        'type' => 'text',
      );
    }
    if ($trigger['#name'] === 'field_select_box') {
      $selection = $form_state->getValue('field_select_box');
      if (!empty($selection) && $selection !== 'empty') {
        $pattern[$selection] = array(
          'type' => 'field',
          'name' => $selection,
          'weight' => $count,
          'optional' => TRUE,
        );
      } else drupal_set_message($this->t('Please choose a field to add'),'error');
    }
    
    $form_storage['cached_pattern'] = $pattern;
    $form_state->setStorage($form_storage);

    $header = array(
      '',
      $this->t('Content'),
      '',
      $this->t('Weight'),
      ''
    );

    $form['pattern'] = array(
      '#type' => 'table',
      '#theme' => 'table__menu_overview',
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
    $options = array();
    foreach ($available_fields as $field_name => $field_def) {
      $options[$field_name] = $field_def->getLabel().' ('.$field_name.')';
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
  
    $rendered = array();
  
    $rendered['#attributes']['class'][] = 'draggable';
      
    $rendered['id'] = array(
      '#type' => 'hidden',
      '#attributes' => array('class' => array('row-id')),
      '#value' => $key,
    );
    
    if ($attributes['type'] === 'field') {
      $rendered['field_name'] = array(
        '#markup' => $attributes['name'],
      );
      $rendered['optional'] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('optional'),
        '#title_display' => 'after',
        '#default_value' => $attributes['optional'],
      );
    }
    if ($attributes['type'] === 'text') {
      $rendered['text'] = array(
        '#type' => 'textfield',
        '#default_value' => $attributes['text'],
        '#title' => $this->t('Text'),
        '#title_display' => 'invisible',
      );
      $rendered['placeholder'] = array('#type' => 'hidden');
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
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = t('Save pattern');
    $actions['delete']['#value'] = t('Delete pattern');
    $actions['delete']['#access'] = $this->entity->access('edit');
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
  
}
