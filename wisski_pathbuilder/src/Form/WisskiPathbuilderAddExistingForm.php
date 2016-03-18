<?php
/**
 * @file
 * Contains \Drupal\wisski_pathbuilder\Form\WisskiPathbuilderAddExistingForm
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
 * Class WisskiPathbuilderAddExistingForm
 * 
 * Fom class for adding/editing WisskiPath config entities.
 */
 
class WisskiPathbuilderAddExistingForm extends EntityForm {

   /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {    
 
    $form = parent::form($form, $form_state);
    
    // load all paths that are available
    $paths = entity_load_multiple('wisski_path');
 
    // make an options array for the dropdown
    $options = array();
    
    foreach($paths as $path) {
      $options[$path->getID()] = $path->getName();
    }
    
    $form['path'] = array(
      '#type' => 'select',
      '#title' => $this->t('Available paths to add'),
      '#options' => $options,
    );

    // thats it.
    
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    // which one should be added?    
    $value = $form_state->getValue('path');
    
    // get the pb it should be added to    
    $pb = $this->entity;

    // do it    
    $pb->addPathToPathTree($value);   
    
    // save it    
    $status = $pb->save();
    
    
#   $form_state->setRedirect('entity.wisski_pathbuilder.collection');
 }
}
    
 
