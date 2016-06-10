<?php
/**
 * @file
 * Contains \Drupal\wisski_pathbuilder\Form\WisskiPathbuilderConfigureFieldForm
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
 
class WisskiPathbuilderConfigureFieldForm extends EntityForm {

  
  protected $pathbuilder = NULL;
  protected $path = NULL;
  
  /**
   * @{inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $wisski_pathbuilder = NULL, $wisski_path = NULL) { 

    // the form() function will not accept additional args,
    // but this function does
    // so we have to override this one to get hold of the pb id
    $this->pathbuilder = $wisski_pathbuilder;
#    drupal_set_message(serialize($wisski_path));
    $this->path = $wisski_path;
    return parent::buildForm($form, $form_state, $wisski_pathbuilder, $wisski_path);
  }

   /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
  
    $form = parent::form($form, $form_state);

    $form['pathbuilder'] = array(
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('Pathbuilder'),
      '#default_value' => empty($this->pathbuilder->getName()) ? $this->t('Name for the pathbuilder') : $this->pathbuilder->getName(),
      '#disabled' => true,
      '#description' => $this->t("Name of the pathbuilder."),
      '#required' => true,
    );
        
    $form['path'] = array(
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('Path'),
      '#default_value' => empty($this->path) ? $this->t('Name for the pathbuilder') : $this->path,
      '#disabled' => true,
      '#description' => $this->t("Name of the path."),
      '#required' => true,
    );

    
#    drupal_set_message(serialize($this->pathbuilder->getPathTree()));
    
#    $tree = $this->pathbuilder->getPathTree();

#    $element = $this->recursive_find_element($tree, $this->path);
    $pbpath = $this->pathbuilder->getPbPath($this->path);
    $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($this->path);

    if($path->getType() != "Path") {
      $form['bundle'] = array(
        '#type' => 'textfield',
        '#maxlength' => 255,
        '#title' => $this->t('bundle'),
        '#default_value' => empty($pbpath['bundle']) ? '' : $pbpath['bundle'],
#      '#disabled' => true,
        '#description' => $this->t("Name of the bundle."),
        '#required' => true,
      );
    }
    
    if($path->getType() == "Path") {
      $form['field'] = array(
        '#type' => 'textfield',
        '#maxlength' => 255,
        '#title' => $this->t('Field'),
        '#default_value' => empty($pbpath['field']) ? '' : $pbpath['field'],
#      '#disabled' => true,
        '#description' => $this->t("ID of the mapped Field."),
        '#required' => true,
      );
      
      $formatter_types = \Drupal::service('plugin.manager.field.formatter')->getDefinitions();
      $widget_types = \Drupal::service('plugin.manager.field.widget')->getDefinitions();
      $field_types = \Drupal::service('plugin.manager.field.field_type')->getDefinitions();
      
      #drupal_set_message(serialize($widget_types));
      #drupal_set_message(serialize($formatter_types));
      
      #drupal_set_message(serialize($field_types));
      
      $listft = array();
      
      foreach($field_types as $key => $ft) {
        $listft[$key] = $ft['label'];
      }    
      
      $ftvalue = NULL;
      // check if we are in ajax-mode, then there is something in form-state
      $ftvalue = $form_state->getValue('fieldtype');
            
      // what is the current (default) value for the display of this field from
      // the database if there is nothing in form_state?
      if(empty($ftvalue))
        $ftvalue = empty($pbpath['fieldtype']) ? 'string' : $pbpath['fieldtype'];     

      // generate the displays depending on the selected fieldtype
      $listdisplay = array();
      foreach($widget_types as $wt) {
        if(in_array($ftvalue, $wt['field_types']))
          $listdisplay[$wt['id']] = $wt['label'];
      }

      // generate the formatters depending on the selected fieldtype
      $listform = array();
      foreach($formatter_types as $wt) {
        if(in_array($ftvalue, $wt['field_types'])) 
          $listform[$wt['id']] = $wt['label'];
      }
      
      // do something for ajax      
      $form['display'] = array(
        '#type' => 'markup',
        '#prefix' => '<div id="wisski_display">',
        '#suffix' => '</div>',
        '#value' => '',
        '#tree' => FALSE,
      );
      
      $form['display']['fieldtype'] = array(
        '#type' => 'select',
        '#maxlength' => 255,
        '#title' => $this->t('Type of the field that should be generated.'),
        '#default_value' => $ftvalue,
#      '#disabled' => true,
        '#options' => $listft,
        '#description' => $this->t("Type for the Field (Textfield, Image, ...)"),
        '#required' => true,
        '#ajax' => array(
          'callback' => 'Drupal\wisski_pathbuilder\Form\WisskiPathbuilderConfigureFieldForm::ajaxPathData',
          'wrapper' => 'wisski_display',
          'event' => 'change',
        ),
      );
        
      $form['display']['displaywidget'] = array(
        '#type' => 'select',
        '#maxlength' => 255,
        '#title' => $this->t('Type of form display for field'),
        '#default_value' => empty($pbpath['displaywidget']) ? '' : $pbpath['displaywidget'],
#      '#disabled' => true,
        '#options' => $listdisplay,
        '#description' => $this->t("Widget for the Field - If there is any."),
#        '#required' => true,
      );
       
      $form['display']['formatterwidget'] = array(
        '#type' => 'select',
        '#maxlength' => 255,
        '#title' => $this->t('Type of formatter for field'),
        '#default_value' => empty($pbpath['formatterwidget']) ? '' : $pbpath['formatterwidget'],
#      '#disabled' => true,
        '#options' => $listform,
        '#description' => $this->t("Formatter for the field - If there is any."),
#        '#required' => true,
      );
    }
    
#    drupal_set_message("ft: " . serialize($ftvalue) . " dis " . serialize($listdisplay) . " for " . serialize($listform));
    
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function ajaxPathData(array $form, array $form_state) {
    return $form['display'];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    // get the input of the field
    $field_name = $form_state->getValue('field');
    // get the input for the path
    $pathid = $form_state->getValue('path');
    
    #$bundle = $this->pathbuilder->getBundle($pathid); #$form_state->getValue('bundle');

    // load the path
    $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($pathid);
    
    // get the pbpaths
    $pbpaths = $this->pathbuilder->getPbPaths();
    // set the path and the bundle - beware: one is empty!
    $pbpaths[$pathid]['fieldtype'] = $form_state->getValue('fieldtype');
    $pbpaths[$pathid]['displaywidget'] = $form_state->getValue('displaywidget');
    $pbpaths[$pathid]['formatterwidget'] = $form_state->getValue('formatterwidget');
    
    // save it
    $this->pathbuilder->setPbPaths($pbpaths);
    $this->pathbuilder->save();
    
#    drupal_set_message(serialize($pbpaths[$pathid]));
    
#    drupal_set_message(serialize($this->pathbuilder->getPbPaths()));
    
    return;    
    // it is a field
    if(!$path->isGroup()) {
    # --- if it's a field ------------------

      $this->pathbuilder->generateFieldForPath($pathid, $field_name);
/*      
      // get the bundle for this pathid
      $bundle = $this->pathbuilder->getBundle($pathid); #$form_state->getValue('bundle');
      
      // this was called field?
      $field_storage_values = [
        'field_name' => $field_name,#$values['field_name'],
        'entity_type' => 'wisski_individual',
        'type' => 'text',//has to fit the field component type, see below
        'translatable' => TRUE,
      ];
    
      // this was called instance?
      $field_values = [
        'field_name' => $field_name,
        'entity_type' => 'wisski_individual',
        'bundle' => $bundle,
        'label' => $field_name,
        // Field translatability should be explicitly enabled by the users.
        'translatable' => FALSE,
        'disabled' => FALSE,
      ];
    

      // get the pbpaths
      $pbpaths = $this->pathbuilder->getPbPaths();
      // set the path and the bundle - beware: one is empty!
      $pbpaths[$this->path]['field'] = $field_name;
      $pbpaths[$this->path]['bundle'] = $bundle;
      // save it
      $this->pathbuilder->setPbPaths($pbpaths);
      $this->pathbuilder->save();
    
      // if the field is already there...
      if(empty($field_name) || !empty(\Drupal::entityManager()->getStorage('field_storage_config')->loadByProperties(array('field_name' => $field_name)))) {
        $form_state->setRedirect('entity.wisski_pathbuilder.edit_form',array('wisski_pathbuilder'=>$this->pathbuilder->id()));
        return;
      }

      // bundle?
      $this->entityManager->getStorage('field_storage_config')->create($field_storage_values)->enable()->save();

      // path?
      $this->entityManager->getStorage('field_config')->create($field_values)->save();

      $view_options = array(
        'type' => 'text_summary_or_trimmed',//has to fit the field type, see above
        'settings' => array('trim_length' => '200'),
        'weight' => 1,//@TODO specify a "real" weight
      );
    
      $view_entity_values = array(
        'targetEntityType' => 'wisski_individual',
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      );

      $display = $this->entityManager->getStorage('entity_view_display')->load('wisski_individual.'.$bundle.'.default');
      if (is_null($display)) $display = $this->entityManager->getStorage('entity_view_display')->create($view_entity_values);
      $display->setComponent($field_name,$view_options)->save();

      $form_display = $this->entityManager->getStorage('entity_form_display')->load('wisski_individual.'.$bundle.'.default');
      if (is_null($form_display)) $form_display = $display = $this->entityManager->getStorage('entity_form_display')->create($view_entity_values);
      $form_display->setComponent($field_name)->save();

      drupal_set_message(t('Created new field %field in bundle %bundle for this path',array('%field'=>$field_name,'%bundle'=>$bundle)));
      */
    } else {
# --- END if its a field -------------------
# --- if it's a bundle ----------------------
      $bundle_name = $form_state->getValue('bundle');

      // get the pbpaths
      $pbpaths = $this->pathbuilder->getPbPaths();
      // set the the bundle_name to the path
      $pbpaths[$this->path]['bundle'] = $bundle_name;
      // save it
      $this->pathbuilder->setPbPaths($pbpaths);
      $this->pathbuilder->save();

      $bundle = $this->entityManager->getStorage('wisski_bundle')->create(array('id'=>$bundle_name,'label'=>$bundle_name));
      $bundle->save();
      drupal_set_message(t('Created new bundle %bundle for this group',array('%bundle'=>$bundle_name)));
    }
# --- END if it's a bundle ----------------
    
    $form_state->setRedirect('entity.wisski_pathbuilder.edit_form',array('wisski_pathbuilder'=>$this->pathbuilder->id()));
    
  }

}
