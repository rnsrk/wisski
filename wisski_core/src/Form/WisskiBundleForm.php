<?php

namespace Drupal\wisski_core\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;


class WisskiBundleForm extends EntityForm {
  
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\media_entity\MediaBundleInterface $bundle */
    $form['#entity'] = $bundle = $this->entity;

    if ($this->operation == 'add') {
      $form['#title'] = $this->t('Add bundle');
    }
    elseif ($this->operation == 'edit') {
      $form['#title'] = $this->t('Edit %label bundle', array('%label' => $bundle->label()));
    }

    $form['label'] = array(
      '#title' => t('Label'),
      '#type' => 'textfield',
      '#default_value' => $bundle->label(),
      '#description' => t('The human-readable name of this bundle.'),
      '#required' => TRUE,
      '#size' => 30,
    );

    // @todo: '#disabled' not always FALSE.
    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $bundle->id(),
      '#maxlength' => 32,
      '#disabled' => FALSE,
      '#machine_name' => array(
        'exists' => array('\Drupal\wisski_core\Entity\WisskiBundle', 'exists'),
        'source' => array('label'),
      ),
      '#description' => t('A unique machine-readable name for this bundle.'),
    );

    $form['description'] = array(
      '#title' => t('Description'),
      '#type' => 'textarea',
      '#default_value' => $bundle->get('description'),
      '#description' => t('Describe this bundle. The text will be displayed on the <em>Add new WisskiEntity</em> page.'),
    );
    dpm($bundle,__METHOD__);
    $form['title_pattern'] = array(
      '#title' => t('Title Pattern'),
      '#type' => 'textfield',
      '#description' => t('The pattern to create the entity titles from'),
      '#default_value' => $bundle->getTitlePattern(),
    );
    $options = '<table><tr><td>Field Name</td><td>Label</td></tr>';
    $field_definitions = \Drupal::entityManager()->getFieldStorageDefinitions('wisski_individual',$this->id);
//    dpm($field_definitions);
    foreach ($field_definitions as $field_name => $field_definition) {
      $options .= '<tr><td>'.$field_name.'</td><td>'.$field_definition->getLabel().'</td></tr>';
    }
    $options .= '</table>';
    $form['available_fields'] = array(
      '#markup' => 'Available Fields for Title Pattern:<br>'.$options,
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = t('Save bundle');
    $actions['delete']['#value'] = t('Delete bundle');
    $actions['delete']['#access'] = $this->entity->access('delete');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $pattern = $form_state->getValue('title_pattern');
    $bundle = $this->entity;
    if ($bundle->setTitlePattern($pattern) === FALSE) $form_state->setErrorByName('title_pattern','Invalid Title Pattern');
  }


  /**
   * {@inheritdoc}
   */
//  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
//    /** @var \Drupal\wisski_core\WisskiBundleInterface $entity */
//    parent::copyFormValuesToEntity($entity, $form, $form_state);
//    
//  }
  

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var  \Drupal\wisski_core\WisskiBundleInterface $bundle */
    $bundle = $this->entity;
    
    $status = $bundle->save();

    $t_args = array('%name' => $bundle->label());
    if ($status == SAVED_UPDATED) {
      drupal_set_message(t('The bundle %name has been updated.', $t_args));
    }
    elseif ($status == SAVED_NEW) {
      drupal_set_message(t('The bundle %name has been added.', $t_args));
    }

    $form_state->setRedirectUrl($bundle->urlInfo('list'));
  }
  
}