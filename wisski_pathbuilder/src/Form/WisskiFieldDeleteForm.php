<?php
/**
 * @file
 * Contains \Drupal\wisski_pathbuilder\Form\WisskiFieldDeleteForm.
 */
 
namespace Drupal\wisski_pathbuilder\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form that handles the removal of Wisski Path entities
 */
class WisskiFieldDeleteForm extends EntityConfirmFormBase {
  
  private $pb_id;
  private $field_id;
                                
  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    
    $this->pb_id = \Drupal::routeMatch()->getParameter('wisski_pathbuilder');
    $this->field_id = \Drupal::routeMatch()->getParameter('wisski_field_id');
    return $this->t('Do you want to delete the field @id associated with this path?',array('@id' => $this->field_id));
  }
  
  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    #drupal_set_message(htmlentities(new Url('entity.wisski_pathbuilder.overview')));
    #return new Url('entity.wisski_pathbuilder.overview');
    #$pb_entities = entity_load_multiple('wisski_pathbuilder');
    # $pb = 'pb';
    if (isset($this->pb_id)) {
      $url = \Drupal\Core\Url::fromRoute('entity.wisski_pathbuilder.edit_form',array('wisski_pathbuilder'=>$this->pb_id));
    } else {
      $url = \Drupal\Core\Url::fromRoute('entity.wisski_pathbuilder.collection');
    }
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

    $field_storages = \Drupal::entityManager()->getStorage('field_storage_config')->loadByProperties(
      array(
        'field_name' => $this->field_id,
        //'entity_type' => $mode,
      )
    );
        
    if (!empty($field_storages)) {
      foreach($field_storages as $field_storage) {
        $field_storage->delete();
      }
    }    
    
    drupal_set_message($this->t('The field with id @id has been deleted.',array('@id' => $this->field_id)));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}