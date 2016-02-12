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
 * Form for adding/editing pathbuilder paths
 *
 * @return form
 *   Form for the ontology handling menu
 * @author Mark Fichtner
 */
class wisski_core_single_path_by_uuidForm extends FormBase {

/**
 * {@inheritdoc}.
 * The Id of every WissKI form is the name of the form class except that
 * 'Form' is added with '_form'
 */
  public function getFormId() {
    return 'wisski_core_single_path_by_uuid_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $uuid='unspecified') {
    //  dpm(debug_backtrace());
    //  dpm(func_get_args(),__FUNCTION__);
    # In d8 variable_get/set/del API is now removed! You have to use the new configuration system API/storage instead
    # instead of using variable_set you have to define the variables in a .yml file in dir wisski_core/config/install
#    variable_set('wisski_throw_exceptions',TRUE);
    $set = FALSE;
    
    // If there is something already there
    # In d8 all array-based usage of $form_state is now replaced by methods!
    # Also look at https://www.drupal.org/node/2310411 for more details
    # if (isset($form_state['storage'])) {
     #  $storage = &$form_state['storage'];
     if (null !== $form_state->getStorage() ) {
       $storage = &$form_state->getStorage();               
       if (isset($storage['full_path'])) {
         $full_path = $storage['full_path'];
         if (isset($storage['starting_concept'])) {
           $starting_concept = $storage['starting_concept'];
           $set = TRUE;
           if (isset($storage['datatype_property'])) {
             $datatype_property = wisski_salz_ensure_short_namespace($storage['datatype_property']);
           }
           if (isset($storage['group_id'])) {
             $group_id = $storage['group_id'];
           }
           if (isset($storage['group_prefix'])) {
             $group_prefix = $storage['group_prefix'];
           }
         }
       }
     } else { // if it is not, initialize empty
       $form_state['storage'] = array();
       $storage = &$form_state['storage'];
     }
     $set_starting_concept = FALSE;
     
      // if we have no starting concept now we have to fetch one
      if (!$set) {
      //  dpm('not set',__FUNCTION__.' STORAGE');
        $select = db_select('wisski_pb_pathdata','p')
               ->fields('p')
               ->condition('pending',0)
               ->condition('uuid',$uuid);
         $select = $select->execute();
         if ($select->countQuery() === 1){
       #  if ($select->rowCount() === 1) {                                
           $path = $select->fetchObject();
           $starting_concept = $path->starting_concept;
           $full_path = unserialize($path->path_array);
           $datatype_property = $path->datatype_property;
           $storage['uuid'] = $path->uuid;
           $field_name = $path->connected_field;
           $map = $path->connected_field_property;
           $title = $path->short_name;
           if (isset($path->external_path)) {
             $datatype_property = 'external';
             $external_path = wisski_core_make_path_array(array('path_id' => $path->external_path));          
             $storage['external_path'] = $external_path;
           }
           if (!empty($path->group_id)) {
             $group_id = $path->group_id;
             $storage['group_id'] = $group_id;
             $group_prefix = current(current(wisski_core_make_path_array(array('path_id'=>$group_id),TRUE)));
             $starting_concept = $group_prefix['starting_concept'];
             $group_prefix = $group_prefix['path_array'];
             array_unshift($group_prefix,$starting_concept);
             $storage['group_prefix'] = $group_prefix;
             $starting_concept = end($group_prefix);
           }
           $bundle_name = $path->connected_bundle;
        } else {
        /*  $starting_concept = $bundle->uri;
          $datatype_property = 'empty';
          $full_path = array();
          if (isset($no_group_found) && !$no_group_found) {
            $group_id = $super_group_id;
            $storage['group_id'] = $group_id;
            $group_prefix_info = current(current(wisski_core_make_path_array(array('path_id'=>$group_id))));
            $group_prefix = $group_prefix_info['path_array'];
            array_unshift($group_prefix,$group_prefix_info['starting_concept']);
            $starting_concept = end($group_prefix);
             //        dpm($group_prefix,'group prefix');
            $storage['group_prefix'] = $group_prefix;
                                                                             
          }*/
          if ($uuid !== 'unspecified') throw new Exception('Problems when loading path info');
          if (!isset($storage['uuid'])) {
            if ($uuid === 'unspecified') $uuid = wisski_core_make_uuid('');
            $storage['uuid'] = $uuid;
          }
          $set_starting_concept = TRUE;
          $full_path = array();
          $datatype_property = 'empty';
          $field_name = 'unspecified';
          $map = 'unspecified';
          $starting_concept = 'empty';
          $bundle_name = '';
        }
        $storage += array(
          'full_path' => $full_path,
          'datatype_property' => $datatype_property,
          'starting_concept' => $starting_concept,
          'bundle_name' => $bundle_name,
          'field_name' => $field_name,
          'map' => $map,
         );
        }
        
        $selected_row = -1;
        $selected_options = array();
        if (!isset($external_path) && isset($storage['external_path'])) $external_path = $storage['external_path'];
        //  dpm($full_path);
        if (isset($form_state['triggering_element']) && $form_state['triggering_element']['#name'] === 'single_path_remove') {
          //remove
          //    dpm($form_state,'REMOVE');
          $sel = explode(':',$form_state['values']['table'],2);
          $sel = $sel[1];
          if($sel === 'bottom') {
            unset($external_path);
            if (isset($storage['external_path'])) unset($storage['external_path']);
            $datatype_property = 'empty';
            $storage['datatype_property'] = 'empty';
          }
          if ($sel !== 'new') {
            $full_path = array_slice($full_path,0,$sel);
            $storage['full_path'] = $full_path;
          }
        } 
        if (isset($form_state['triggering_element']) && $form_state['triggering_element']['#name'] === 'single_path_button') {
        //select
        if (isset($storage['selected_row'])) {
          $sel = $storage['selected_row'];
          dpm(array('sel'=>$sel,'form_state'=>$form_state),'SELECT');
          if ($sel === 'starting_concept') {
            if ($starting_concept !== $form_state['input']['con:starting_concept']) {
              $full_path = array();
              $datatype_property = 'empty';
              unset($external_path);
            }
            $starting_concept = $form_state['input']['con:starting_concept'];
            if ($starting_concept !== 'empty') {
              $set_starting_concept = FALSE;
            }
            $storage['starting_concept'] = $starting_concept;
          } elseif($sel === 'bottom') {
             //        $datatype_property = $form_state['input']['step:datatype_property'];
             //        $storage['datatype_property'] = $datatype_property;
            if (isset($form_state['input']['step:external'])) {
              $explode = explode(':',$form_state['input']['step:external'],2);
              if ($explode[0] == 'external') {
                $external_path = current(current(wisski_core_make_path_array(array('path_id' => $explode[1]))));
              } else {
                trigger_error('Unexpected Problem: "'.$form_state['input']['external'].'" is not formatted correctly',E_USER_WARNING);
                if (isset($external_path)) unset($external_path);
               }
             } else {
               if (isset($external_path)) unset($external_path);
             }
             if (isset($form_state['input']['step:datatype_property'])) {
               $datatype_property = $form_state['input']['step:datatype_property'];
             }
           } elseif ($sel === 'new' && isset($form_state['input']['adds:new']) && $form_state['input']['adds:new'] !== 'empty') {
             $full_path[] = $form_state['input']['adds:new'];
             $storage['full_path'] = $full_path;
             $datatype_property = 'empty';
           } elseif (isset($form_state['input']['step:'.$sel])) {
             if (isset($full_path[$sel])) {
               if ($full_path[$sel] !== $form_state['input']['step:'.$sel]) {
                 unset($external_path);
                 $datatype_property = 'empty';
               }
             }
             $full_path[$sel] = $form_state['input']['step:'.$sel];
             $storage['full_path'] = $full_path;
             if((int)$sel === count($full_path) - 1) {
               $datatype_property = 'empty';
             }
           }
         }
          $selection = explode(':',$form_state['values']['table'],2);
          $selected_row = $selection[1];
          $storage['selected_row'] = $selected_row;
          if (isset($external_path)) {
            $storage['external_path'] = $external_path;
          } elseif (isset($storage['external_path'])) {
            unset($storage['external_path']);
          }
                                          
          $storage['datatype_property'] = $datatype_property;
          dpm($selected_row,'selected row');
          if ($set_starting_concept || $selected_row === 'starting_concept') {
            $selected_options = wisski_salz_pb_list_bundles();
          } elseif ($selected_row === 'bottom') {
            $elem = empty($full_path) ? $starting_concept : end($full_path);
            $selected_options = wisski_salz_next_datatype_properties($elem);
            if (count($full_path) % 2 === 0) {
              $external_options = wisski_core_pb_get_external_paths($elem);
            }
          } elseif ($selected_row === 'new' && empty($external_path)) {
            $elem = empty($full_path) ? $starting_concept : end($full_path);
            $selected_options = wisski_salz_next_steps($elem);
            if (count($full_path) % 2 === 0) {
              $external_options = wisski_core_pb_get_external_paths($elem);
            }
          } else {
            if (((int)$selected_row) === 0) {
              $before = $starting_concept;
            } else {
              $before = $full_path[$selected_row-1];
            }
            if (((int)$selected_row) < count($full_path) - 2) {
              $selected_options = wisski_salz_next_steps($before,$full_path[$selected_row+1]);
            } elseif (((int)$selected_row) === count($full_path) - 2) {
              if (isset($external_path)) {
                $selected_options = wisski_salz_next_steps($before,$external_path['starting_concept']);
              } else {
                $selected_options = wisski_salz_next_steps($before,$full_path[$selected_row+1]);
              }
            } else {
              $selected_options = wisski_salz_next_steps($before);
              if (((int)$selected_row) % 2 === 0) {
                $external_options = wisski_core_pb_get_external_paths($before);
              }
            }
          }
     //    dpm(array('selected_row'=>$selected_row,'selected_options'=>$selected_options));
        }
     //  dpm($full_path);
       if (isset($external_options)) dpm($external_options,'external');
       $rows = array();
       $empty_options = array('empty' => ' - '.t('select').' -');
       if ($set_starting_concept && $selected_row !== 'starting_concept') {
         $rows['con:starting_concept'] = array('step' => '<b>'.t('Starting Concept').'</b> '.t('not specified'));
       } else {
         if ($selected_row === 'starting_concept') {
           $rows['con:starting_concept'] = array('step' => array('data' => array(
             '#type' => 'select',
             '#options' => $empty_options + $selected_options,
             '#title' => t('Starting Concept'),
             '#value' => !empty($starting_concept) ? $starting_concept : 'empty',
             '#name' => 'con:starting_concept',
           )));
         } elseif (isset($group_prefix)) {
           $rows['con:starting_concept'] = array('step' => '<b>'.t('Group Prefix').'</b>: '.implode(' -> ',$group_prefix));
         } else {
           $rows['con:starting_concept'] = array('step' => '<b>'.t('Starting Concept').'</b>: '.$starting_concept);
         }
         foreach($full_path as $key => $entry) {
           if ($selected_row !== 'new' && $selected_row !== 'bottom' && ((int)$selected_row) == $key) {
             $rows['step:'.$key] = array(
               'step' => array(
                 'data' => array(
                    '#type' => 'select',
                    '#options' => $empty_options + $selected_options,
                     '#name' => 'step:'.$key,
                     '#value' => $entry,
                 ),
               ),
             );
            if (count($full_path) === $key+1) {
              if (!empty($datatype_property) && $datatype_property !== 'empty') $rows['step:'.$key]['step']['data']['#title'] = '! '.t('Datatype Property will be removed, if you change this');
              if (isset($external_path)) $rows['step:'.$key]['step']['data']['#title'] = '! '.t('Re-Used Path will be removed, if you change this');
            }
          } else {
            $rows['step:'.$key] = array('step' => $entry);
          }
        }
        $bottom_line = array();
        if(!empty($external_path)) {
          $str = '<b>'.t('Re-Used Path').':</b> ';
          $str .= $external_path['connected_bundle'].' '.$external_path['connected_field'].'-'.$external_path['connected_field_property'];
          foreach($external_path['path_array'] as $p) {
            $str .= ' -> '.$p;
          }
          $str .= ' => '.$external_path['datatype_property'];
          $bottom_line['external'] = array(
            '#markup' => $str,
          );
          } else {
            if ($selected_row === 'new') {
              $rows['adds:new'] = array(
                'step' => array(
                  'data' => array(                                                                                                                                                          
                    '#type' => 'select',
                    '#options' => $empty_options + $selected_options,
                    '#name' => 'adds:new',
                  ),
                ),
              );                                                                      
              if (!empty($datatype_property) && $datatype_property !== 'empty') $rows['adds:new']['step']['data']['#title'] = '! '.t('Datatype Property will be removed, if you change this');
            } else {
              $rows['adds:new'] = array('step' => '<b>'.t('NEW').'</b>: '.t('Add new step'));
            }
          }
                                                
    if ($selected_row === 'bottom') {
      if (!empty($external_options)) {
        $bottom_line['external'] = array(
          '#type' => 'select',
          '#options' => array_merge($empty_options,$external_options),
          '#title' => '<b>'.t('Re-Use Existing Path').'</b>',
          '#name' => 'step:external',
          '#value' => isset($external_path) ? 'external:'.$external_path['uuid'] : 'empty',
        );
        if (count($full_path) === $key+1) {
          if (!empty($datatype_property) && $datatype_property !== 'empty') $bottom_line['external']['#title'] .= ' ! '.t('Datatype Property will be removed, if you change this');
        }
      }
      $bottom_line['datatype_property'] = array(
        '#type' => 'select',
        '#options' => $empty_options + $selected_options,
        '#title' => '<b>'.t('Datatype Property').'</b>',
        '#name' => 'step:datatype_property',
        '#value' => $datatype_property,
      );     
    } elseif (count($full_path) % 2 === 0) {
      $text = $datatype_property === 'empty' ? '('.t('not set').')' : $datatype_property;
      if (!isset($external_path)) $bottom_line['datatype_property'] = array('#markup' => '<b>'.t('Datatype Property').'</b>: '.$text);
    }
    if (!empty($bottom_line)) {
      $rows['step:bottom'] = array('step'=>array('data'=>array('#type'=>'container', '#attributes' => array( 'class' => array('wki-container-class-bottom')))+$bottom_line));
    }
  }
  $form['short_name'] = array(
    '#type' => 'textfield',
    '#default_value' => isset($title) ? $title : '',
    '#title' => t('Title'),
    '#description' => t('Human-readable name for the Path'),
  );
  
  $form['uuid'] = array(
    '#type' => 'item',
    '#markup' => $storage['uuid'],
    '#title' => 'UUID',
  );
                                                                                                                                
  $form['table'] = array(
    '#type' => 'tableselect',
    '#options' => $rows,
    '#tree' => TRUE,
    '#header' => array('step' => ''),
    '#multiple' => FALSE,
    '#prefix' => '<div id=wisski-core-single-path-table>',
    '#suffix' => '</div>',
    '#name' => 'single_path_table',
    '#default_value' => 'starting_concept',
    '#title' => t('Steps'),
  );
  
  $form['op'] = array(
    '#name' => 'single_path_button',
    '#type' => 'button',
    '#value' => t('Edit Selected'),
    '#ajax' => array(
      'wrapper' => 'wisski-core-single-path-table',
      'callback' => 'wisski_core_single_path_callback',         
    ),
    '#validate' => array('wisski_core_single_path_validate'),
  );
  $form['remove'] = array(
    '#name' => 'single_path_remove',
    '#type' => 'button',
    '#value' => t('Remove from here'),
    '#ajax' => array(
      'wrapper' => 'wisski-core-single-path-table',
      'callback' => 'wisski_core_single_path_callback',
    ),
    '#validate' => array('wisski_core_single_path_validate'),
  );
  $form['submit'] = array(
    '#name' => 'single_path_submit',
    '#type' => 'submit',
    '#value' => t('Save this path'),
    '#validate' => array('wisski_core_single_path_validate'),
  );
  variable_set('wisski_throw_exceptions',FALSE);
//  dpm($form);
  return $form;
}


  public function validateForm(array &$form, FormStateInterface $form_state) {
 
  }
   
  public function submitForm(array &$form, FormStateInterface $form_state) {
    drupal_set_message("muahah");
  }
           
}
                                        
