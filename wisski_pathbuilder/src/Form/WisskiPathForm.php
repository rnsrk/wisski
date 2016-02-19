<?php
/**
 * @file
 * Contains \Drupal\wisski_pathbuilder\Form\WisskiPathbuilderForm
 */
 
namespace Drupal\wisski_pathbuilder\Form;

use Drupal\Core\Form\FormStateInterface; 
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Url;

/**
 * Class WisskiPathbuilderForm
 * 
 * Fom class for adding/editing WisskiPathbuilder config entities.
 */
 
class WisskiPathbuilderForm extends EntityForm {

   /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
  
    $form = parent::form($form, $form_state);
    
    $pathbuilder = $this->entity;
    
    // Change page title for the edit operation
    if($this->operation == 'edit') {
      $form['#title'] = $this->t('Edit Pathbuilder: @id', array('@id' => $pathbuilder->id));
    }
    
    $form['id'] = array(
      '#type' => 'machine_name',
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#default_value' => $pathbuilder->id,
      '#disabled' => !$pathbuilder->isNew(),
      '#machine_name' => array(
        'source' => array('name'),
        'exists' => 'wisski_pathbuilder_load',
      ),
    );
    
    $form['name'] = array(
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('Name'),
      '#default_value' => $pathbuilder->name,
      '#description' => $this->t("Name of the Pathbuilder-Tree."),
      '#required' => true,
#      '#disabled' => !$pathbuilder->isNew(),
#      '#machine_name' => array(
#        'source' => array('name'),
#        'exists' => 'wisski_pathbuilder_load',
#      ),
    );
    
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    
    $pathbuilder = $this->entity;
    
    $status = $pathbuilder->save();
    
    if($status) {
      // Setting the success message.
      drupal_set_message($this->t('Saved the pathbuilder: @id.', array(
        '@id' => $pathbuilder->id,
      )));
    } else {
      drupal_set_message($this->t('The Pathbuilder @id could not be saved.', array(
        '@id' => $pathbuilder->id,
      )));
    }
    
    $url = new Url('entity.wisski_pathbuilder.collection');
    $form_state->setRedirect = $url->toString();
 }
}
    
 