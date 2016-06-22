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
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
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
      '#default_value' => empty($path->getName()) ? NULL : $path->getName(),
      '#attributes' => array('placeholder' => $this->t('Name for the path')),
      //'#description' => $this->t("Name of the path."),
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
      '#reequired' => TRUE,
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
      $datatype_property = $storage['datataype_property'];
      $trigger = $form_state->getTriggeringElement();
      //dpm($trigger,'Trigger');
      $matches = array();
      $did_match = preg_match('/^(\w+)(\d+)$/',$trigger['#attributes']['data-wisski'],$matches);
      if (!$did_match) {
        drupal_set_message($this->t('The trigger name didn\'t match','error'));
      } else {        
        list(,$trigger_type,$row_num) = $matches;
      }
      //dpm($paout,'before');
      if ($trigger_type === 'select') {
        $paout[$row_num] = $input['step:'.$row_num]['select'];
      }      
      if ($trigger_type === 'btn' && $paout[$row_num] !== 'empty') {
        $paout = \Drupal\wisski_core\WisskiHelper::array_insert($paout,array('empty','empty'),$row_num);
      }
      if ($trigger_type === 'del' && count($paout) > $row_num + 1) {
        $paout = \Drupal\wisski_core\WisskiHelper::array_remove_part($paout,$row_num,2);
      }
      if ($trigger_type === 'data') {
        $datatype_property = $input['datatype_property'];
      }
      //dpm($paout,'after');
      $existing_paths = $paout;
      $storage['existing_paths'] = $existing_paths;
      $storage['datatype_property'] = $datatype_property;
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
        //'#title' => $this->t('Step ' . $key . ': Select the next step of the path'),
        //'#title_display' => 'invisible',
        '#attributes' => array('data-wisski' => 'select'.$key),
        '#description' => $pre,
        '#ajax' => array(
          'callback' => 'Drupal\wisski_pathbuilder\Form\WisskiPathForm::ajaxPathData',
          'wrapper' => 'wisski-path-table',
          'event' => 'change', 
        ),
        '#limit_validation_errors' => array(),
      );
    
      if($i < count($curvalues) - 1 && !($i % 2)) {
        $form['path_data']['path_array']['step:'.$key]['op']['#type'] = 'actions';
        $form['path_data']['path_array']['step:'.$key]['op']['btn'] = array(
          //'#type' => 'submit',
          '#type' => 'button',
          '#value' => '+'.$key,
          '#attributes' => array('data-wisski' => 'btn'.$key),
          '#ajax' => array(
            'callback' => 'Drupal\wisski_pathbuilder\Form\WisskiPathForm::ajaxPathData',
            'wrapper' => 'wisski-path-table',
            'event' => 'click', 
          ),
          '#name' => 'btn'.$key,
          '#limit_validation_errors' => array(),
        );
        $form['path_data']['path_array']['step:'.$key]['op']['del'] = array(
          '#type' => 'button',
          '#value' => '-'.$key,
          '#attributes' => array('data-wisski' => 'del'.$key),
          '#ajax' => array(
            'callback' => 'Drupal\wisski_pathbuilder\Form\WisskiPathForm::ajaxPathData',
            'wrapper' => 'wisski-path-table',
            'event' => 'click', 
          ),
          '#name' => 'del'.$key,
          '#limit_validation_errors' => array(),
        );
      } else {
        $form['path_data']['path_array']['step:'.$key]['op'] = array(
          '#type' => 'hidden',
          '#title' => 'nop:'.$key
        );
      }
      
      
      
      $i++;
    }                         
    

    
    $primitive = array();

    // only act if there is more than the dummy entry
    // and if it is not a property -> path length odd +1 for dummy -> even
    if(count($curvalues) > 1 && count($curvalues) % 2 == 0) {
      $primitive = $engine->getPrimitiveMapping($curvalues[(count($curvalues)-2)]);
    
      $form['path_data']['path_array']['datatype_property']['select'] = array(
        '#default_value' => 'empty',
        '#value' => isset($datatype_property) ? $datatype_property : $path->getDatatypeProperty(), #$this->t('Please select.'),
        '#type' => 'select',
        '#options' => array_merge(array('empty' => $this->t('Select datatype property')), $primitive),
        //'#title' => t('Please select the datatype property for the Path.'),
        '#ajax' => array(
          'callback' => 'Drupal\wisski_pathbuilder\Form\WisskiPathForm::ajaxPathData',
          'wrapper' => 'wisski-path-table',
          'event' => 'change', 
        ),
        '#attributes' => array('data-wisski' => 'data0'),
      );
    } else $form['path_data']['datatype_property']['select'] = array(
      '#type' => 'hidden',
      '#value' => 'empty',
    );
    $form['path_data']['path_array']['datataype_property']['op'] = array(
      '#type' => 'hidden',
      '#value' => 'op',
    );
    
    //dpm($form['path_data']['path_array'], 'formixxx000');
    
    return $form;
  }
  
  public function ajaxPathData(array $form, FormStateInterface $form_state) {
   
    return $form['path_data']['path_array'];
  }
  
  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    //parent::save($form,$form_state);
    //$pb = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilder::load($this->pb);
#    dpm(array($this->entity,$this->pb),__METHOD__);
    //$form_state->setRedirect('entity.wisski_pathbuilder.edit_form',array('wisski_pathbuilder' => $this->pb));

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
        
    if(empty($this->pb))
      $pbid = $form_state->getBuildInfo()['args'][0];
    else
      $pbid = $this->pb;
          
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
 
  /**
   * {@inheritdoc}
   * overridden to ensure the correct mapping of form values to entity properties
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    
    $values = $form_state->getValues();

    //From parent, not sure what this is necessary for
    if ($this->entity instanceof EntityWithPluginCollectionInterface) {
      // Do not manually update values represented by plugin collections.
      $values = array_diff_key($values, $this->entity->getPluginCollections());
    }

    $values = $form_state->getValues();
    //dpm($values,__METHOD__.'::values');
    $path_array = array();
    foreach ($values as $key => $value) {
      if (strpos($key,'step') === 0 && $value['select'] !== 'empty') {
        $row = explode(':',$key)[1];
        $path_array[$row] = $value['select'];
      }
    }
    $entity->setPathArray($path_array);
    //the $values do not accept the datatype_property value being named correctly, thus select is our desired goal
    $entity->setDatatypeProperty($values['select']);
    $entity->setID($values['id']);
    $entity->setName($values['name']);
    $entity->setType($values['type']);
    
    //dpm($entity,__FUNCTION__.'::path');
  }

}


