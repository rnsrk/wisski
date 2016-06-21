<?php
/**
 * @file
 * Contains \Drupal\wisski_pathbuilder\Form\WisskiPathForm
 */
 
namespace Drupal\wisski_pathbuilder\Form;

use Drupal\Core\Form\FormState;
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

  #public function getFormId() {
  #  return 'wisski_path_form';
  #}  
  /**
   * @{inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $wisski_pathbuilder = NULL) { 

    // the form() function will not accept additional args,
    // but this function does
    // so we have to override this one to get hold of the pb id
    $this->pb = $wisski_pathbuilder;
#    dpm($this->pb, 'pb');
#    drupal_set_message('BUILD: ' . serialize($form_state));
    return parent::buildForm($form, $form_state, $wisski_pathbuilder);
    
  }
    
  /**
   * this seems to be necessary to prevent AJAX from firing twice
   */
  private $semaphore = FALSE;

   /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {    
    
#    drupal_set_message('FORM: ' . serialize($form_state)); 
/*
    $form = parent::form($form, $form_state);
    $twig = \Drupal::service('twig');
dpm($twig);
    $twig->enableDebug();
    $twig->enableAutoReload();
*/
//dpm($form,'Input Form');    
    // get the entity    
    $path = $this->entity;

    // do we have an engine for queries?
    $got_engine = FALSE;
    
#    dpm($this->pb, "pb in form: ");
    
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
    $form['path_data'] = array(
      '#type' => 'container',
      //'#tree' => TRUE,
    );
                                  
    // read the userinput
    #$input = $form_state->getUserInput();#
    
    $existing_paths = array();
    #drupal_set_message('val ' . serialize($form_state->getValues()));
    #drupal_set_message('path_data ' . serialize($form_state->getValue('path_data')));

    // if there was something in form_state - use that because it is likely more accurate
    #if(empty($form_state->getValue('path_data'))) {
    $input = $form_state->getUserInput();
    //dpm($input,'Input');
    
    if(empty($input)) {
      if(!empty( $path->getPathArray() ))
        $existing_paths = $path->getPathArray();
#      drupal_set_message('getPathArray: ' . serialize($existing_paths));
       
    } else {
      #$pa = $pd['path_array'];
      //$pa = $form_state->getValue('path_array');
      $storage = $form_state->getStorage();
      $paout = $storage['existing_paths'];

      $trigger = $input['_triggering_element_name'];
      $matches = array();
      $did_match = preg_match('/^step\:(\d+)\[(\w+)\]$/',$trigger,$matches);
      if (!$did_match) {
        drupal_set_message($this->t('The trigger name didn\'t match','error'));
      } else {
        //dpm(array('trigger'=>$trigger,'matches'=>$matches),'Triggered');
        list(,$row_num,$trigger_type) = $matches;
      }
      //dpm($paout,'before');
      if ($trigger_type === 'select') {
        $paout[$row_num] = $input['step:'.$row_num]['select'];
      }      
      
      if ($trigger_type === 'btn' && $row_num+1 < count($paout) && $paout[$row_num+1] !== 'empty') {
        $paout = \Drupal\wisski_core\WisskiHelper::array_insert($paout,array('empty','empty'),$path_key+1);
      }
      //dpm($paout,'after');
      $existing_paths = $paout;
      $storage['existing_paths'] = $existing_paths;
      $form_state->setStorage($storage);
#      drupal_set_message('pa: ' . serialize ($pa));     
    }
    #drupal_set_message("HI");
    #drupal_set_message('isRebuilding? ' . serialize($form_state->isRebuilding()));  
    #$form_state->setRebuild();
    
    if (end($existing_paths) !== 'empty') $existing_paths[] = 'empty';
    
#    drupal_set_message(serialize($existing_paths));

    $curvalues = $existing_paths;
//    dpm($curvalues, 'curvalues');

    $form['path_data']['#pathcount'] = count($curvalues);
    
    // count the steps as the last one doesn't need a button
    $i = 0; 
    
    $form['path_data']['path_array'] = array(
      '#type' => 'table',
      '#prefix' => '<div id="wisski-path-table">',
      '#suffix' => '</div>',
      '#header' => array('step' => $this->t('Step'),'op' => ''),
      '#tree' => FALSE,
    );
    
    // go through all values and create fields for them
    foreach($curvalues as $key => $element) {
      $form['path_data']['path_array']['step:'.$key] = array(
        '#type' => 'container',
        '#tree' => TRUE,
        '#attributes' => array('class' => 'wisski-row', 'id' => 'wisski-row-'.$key),
      );
#      drupal_set_message("key " . $key . ": element " . $element);
      if ($key > 0) {
        $pre = $curvalues[($key-1)] !== 'empty' ? array($curvalues[($key-1)]) : array();
        $succ = (isset($curvalues[($key+1)]) && $curvalues[($key+1)] !== 'empty') ? array($curvalues[($key+1)]) : array();
        $path_options = $engine->getPathAlternatives($pre,$succ);
      } else $path_options = $engine->getPathAlternatives();
      $form['path_data']['path_array']['step:'.$key]['select'] = array(
        '#default_value' => 'empty',
        '#value' => $element,
        '#type' => 'select',
        '#options' => array_merge(array('empty' => $this->t('Select next step')), $path_options),
        '#title' => $this->t('Step ' . $key . ': Select the next step of the path'),
        '#title_display' => 'invisible',
        '#description' => $pre,
        '#ajax' => array(
          'callback' => 'Drupal\wisski_pathbuilder\Form\WisskiPathForm::ajaxPathData',
          'wrapper' => 'wisski-path-table',
          'event' => 'change', 
        ),
        '#limit_validation_errors' => array(),
      );
    
      if($i < count($curvalues) - 2 ) {
        
        $form['path_data']['path_array']['step:'.$key]['btn'] = array(
          //'#type' => 'submit',
          '#type' => 'button',
          '#value' => '+'.$key,
          '#name' => 'step:'.$key.'[btn]',
          '#ajax' => array(
            'callback' => 'Drupal\wisski_pathbuilder\Form\WisskiPathForm::ajaxPathData',
            'wrapper' => 'wisski-path-table',
            'event' => 'click', 
          ),
          '#limit_validation_errors' => array(),
        );
      } else $form['path_data']['path_array']['step:'.$key]['btn'] = array(
        '#type' => 'hidden',
        '#title' => 'nop:'.$key
      );
      $i++;
    }                         
    

    
    $primitive = array();

    // only act if there is more than the dummy entry
    // and if it is not a property -> path length odd +1 for dummy -> even
    if(count($curvalues) > 1 && count($curvalues) % 2 == 0)
      $primitive = $engine->getPrimitiveMapping($curvalues[(count($curvalues)-2)]);
    
    $form['path_data']['path_array']['datatype_property'] = array(
      '#default_value' => isset($datatype_property) ? $datatype_property : $path->getDatatypeProperty(), #$this->t('Please select.'),
      '#type' => 'select',
      '#options' => array_merge(array("0" => 'Please select.'), $primitive),
      '#title' => t('Please select the datatype property for the Path.'),
    );
    
    $form['test_button'] = array(
      '#type' => 'button',
      '#value' => 'Click',
      '#ajax' => array(
        'wrapper' => 'wisski-path-table',
        'callback' => '\Drupal\wisski_pathbuilder\Form\WisskiPathForm::ajaxPathData',
      ),
    );

    //dpm($form['path_data']['path_array'], 'formixxx000');
    
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
  
  public function ajaxPathData(array $form, FormStateInterface $form_state) {
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
       #$form_state->setRebuild();
    //dpm($form,'AJAX says');
    return $form['path_data']['path_array'];
  }
  
 public function submitAddPathField(array $form, FormStateInterface $form_state) {
    dpm($form_state, "submit"); 
    
    dpm($form_state->getTriggeringElement(), "trigger");

    $triggerid = $form_state->getTriggeringElement()['#attributes']['data'][0];
    
    dpm($triggerid);
    
    $values = $form_state->getValues();
    
    $newpa = array();
    
    foreach($values['path_array'] as $key => $value) {
      foreach($value as $subkey => $subvalue) {
        // skip buttons
        if(strpos($subkey, 'button_') !== FALSE)
          continue;
        
        // just copy
        if($subkey < $triggerid) {
          $newpa[$key][$subkey] = $subvalue;
        }
        // we have to add something before that
        if($key == $triggerid) {
          $newpa[$key][$subkey] = "0"; 
          $newpa[$key+1][$subkey+1] = "0"; 
          $newpa[$key+2][$subkey+2] = $subvalue;
        }
        
        if($key > $triggerid) {
          $newpa[$key+2][$subkey+2] = $subvalue; 
        }
        
      }
    }

    $form_state->setValue('path_array', $newpa);
    
    dpm($form_state->getValues(), "values");
    
    $form_state->setRebuild();
 }
  
#  public function ajaxAddPathField($form, $form_state) {    
/*
    drupal_set_message("HELLO");
    #$selector = '#path_array_div';
     
    #$commands = array();
    #$commands[] = ajax_command_append($selector, "Stuff...");
    #return array('#type' => 'ajax', '#commands' => $commands);
    
         
    $existing_paths = $form_state->getValue('path_array');
    $existing_paths_complete = $existing_paths;
    $complete_form = $form_state->getCompleteForm(); 
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#parents'];
    $trigger_element = $parents[0];
    drupal_set_message('parents ' . serialize($parents));
    #$existing_paths[$trigger_element+1] = "HI";
    $existing_paths_part = array_splice($existing_paths, $trigger_element+1);
    $existing_paths[] = "0";
    #$existing_paths[] = "0";
    
    #drupal_set_message(serialize($form_state));
    drupal_set_message('existing_paths: ' . serialize($existing_paths));
    drupal_set_message('existing_paths_part: ' . serialize($existing_paths_part));
    drupal_set_message('existing_paths_complete: ' . serialize($existing_paths_complete));
    $existing_paths_new = array_merge($existing_paths, $existing_paths_part);
    drupal_set_message('existing_paths_new: ' . serialize($existing_paths_new));
    $form_state->setValue('path_array', $existing_paths_new);
    drupal_set_message('form state path array' . serialize($form_state->getValue('path_array'))); 
    #drupal_set_message('complete_form: ' . serialize($complete_form));
    #drupal_set_message('path_data: ' . serialize($complete_form['path_data']));
    #drupal_set_message('trigger ' . serialize($form_state->getTriggeringElement()));  
    #dpm($complete_form['path_data']);
    #dpm($form_state->getTriggeringElement());
    #drupal_set_message('isRebuild? ' . serialize($form_state->isRebuilding()));
    #$form_state->setRebuild();
    #$form = \Drupal::formBuilder()->rebuildForm('wisski_path_form', $form_state);
    $form = \Drupal::formBuilder()->rebuildForm('Drupal\wisski_pathbuilder\Form\WisskiPathForm', $form_state);
    #drupal_set_message('FORMBUILDER: ' . \Drupal::formBuilder());
    #drupal_set_message('isRebuild now? ' . serialize($form_state->isRebuilding()));
    return $form['path_data'];
    #$form_state->setRebuild();
*/
#  }
  
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
    
    if(empty($this->pb))
      $pbid = $form_state->getBuildInfo()['args'][0];
    else
      $pbid = $this->pb;
 
    dpm($pbid, "pbid");
      
    // load the pb
    $pb = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::load($pbid);

    
    // add the path to its tree if it was not there already
    if(is_null($pb->getPbPath($path->id())))
      $pb->addPathToPathTree($path->id(), 0, $path->isGroup());
    
    // save the pb
    $status = $pb->save();

    $redirect_url = \Drupal\Core\Url::fromRoute('entity.wisski_pathbuilder.edit_form')
                          ->setRouteParameters(array('wisski_pathbuilder'=>$pbid));
        
                         
    $form_state->setRedirectUrl($redirect_url);             
 }
}
    
 
