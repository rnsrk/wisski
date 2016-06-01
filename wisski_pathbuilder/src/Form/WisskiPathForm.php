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
    $adapter = \Drupal\wisski_salz\Entity\Adapter::load($pb->getAdapterId());

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

    $form['path_data'] = array(
      '#type' => 'markup',
#      '#tree' => TRUE,
     // The prefix/suffix provide the div that we're replacing, named by
     // #ajax['wrapper'] below.
     '#prefix' => '<div id="path_array_div">',
     '#suffix' => '</div>',
     '#value' => "",
      
    );
    
    // preserve tree
    #$form['path_data']['path_array'] = array(
    #  '#type' => 'markup',
    #  '#tree' => TRUE,
    #  '#value' => "",
    #);

#    $form['path_data']['path_container'] = array(
 #     '#type' => 'container',
  #    '#attributes' => array(
   #   'class' => array('container-inline'),
    #  ),
   # );
                                   
    
    // preserve tree
    $form['path_data']['path_array'] = array(
      '#type' => 'markup',
      '#tree' => TRUE,
      '#value' => "",
    );
                                  
    // read the userinput
    #$input = $form_state->getUserInput();#
    
    $existing_paths = array();
    #drupal_set_message('val ' . serialize($form_state->getValues()));
    #drupal_set_message('path_data ' . serialize($form_state->getValue('path_data')));

    // if there was something in form_state - use that because it is likely more accurate
    if(empty($form_state->getValue('path_data'))) {
      if(!empty( $path->getPathArray() ))
        $existing_paths = $path->getPathArray();
     # drupal_set_message('getPathArray: ' . serialize($existing_paths));
       
    } else {
      #$pa = $pd['path_array'];
      $pa = $form_state->getValue('path_array');
      $existing_paths = $pa;
     # drupal_set_message('pa:' . serialize ($pa));
     
    }
    
#    drupal_set_message(serialize($existing_paths));

    // if there is no new field create one
    if(array_search("0", $existing_paths) === FALSE)
      $existing_paths[] = "0";

    $curvalues = $existing_paths;
    drupal_set_message('curvalues: ' . serialize($curvalues));
    // go through all values and create fields for them
    foreach($curvalues as $key => $element) {
      drupal_set_message("key " . $key . ": element " . $element);
      if(!empty($curvalues[($key-1)])) {

       // function getPathAlternatives takes as parameter an array of the previous steps 
       // or an empty array if this is the beginning of the path.        
        $path_options = $engine->getPathAlternatives(array($curvalues[($key-1)]));
      } else {
        $path_options = $engine->getPathAlternatives();
      }    
                  
      $form['path_data']['path_array'][$key] = array(
        '#default_value' => $element,
        '#type' => 'select',
        '#options' => array_merge(array("0" => 'Please select.'), $path_options),
        '#title' => $this->t('Step ' . $key . ': Select the next step of the path'),
        #'#prefix' => '<div class="container-inline">',
        #'#prefix' => '<table border= \'0\'><tr><td>',
        #'#suffix' => '</td>',
        '#ajax' => array(
          'callback' => 'Drupal\wisski_pathbuilder\Form\WisskiPathForm::ajaxPathData',
          'wrapper' => 'path_array_div',
          'event' => 'change', 
        ),
      );
    
    
      $form['path_data']['add_path_field_submit'][$key] = array(
        '#type' => 'submit',
        '#value' => $this->t('+'),
        '#submit' => array('::submitAddPathField'),
        #'#prefix' => '<td>',
        #'#suffix' => '</td></tr></table><div class="clearfix"></div>',
        #'#suffix' => '</div>',
      );
    }                               
    #dpm($form['path_data']);
    
    $primitive = array();

    // only act if there is more than the dummy entry
    // and if it is not a property -> path length odd +1 for dummy -> even
    if(count($curvalues) > 1 && count($curvalues) % 2 == 0)
      $primitive = $engine->getPrimitiveMapping($curvalues[(count($curvalues)-2)]);
    
    $form['path_data']['datatype_property'] = array(
      '#default_value' => $path->getDatatypeProperty(), #$this->t('Please select.'),
      '#type' => 'select',
      '#options' => array_merge(array("0" => 'Please select.'), $primitive),
      '#title' => t('Please select the datatype property for the Path.'),
    );
    
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
      return $form['path_data'];
   # }
  }
  
  public function submitAddPathField(array &$form, FormStateInterface $form_state) {    
    $existing_paths = $form_state->getValue('path_array');
    $existing_paths_complete = $existing_paths;
    $complete_form = $form_state->getCompleteForm(); 
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#parents'];
    $trigger_element = $parents[0];
    drupal_set_message('parents ' . serialize($parents));
    #$existing_paths[$trigger_element+1] = "HI";
    $existing_paths_part = array_splice($existing_paths, $trigger_element+1);
    #$existing_paths[] = "0";
    #array_merge($existing_paths, $existing_paths_part); 
   # drupal_set_message(serialize($form_state));
   # drupal_set_message(serialize($path_data));
    drupal_set_message('existing_paths: ' . serialize($existing_paths));
    drupal_set_message('existing_paths_part: ' . serialize($existing_paths_part));
    drupal_set_message('existing_paths_complete: ' . serialize($existing_paths_complete));
    
    #drupal_set_message('complete_form: ' . serialize($complete_form));
    #drupal_set_message('path_data: ' . serialize($complete_form['path_data']));
    #drupal_set_message('trigger ' . serialize($form_state->getTriggeringElement()));  
    #dpm($complete_form['path_data']);
    dpm($form_state->getTriggeringElement());
    
    /*
    // Find out what was submitted.
    $values = $form_state->getValues();
    drupal_set_message(serialize($values));
    
    foreach ($values as $key => $value) {
      $label = isset($form[$key]['#title']) ? $form[$key]['#title'] : $key;
                    
      // Many arrays return 0 for unselected values so lets filter that out.
      if (is_array($value)) {
        $value = array_filter($value);
      }
      // Only display for controls that have titles and values.
      if ($value) {
        $display_value = is_array($value) ? print_r($value, 1) : $value;
        $message = $this->t('Value for %title: %value', ['%title' => $label, '%value' => $display_value]);
        drupal_set_message($message);
      }
    }
    */                                                                                        
    $form_state->setRebuild();
  }
  
  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    
    $path = $this->entity;
    
    $patharray = $path->getPathArray();

    // unset the last step because this usually is an empty field for selection        
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
    
    // add the path to its tree if it was not there already
    if(is_null($pb->getPbPath($path->id())))
      $pb->addPathToPathTree($path->id(), 0, $path->isGroup());
    
    // save the pb
    $status = $pb->save();

    $redirect_url = \Drupal\Core\Url::fromRoute('entity.wisski_pathbuilder.edit_form')
                          ->setRouteParameters(array('wisski_pathbuilder'=>$this->pb));
        
                         
    $form_state->setRedirectUrl($redirect_url);             
 }
}
    
 
