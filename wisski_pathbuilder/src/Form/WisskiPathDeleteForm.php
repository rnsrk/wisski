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
    $path = $this->entity;
    return $this->t('Are you sure you want to delete this path: @id?',
    array('@id' => $path->getID()));
  }
  
  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    # drupal_set_message(htmlentities(new Url('entity.wisski_pathbuilder.overview')));
    # return new Url('entity.wisski_pathbuilder.overview');
    #$pb_entities = entity_load_multiple('wisski_pathbuilder');
    $wisski_pathbuilder = 'pb';
    drupal_set_message($wisski_pathbuilder);                                     
    $url = \Drupal\Core\Url::fromRoute('entity.wisski_pathbuilder.overview')
                        ->setRouteParameters(array('wisski_pathbuilder'=>$wisski_pathbuilder));
    return $url;                       
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
    $path = $this->entity; 
    // Delete and set message
    $path->delete();
    drupal_set_message($this->t('The path @id has been deleted.',
    array('@id' => $path->getID())));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}