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
    
    $path = $this->entity;

    $got_engine = FALSE;
    
    $adapter = entity_load('wisski_salz_adapter', $this->pb);
    drupal_set_message($this->pb);
    drupal_set_message(serialize($adapter));
    if ($adapter) {
      // this is the engine that must implement the PathbuilderEngineInterface
      // currently the adapter must have the same machine name as the pb
      $engine = $adapter->getEngine();    
      drupal_set_message('engine: ' . serialize($engine));
      if ($engine) $got_engine = TRUE;
    }

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
    
    $path_options_first = array(
      '0' => $this->t('Select one'),
      'x0' =>  $this->t('ecrm:E1_CRM_Entity'),
      'x1' => $this->t('ecrm:E39_Actor'),
      'x2' => $this->t('ecrm:E82_Actor_Appellation'),
    );
    
    if ($got_engine) {
      // you must set the options like this:
      $path_options_first = $engine->getPathAlternatives();
      if ($form_state->getValue('path_array')) {
        $path_options = $engine->getPathAlternatives($form_state->getValue('path_array'));
        drupal_set_message('path options: ' . serialize($path_options));    
      }
    }
    
    // If we have a value for the first dropdown from $form_state['values'] we use
    // this both as the default value for the first dropdown and also as a
    // parameter to pass to the function that retrieves the options for the
    // second dropdown.
  #  $selected = (null !== $form_state->getValue('path_array')) ? $form_state->getValue('path_array') : key($path_options_first));                             
    
    $form['path_array'] = array(
      '#type' => 'select',
      '#title' => $this->t('Path'),
      #'#default_value' => $path->getPathArray(),
   #   '#default_value' => $selected,
      '#description' => $this->t("Select the next step of the path."),
      '#options' => $path_options_first,
     /** '#options' => [
        '0' => $this->t('Select one'),
        'x0' => $this->t('ecrm:E39_Actor'),
     # '2' => [
      #  '2.1' => $this->t('Two point one'),
      #  '2.2' => $this->t('Two point two'),
     # ],
        'y0' => $this->t('ecrm:P131_is_identified_by'),
        'x1' => $this->t('ecrm:E82_Actor_Appellation'),
      ],
      **/    
      #'#required' => true,
      '#ajax' => array(
        'callback' => 'Drupal\wisski_pathbuilder\Form\WisskiPathForm::ajaxPathData',
        'wrapper' => 'new_select_div',
        'effect' => 'slide',
        #'event' => 'keyup',
        #'progress' => array(
         # 'type' => 'throbber', 
          #'message' => NULL
        #),
      ),     
    );
    

               
     
 #  if ($form_state->getValue('path_array')!='0') {
      drupal_set_message(t("The value of path is not empty: '@value'", array('@value' => $form_state->getValue('path_array'))));
   // This entire form element will be replaced whenever 'changethis' is updated.
   $form['new_select'] = array(
     '#type' => 'select',
     '#title' => t("Select step 2 of the path"),
     // The prefix/suffix provide the div that we're replacing, named by
     // #ajax['wrapper'] above.
     '#prefix' => '<div id="new_select_div">',
     '#suffix' => '</div>',
     '#options' => $path_options,
   );
   
#  } 
    // In d8 the newly added FormStateInterface is used instead of an array for $form_state,
    // use the appropriate function instead, in this case $form_state->getValue().
    // Look at https://www.drupal.org/node/2310411 for more details
     # $wisski_core_config->set('wisski_core_lod_uri_templates', array('' => $base_url .  '/' . $form_state->getValue('template')));
     
                    
   // An AJAX request calls the form builder function for every change.
   // We can change how we build the form based on $form_state.
   #if (!empty($form_state['values']['path_array'])) {   
  # if (!empty($form_state->getValue('path_array'))) {
   #  $form['replace_textfield']['#description'] = t("Say why you chose '@value'", array('@value' => $form_state->getValue('path_array')));
   #} 
  /**  
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
  **/                                                                                          
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
     
      return $form['new_select'];
   # }
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
        '@id' => $path->getID(),
      )));
    } else {
      drupal_set_message($this->t('The path @id could not be saved.', array(
        '@id' => $path->getID(),
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
 }
}
    
 
