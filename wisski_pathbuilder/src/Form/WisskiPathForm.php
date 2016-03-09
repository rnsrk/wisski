<?php
/**
 * @file
 * Contains \Drupal\wisski_pathbuilder\Form\WisskiPathForm
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
 * Class WisskiPathForm
 * 
 * Fom class for adding/editing WisskiPath config entities.
 */
 
class WisskiPathForm extends EntityForm {

   /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
  
    $form = parent::form($form, $form_state);
    
    $path = $this->entity;
    
    // Change page title for the edit operation
    if($this->operation == 'edit') {
      $form['#title'] = $this->t('Edit Path: @id', array('@id' => $path->id));
    }
    
    $form['id'] = array(
      '#type' => 'machine_name',
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#default_value' => $path->id,
      '#disabled' => !$path->isNew(),
      '#machine_name' => array(
        'source' => array('name'),
        'exists' => 'wisski_path_load',
      ),
    );
    
    $form['name'] = array(
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('Name'),
      '#default_value' => $path->name,
      '#description' => $this->t("Name of the Path."),
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
    
    $path = $this->entity;
    
    $status = $path->save();
    
    if($status) {
      // Setting the success message.
      drupal_set_message($this->t('Saved the path: @id.', array(
        '@id' => $path->id,
      )));
    } else {
      drupal_set_message($this->t('The path @id could not be saved.', array(
        '@id' => $path->id,
      )));
    }
    
   /**
     $buildinfo = $form_state->getBuildInfo();
    # drupal_set_message(serialize($buildinfo));
      $args = $buildinfo['args'];
      drupal_set_message($args);
      // args[1] is the store name
      $store_name = $args[1];
      // args[0] is the store type name
      $wisski_pathbuilder = $args[0];
   */                      
    
    $wisski_pathbuilder = 'pb';
    // in d8 you have to redirect like this, if you have a slug like {wisski_pathbuilder} in the routing.yml file:
    #$url = \Drupal\Core\Url::fromRoute('entity.wisski_pathbuilder.overview')
    #                 ->setRouteParameters(array('wisski_pathbuilder'=>$wisski_pathbuilder));
    $url = \Drupal\Core\Url::fromRoute('entity.wisski_path.edit_form')
                         ->setRouteParameters(array('wisski_pathbuilder'=>$wisski_pathbuilder, 'wisski_path'=>$path->id));
                         
    $form_state->setRedirectUrl($url);             
 }
}
    
 