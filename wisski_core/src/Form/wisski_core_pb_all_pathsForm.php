<?php
/**
 * @file
 *
 */
   
namespace Drupal\wisski_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Overview form for adding and editing paths
 *
 * @return form
 *   Form for the pathbuilder menu
 * 
 */
      
      
class wisski_core_pb_all_pathsForm extends FormBase {

  /**
    * {@inheritdoc}.
    * The Id of every WissKI form is the name of the form class except that
    * 'Form' is added with '_form'
    */
  public function getFormId() {
    return 'wisski_core_pb_all_paths_form';
  }
       
  public function buildForm(array $form, FormStateInterface $form_state) {
    //dpm(func_get_args(),__METHOD__);
    $form = array();
    $header = array(
      'title' => t('Title'),
      'starting_concept' => t('Starting Concept'),
      'path' => t('Path'),
      'datatype_property' => t('Datatype Property'),
      'op' => array(
      'data' => t('Operations'),
      'colspan' => 2,
      ),
    );
    $paths = db_select('wisski_pb_pathdata','paths')
              ->fields('paths')
              ->condition('pending',0,'=');
    if (!is_null($bundle) && isset($bundle->uri)) {
      $paths = $paths->condition('starting_concept',$bundle->uri);
    }
    $paths = $paths->execute()->fetchAllAssoc('uuid');
    $options = array();
    foreach($paths as $path) {
      $uuid = $path->uuid;
      
      /*
          $path_array = unserialize($path->path_array);
          $current = $path;
          while ($current->external_path != NULL) {
            $current = $paths[$current->external_path];
            $path_array = array_merge($path_array,unserialize($current->path_array));
          }
          $item = $path->starting_concept;
          foreach ($path_array as $step) {
            $item .= ' ==> '.$step;
          }
          $item .= ' --> '.$current->datatype_property;
        */
      $path_array = current(current(wisski_core_make_path_array(array('path_id'=>$uuid),TRUE)));
      //dpm($path_array,'path_array');
      $item = implode('-->',$path_array['path_array']);
      $options[$uuid] = array(
        'title' => $path->short_name,
        'starting_concept' => $path->starting_concept,
        'path' => $item,
        'datatype_property' => !empty($path_array['datatype_property']) ? $path_array['datatype_property'] : '',
        'op1' => l('Edit',$path_array['edit_link_url']),
        'op2' => l('Delete',''),
      );
    }
                                                                                                                                                                                                 
  //  dpm($options,'options');
  
    $colgroup = array(
      'title' => array(),
      'starting_concept' => array(),
      'path' => array(
        'style' => array(
          'width' => '100px',
        ),
      ),
    );
                                          
    $form['table'] = array(
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $options,
      '#colgroup' => $colgroup,
      '#empty' => t('no paths defined'),
      '#sticky' => TRUE,
    );
    $form['add_button'] = array(
      '#type' => 'submit',
      '#value' => t('Add Path'),
    );
    return $form;
                                           
  }
  
  public function validateForm(array &$form, FormStateInterface $form_state) {
   
  }
  
  public function submitForm(array &$form, FormStateInterface $form_state) {
    drupal_set_message("muahah");
    dpm(func_get_args(),__METHOD__);
    $form_state->setRedirect('admin/structure/wisski_core_bundle/manage/edit_path/unspecified');
    
  }
         
}
         
                  