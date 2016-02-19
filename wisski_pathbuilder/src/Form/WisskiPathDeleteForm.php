<?php
/**
 * @file
 * Contains \Drupal\wisski_pathbuilder\Form\WisskiPathDeleteForm.
 */
 
namespace Drupal\wisski_pathbuilder\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form that handles the removal of Wisski Path entities
 */
class WisskiPathDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete this path: @id?',
    array('@id' => $this->entity->id));
  }
  
  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    drupal_set_message(htmlentities(new Url('entity.wisski_path.collection')));
    return new Url('entity.wisski_path.collection');
  }
  
  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
     
    // Delete and set message
    $this->entity->delete();
    drupal_set_message($this->t('The path @id has been deleted.',
    array('@id' => $this->entity->id)));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}