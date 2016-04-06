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
use Drupal\wisski_salz\EngineInterface;
use Drupal\wisski_pathbuilder\PathbuilderEngineInterface;

/**
 * Class WisskiPathForm
 * 
 * Fom class for adding/editing WisskiPath config entities.
 */
 
class WisskiPathForm extends EntityForm {
      

  protected $pb = NULL;
  
  /**
   * @{inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $wisski_pathbuilder = NULL) { 

    // the form() function will not accept additional args,
    // but this function does
    // so we have to override this one to get hold of the pb id
    $this->pb = $wisski_pathbuilder;
    return parent::buildForm($form, $form_state, $wisski_pathbuilder);
  }
    

   /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {    
     
    $form = parent::form($form, $form_state);
    
    // get the entity    
    $path = $this->entity;

    // do we have an engine for queries?
    $got_engine = FALSE;

    // load the pb entity this path currently is attached to 
    // we found this out by the url we're coming from!
    $pb = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::load($this->pb);

    // load the adapter of the pb
    $adapter = \Drupal\wisski_salz\Entity\Adapter::load($pb->getAdapter());

    // if there was an adapter
    if ($adapter) {
      // then we can get the engine
      $engine = $adapter->getEngine();    

      if ($engine) $got_engine = TRUE;
    } // else we should fail here I think.

    // Change page title for the edit operation
    if($this->operation == 'edit') {
      $form['#title'] = $this->t('Edit Path: @id', array('@id' => $path->getID()));
    }
                                                                                                            
    // the name for this path
    $form['name'] = array(
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('Name'),
      '#default_value' => empty($path->getName()) ? $this->t('Name for the path') : $path->getName(),
      '#description' => $this->t("Name of the path."),
      '#required' => true,
    );
    
    // automatically calculate a machine name based on the name field
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
    
    // the name for this path
    $form['type'] = array(
      '#type' => 'select',
      '#title' => $this->t('Path Type'),
      '#options' => array("Path" => "Path", "Group" => "Group", "SmartGroup" => "SmartGroup"),
      '#default_value' => $path->getType(),
      '#description' => $this->t("Is this Path a group?"),
    );
    
    // only ask for alternatives if there is an engine.
    if ($got_engine) {
      // you must set the options like this:
      $path_options = $engine->getPathAlternatives();
    }

    $form['path_array'] = array(
      '#type' => 'markup',
      '#tree' => TRUE,
     // The prefix/suffix provide the div that we're replacing, named by
     // #ajax['wrapper'] below.
     '#prefix' => '<div id="path_array_div">',
     '#suffix' => '</div>',
     '#value' => "",
      
    );

    // read the userinput
    #$input = $form_state->getUserInput();#
    
    $existing_paths = array();

    // if there was something in form_state - use that because it is likely more accurate
    if(empty($form_state->getValue('path_array'))) {
      if(!empty( $path->getPathArray() ))
        $existing_paths = $path->getPathArray();
    } else 
      $existing_paths = $form_state->getValue('path_array');

    

    // if there is no new field create one
    if(array_search("0", $existing_paths) === FALSE)
      $existing_paths[] = "0";

    $curvalues = $existing_paths;
  
    // go through all values and create fields for them
    foreach($curvalues as $key => $element) {
   
      if(!empty($curvalues[($key-1)])) {

       // function getPathAlternatives takes as paramter an array of the previous steps 
       // or an empty array if this is the beginning of the path.        
        $path_options = $engine->getPathAlternatives(array($curvalues[($key-1)]));
      } else {
        $path_options = $engine->getPathAlternatives();
      }    
                  
      $form['path_array'][$key] = array(
        '#default_value' => $element,
   # '#key_type' => 'associative',
   # '#multiple_toggle' => '1',
        '#type' => 'select',
        '#options' => array_merge(array("0" => 'Please select.'), $path_options),
        '#title' => t('Step ' . $key . ': Select the next step of the path'),
        '#ajax' => array(
          'callback' => 'Drupal\wisski_pathbuilder\Form\WisskiPathForm::ajaxPathData',
          'wrapper' => 'path_array_div',
#          'effect' => 'slide',        
          'event' => 'change', 
        ),
      );    
    }
    
    return $form;
  }
  
/**
  * Ajax callback to render a sample of the input path data.
  *
  * @param array $form
  *   Form API array structure.
  * @param array $form_state
  *   Form state information.
  *
  * @return AjaxResponse
  *   Ajax replace command with the rendered sample date using the given
  *   format. If the given format cannot be identified or was empty, the
  *   rendered sample date will be empty as well.
  */
  
  public function ajaxPathData(array $form, array $form_state) {
   # $value = \Drupal\Component\Utility\NestedArray::getValue(
    #  $form_state->getValues(),
     # $form_state->getTriggeringElement()['#array_parents']); 
   # drupal_set_message($form_state->getTriggeringElement()['#path_array']);  
   # $response = new AjaxResponse();
   # $response->addCommand(new ReplaceCommand('#edit-date-format-suffix', '<small id="edit-date-format-suffix">' . $format . '</small>'));
  #  return $response;
 # return $form['replace_textfield'];   
  #  if ($form_state->getValue('path_array')!='0') {
      #$selector = '#path_array_div';
      
     # $commands = array();
     # $commands[] = ajax_command_after($selector, "New 'after'...");
     # $commands[] = ajax_command_replace("#after_status", "<div id='after_status'>Updated after_command_example " . date('r') . "</div>");
       
     # return array('#type' => 'ajax', '#commands' => $commands);
    #  return $form['item']['path_array']['pathbuilder_add_select'];        
    #  drupal_set_message("ajax: " . serialize($form_state));
      return $form['path_array'];
   # }
  }
  
  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    
    $path = $this->entity;
    
    $patharray = $path->getPathArray();
        
    if($patharray[count($patharray) -1] == "0") {
      unset($patharray[count($patharray) -1]);
      $path->setPathArray($patharray);
    }
    
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
    
    // load the pb
    $pb = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::load($this->pb);
    
    // add the path to its tree
    $pb->addPathToPathTree($path->id());
    
    // save the pb
    $status = $pb->save();
    
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
   /*                      
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
    
    */
    # $wisski_pathbuilder = 'pb';
    // in d8 you have to redirect like this, if you have a slug like {wisski_pathbuilder} in the routing.yml file:
    #$url = \Drupal\Core\Url::fromRoute('entity.wisski_pathbuilder.overview')
    #                 ->setRouteParameters(array('wisski_pathbuilder'=>$wisski_pathbuilder));
    #$redirect_url = \Drupal\Core\Url::fromRoute('entity.wisski_path.edit_form')
    #                     ->setRouteParameters(array('wisski_pathbuilder'=>$wisski_pathbuilder, 'wisski_path'=>$path->getID()));
    $redirect_url = \Drupal\Core\Url::fromRoute('entity.wisski_pathbuilder.edit_form')
                          ->setRouteParameters(array('wisski_pathbuilder'=>$this->pb));
        
                         
    $form_state->setRedirectUrl($redirect_url);             
 }
}
    
 
