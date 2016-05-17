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
    $form = parent::form($form, $form_state);

    /** @var \Drupal\media_entity\MediaBundleInterface $bundle */
    $form['#entity'] = $bundle = $this->entity;

    $available_fields = \Drupal::entityManager()->getFieldDefinitions('wisski_individual',$bundle->id());
    dpm($available_fields,'available fields');
    
    $form['#title'] = $this->t('Edit title pattern for bundle %label', array('%label' => $bundle->label()));
    $cached_pattern = $form_state->getValue('cached_pattern');
    $pattern = empty($cached_pattern) ? $bundle->getTitlePattern() : $cached_pattern;

    $selection = $form_state->getValue('select');
    if (!empty($selection)) {
      $pattern[$selection] = array(
        'weight' => count($pattern),
        'required' => FALSE,
        'delimiter' => '',
      );
    }

    $form['cached_pattern'] = array('#value'=>$pattern);

    $form['pattern'] = array(
      '#type' => 'table',
      '#caption' => $this->t('Title Pattern'),
      '#header' => array($this->t('Field'),$this->t('Required'),$this->t('Delimiter')),
      '#empty' => $this->t('This bundle has not title pattern, yet'),
      '#prefix' => '<div id = "wisski-title-table">',
      '#suffix' => '</div>',
    );
    foreach ($pattern as $field_name => $field_requirements) {
      $form['pattern']['#rows'][$field_requirements['weight']] = array(
        'field_name' => $field_name,
        'required' => $field_requirements['required'],
        'delimiter' => $field_requirements['delimiter'],
      );
    }
    $options = array();
    foreach ($available_fields as $field_name => $field_def) {
      $options[$field_name] = $field_def->getLabel().' ('.$field_name.')';
    }
    $form['select'] = array(
      '#type' => 'select',
      '#options' => $options,
      '#caption' => $this->t('Add new field'),
      '#ajax' => array(
        'callback' => 'Drupal\wisski_core\Form\WisskiTitlePatternForm::onFieldSelection',
        'wrapper' => 'wisski-title-table'
      ),
    );
    
    return $form;
  }

  /**
   * AJAX response for Field Selection
   */
  public function onFieldSelection(array &$form, FormStateInterface $form_state) {
	dpm($form_state->getValue('cached_pattern'),'Cached Pattern');
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
    
    $status = $bundle->save();

    $t_args = array('%name' => $bundle->label());
    drupal_set_message(t('The title pattern for bundle %name has been updated.', $t_args));

    $form_state->setRedirectUrl($bundle->urlInfo('edit-form'));
  }
  
}