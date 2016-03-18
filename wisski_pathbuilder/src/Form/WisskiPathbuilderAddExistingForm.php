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
    
    drupal_set_message(serialize($this->entity));
    
    #$path = $this->entity;

    $paths = entity_load_multiple('wisski_path');
 
    $options = array();
    
    foreach($paths as $path) {
      $options[$path->getID()] = $path->getName();
    }
    
    $form['path'] = array(
      '#type' => 'select',
      '#title' => $this->t('Available paths to add'),
      #'#default_value' => $path->getDatatypeProperty(),
      #'#description' => $this->t("Available datatype properties."),
      '#options' => $options,
       #'#required' => true,
    );
    /*
    // Change page title for the edit operation
    if($this->operation == 'edit') {
      $form['#title'] = $this->t('Edit Path: @id', array('@id' => $path->getID()));
    }
    
    $form['id'] = array(
      '#type' => 'machine_name',
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#default_value' => $path->getID(),
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
      '#default_value' => $path->getName(),
      '#description' => $this->t("Name of the path."),
      '#required' => true,
#      '#disabled' => !$pathbuilder->isNew(),
#      '#machine_name' => array(
#        'source' => array('name'),
#        'exists' => 'wisski_pathbuilder_load',
#      ),
    );
    
    $form['path_array'] = array(
      '#type' => 'select',
      '#title' => $this->t('Path'),
      '#default_value' => $path->getPathArray(),
      '#description' => $this->t("Select the next step of the path."),
      '#options' => [
        '0' => $this->t('Select one'),
        '1' => $this->t('ecrm:E39_Actor'),
     # '2' => [
      #  '2.1' => $this->t('Two point one'),
      #  '2.2' => $this->t('Two point two'),
     # ],
        '2' => $this->t('ecrm:P131_is_identified_by'),
        '3' => $this->t('ecrm:E82_Actor_Appellation'),
      ],    
      #'#required' => true,
    );
    
    $form['datatype_property'] = array(
      '#type' => 'select',
      '#title' => $this->t('Available datatype properties'),
      '#default_value' => $path->getDatatypeProperty(),
      #'#description' => $this->t("Available datatype properties."),
      '#options' => [
        '0' => $this->t('Select one'),
        '1' => $this->t('ecrm:P3_has_note'),
        '2' => $this->t('test:T1_has_testdatatype'),
      ],
       #'#required' => true,
      );
                                                                                                                                          
    $form['short_name'] = array(
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('Short Name'),
      '#default_value' => $path->getShortName(),
      '#description' => $this->t("The short name of the path."),
      #'#required' => true,
    );
    
    $form['description'] = array(
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('Description'),
      '#default_value' => $path->getDescription(),
      '#description' => $this->t("Description of this group or path."),
       #'#required' => true,
     );
     
    $form['disamb'] = array(
      '#type' => 'select',
      #'#description' => $this->t("Available datatype properties."),
      '#options' => [
        '0' => $this->t('no disambiguation'),
        '1' => $this->t('user disambiguation'),
        '2' => $this->t('ecrm:E35_Title'),
      ],                                      
      '#title' => $this->t('Disambiguation'),
      '#default_value' => $path->getDisamb(),
      '#description' => $this->t("Select the option for disambiguation."),
      #'#required' => true,
      
    );
     */                                                                                       
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
#    drupal_set_message(serialize($form_state)); 
    
    $value = $form_state->getValue('path');
    
    drupal_set_message(serialize($value));
    
    $pb = $this->entity;

    drupal_set_message(serialize($pb));
    
    $pb->addPathToPathTree($value);   
    
#    $pb->setName("Hans Kaspar");
    
    drupal_set_message(serialize($pb)); 

    $this->entity = $pb;
    
    $status = $pb->save();
    
    drupal_set_message(serialize($status));
    
    
    /*
    $path = $this->entity;
    
    $status = $path->save();
    
    if($status) {
      // Setting the success message.
      drupal_set_message($this->t('Saved the path: @id.', array(
        '@id' => $path->getID(),
      )));
    } else {
      drupal_set_message($this->t('The path @id could not be saved.', array(
        '@id' => $path->getID(),
      )));
    }
    

#     $buildinfo = $form_state->getBuildInfo();
#    # drupal_set_message(serialize($buildinfo));
#      $args = $buildinfo['args'];
#      drupal_set_message($args);
#      // args[1] is the store name
#      $store_name = $args[1];
#      // args[0] is the store type name
#      $wisski_pathbuilder = $args[0];

    $wisski_pathbuilder = '';
    // get the current internal path to search for the pathbuilder id component
    $url = Url::fromRoute('<current>');
    $internal_path = $url->getInternalPath();
    #drupal_set_message('Internal Path: ' . $internal_path);
    #$current_uri = \Drupal::request()->getRequestUri();
    #drupal_set_message('Current Uri: ' . $current_uri); 
    #$current_path = \Drupal::service('path.current')->getPath();
    #drupal_set_message('Current Path: ' . $current_path);
    
    // divide the path into its components (parts between slashes)
    $path_parts = explode('/', $internal_path);
    #drupal_set_message('Path parts: ' . serialize($path_parts));
 
    // iterate through the path parts array
    for($i = 0, $size = count($path_parts); $i < $size; ++$i) {
      // search for the component
      // that is two positions before the current component
      // and has the value 'wisski' 
      // and the component
      // that is one position before the current component
      // and has the value 'pathbuilder'
      if($path_parts[$i-2]=='wisski' && $path_parts[$i-1]=='pathbuilder'){
        // the current component that is one position after 'pathbuilder' 
        // equates to the pathbuilder id
        // so save the value as $wisski_pathbuilder 
        #drupal_set_message('pathbuilder is on pos ' . ($i-1));
        drupal_set_message('wisski pathbuilder id: ' . $path_parts[$i]);
        $wisski_pathbuilder = $path_parts[$i];
      }  
    } 
    
 
    # $wisski_pathbuilder = 'pb';
    // in d8 you have to redirect like this, if you have a slug like {wisski_pathbuilder} in the routing.yml file:
    #$url = \Drupal\Core\Url::fromRoute('entity.wisski_pathbuilder.overview')
    #                 ->setRouteParameters(array('wisski_pathbuilder'=>$wisski_pathbuilder));
    $redirect_url = \Drupal\Core\Url::fromRoute('entity.wisski_path.edit_form')
                         ->setRouteParameters(array('wisski_pathbuilder'=>$wisski_pathbuilder, 'wisski_path'=>$path->getID()));
                         
    $form_state->setRedirectUrl($redirect_url);             
 */
#   $form_state->setRedirect('entity.wisski_pathbuilder.collection');
 }
}
    
 
