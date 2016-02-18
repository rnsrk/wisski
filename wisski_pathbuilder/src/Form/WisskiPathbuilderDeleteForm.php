<?php
/**
 * @file
 * Contains \Drupal\wisski_pathbuilder\Form\WisskiPathbuilderDeleteForm.
 */
 
namespace Drupal\wisski_pathbuilder\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Url;

/**
 * Form that handles the removal of flower entities
 */
class WisskiPathbuilderDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete this pathbuilder: @id?',
    array('@id' => $this->entity->id));
  }
  
  /**
   * {@inheritdoc}
   */
  public function getCancelRoute() {
    return new Url('WisskiPathbuilder.list');
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
  public function submit(array $form, array &$form_state) {
    
    // Delete and set message
    $this->entity->delete();
    drupal_set_message($this->t('The pathbuilder @id has been deleted.',
    array('@id' => $this->entity->id)));
    $form_state['redirect_route'] = $this->getCancelRoute();
  }

}