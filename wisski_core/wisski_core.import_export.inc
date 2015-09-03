<?php

/**
 * @param settings 
 * Associative array defining field and instance with the following keys and
 * default values
 * - field_name: (required)
 * - field_type: (required)
 * - instance_label: ''
 * - bundle_name: (required)
 * - cardinality: 1
 * - description: ''
 * - required: FALSE
 */
function wisski_core_create_instance($settings) {

  $errors = array();
  foreach (array('field_name','field_type','bundle_name') as $required_key) {
    if (!isset($settings[$required_key]) || empty($settings[$required_key])) {
      $errors[] = $required_key;
    }
  }
  if (!empty($errors)) {
    throw new WisskiMissingArgumentException($errors);
  }
  $field_info = array(
    'field_name' => $settings['field_name'],
    'type' => $settings['field_type'],
    'cardinality' => isset($settings['cardinality']) ? $settings['cardinality'] : '',
    'locked' => FALSE,
    /*'indexes' => the field-type indexes, specified by the field type's 
      hook_field_schema(). The indexes specified in $field are added to 
      those default indexes. It is possible to override the definition of 
      a field-type index by providing an index with the same name, or to 
      remove it by redefining it as an empty array of columns. Overriding 
      field-type indexes should be done carefully, for it might seriously 
      affect the site's performance.
    */
    'settings' => array(),//each omitted setting is given the default value defined in hook_field_info().
    'storage' => array(
      'type' => 'wisski_core_swips', //the storage backend specified in the 
                                //'field_storage_default' system variable.
      'module' => 'wisski_core',
      'settings' => array(), //each omitted setting is given the default value specified in hook_field_storage_info().
    ),
  );
  field_create_field($field_info);
  $instance_info = array(
    'field_name' => $settings['field_name'],
    'entity_type' => 'wisski_individual',
    'bundle' => $settings['bundle_name'], 
    'label' => isset($settings['instance_label']) ? $settings['instace_label'] : '',
    'description' => isset($settings['description']) ? $settings['description'] : '',
    'required' => isset($settings['required']) ? $settings['required'] : FALSE,
    'default_value_function' => '',
    'settings' => array(	//each omitted setting is given the default value 
                          //specified in hook_field_info().
      'widget' => array(
        //'type' =>: the default widget specified in hook_field_info().
        //'settings' =>: each omitted setting is given the default value specified in hook_field_widget_info().
      ),
      'display' => array( //Settings for the 'default' view mode will be 
                          //added if not present, and each view mode in the 
                          //definition will be completed with the following 
                          //default values:
        //'label' => 'above',
        //'type' =>: the default formatter specified in hook_field_info().
        //'settings' =>: each omitted setting is given the default value specified in hook_field_formatter_info().
      ),
    ),
  );
  field_create_instance($instance_info);
}

class WisskiMissingArgumentException extends InvalidArgumentException {
  
  function __construct(array $missing_keys) {
    $message = 'Missing required entries: '.implode(', ',$missing_keys);
    parent::__construct($message);
  }
}

function wisski_core_path_export_form($form,&$form_state) {

  $settings = array_keys(wisski_core_standard_path_settings());
  $settings = drupal_map_assoc(array_diff($settings,array('group_prefix','connected_bundle','connected_field','connected_field_property')));
  
  //we do not use wisski_core_make_path_array(array(),TRUE) here to avoid multiple
  //loading of group_prefixes
  $form_state['storage']['paths'] = array();
  $real_paths = &$form_state['storage']['paths'];
  $print_paths = array();
  $hash = array();
  $defs = array();
  $lookbehind = array(); //we save paths, that cannot be resolved at first sight
                        //should theoretically always be empty
  $paths = db_select('wisski_pb_pathdata','p')->fields('p',$settings)->condition('pending',0)->execute();
  $count = 0;
  while ($path = $paths->fetchObject()) {
    $uuid = $path->uuid;
    $path->path_array = unserialize($path->path_array);
    if (!empty($path->group_id)) {
      $gid = $path->group_id;
      if (isset($hash[$gid])) {
        $rp = $real_paths[$hash[$gid]];
        $path->path_array = array_merge($rp['path_array'],$path->path_array);
        $path->starting_concept = $rp['starting_concept'];
        $path->field_type = $rp['field_type'];
      } else {
        $lookbehind[$uuid] = $path;
        continue;
      }
    }
    if (!empty($path->external_path)) {
      $xid = $path->external_path;
      if (isset($hash[$xid])) {
        $path->path_array = array_merge($path->path_array,$real_paths[$hash[$xid]]['path_array']);
        $path->datatype_property = $real_paths[$hash[$xid]]['datatype_property'];
      } else {
        $lookbehind[$uuid] = $path;
        continue;
      }
    }
    $hash[$uuid] = $count;
    $defs[$count] = 1;
    $real_paths[$count] = (array)$path;
    $path->path_array = implode(' -> ',$path->path_array);
    $print_paths[$count] = (array)$path;
    $count++;
  }
  
  dpm(array('header'=>$settings,'defs'=>$defs,'paths'=>$print_paths),'input');
  
  if (!empty($lookbehind)) {
    trigger_error('errors when loading paths '.implode(', ',array_keys($lookbehind)),E_USER_WARNING);
  }
  
  $form['path_table'] = array(
    '#type' => 'tableselect',
    '#header' => $settings,
    '#options' => $print_paths,
    '#empty' => t('There are no paths defined yet'),
    '#weight' => -20,
    '#default_value' => $defs,
  );
  
  $form['button'] = array(
    '#type' => 'submit',
    '#value' => t('Ceate New Export File'),
  );
  
  $files = variable_get('wisski_pb_export',array());
  
  if (!empty($files)) {
    
    $header = array(t('File'),t('Size'),t('Creation Date'));
    $rows = array();
    $remove = array();
    foreach ($files as $id => $file) {
      if (!file_exists($file->uri)) {
        $remove[$id] = $id;
        continue;
      }
      $rows[] = array(
        l($file->filename,file_create_url($file->uri)),
        wisski_file_size_pretty_print($file->filesize),
        date('Y-m-d H:i:s',$file->timestamp)
      );
    }
    if (!empty($remove)) {
      $files = array_diff_key($files,$remove);
      variable_set('wisski_pb_export',$files);
      trigger_error(
        t('Removed missing files from cache: @files',
          array('@files'=>implode('. ',$remove))
        ),
        E_USER_NOTICE
      );
    }
    
    $form['upper'] = array(
      '#type' => 'fieldset',
      '#title' => t('Available Export Files'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    
    $form['upper']['table'] = array(
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    );
  
  }
  
  
  return $form;
}

function wisski_core_path_export_form_validate($form,&$form_state) {
  
  $sel = array_diff($form_state['input']['path_table'],array(NULL));
  if (empty($sel))
    form_set_error('path_table',t('Please select at least one path to export'));
  else $form_state['storage']['selected'] = $sel;
}

function wisski_core_path_export_form_submit($form,$form_state) {

  dpm($form_state,__METHOD__);  
  $export = array_intersect_key($form_state['storage']['paths'],$form_state['storage']['selected']);
  $export = array('path'=>$export);
  $export = wisski_core_array_to_xml($export,'pathbuilder');
  $file = file_save_data($export,NULL);
  $cur = variable_get("wisski_pb_export", array());    
  $cur[$file->filename] = $file;
  variable_set("wisski_pb_export", $cur);
}

function wisski_core_path_import_form($form,$form_state) {
  dpm(array($form, $form_state), 'ffs');
  
  $form['string_input'] = array(
    '#type' => 'textarea',
    '#title' => t('Paste path XML here'),
    '#prefix' => '<div id="path-xml-text">',
    '#suffix' => '</div>',
  );
  
  $files = variable_get('wisski_pb_export',array());
  
  if (!empty($files)) {
  
    $header = array('file'=>t('File'),'size'=>t('Size'),'date'=>t('Creation Date'));
    $rows = array();
    $remove = array();
    foreach ($files as $id => $file) {
      if (!file_exists($file->uri)) {
        $remove[$id] = $id;
        continue;
      }
      $rows[$id] = array(
        'file'=>$file->filename,
        'size'=>wisski_file_size_pretty_print($file->filesize),
        'date'=>date('Y-m-d H:i:s',$file->timestamp)
      );
    }
    if (!empty($remove)) {
      $files = array_diff_key($files,$remove);
      variable_set('wisski_pb_export',$files);
      trigger_error(
        t('Removed missing files from cache: @files',
          array('@files'=>implode('. ',$remove))
        ),
        E_USER_NOTICE
      );
    }
    
    $form['upper'] = array(
      '#type'  => 'fieldset',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#title' => t('or choose an available export file'),
    );
    
    $form['upper']['tbl_available'] = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#multiple' => FALSE,
      '#js_select' => FALSE,
      '#attributes' => array(),
      '#empty' => t('No export files available'),
    );
    
    $form['upper']['btn_available'] = array(
      '#type' => 'button',
      '#value' => t('Select'),
      '#ajax'=> array(
        'wrapper' => 'path-xml-text',
        'callback' => 'wisski_core_path_import_form_callback',
      ),
      '#limit_validation_errors' => array(),
      '#name' => 'btn_available',
    );
  }
  
  $form['lower'] = array(
    '#type' => 'fieldset',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#title' => t('or upload an XML file'),
  );
  
  $form['lower']['path_file_upload'] = array(
    '#type' => 'file',
    '#title' => t('Select a file to upload'),
  );
  
  $form['lower']['btn_upload'] = array(
    '#type' => 'button',
    '#value' => t('Upload'),
    '#ajax'=> array(
      'wrapper' => 'path-xml-text',
      'callback' => 'wisski_core_path_import_form_callback',
    ),
    '#limit_validation_errors' => array(),
    '#name' => 'btn_upload',
  );
  
  if (isset($form_state['triggering_element'])) {
    if ($form_state['triggering_element']['#name'] == 'btn_upload') {
      $file = file_save_upload('path_file_upload', array(
        // Validate extensions.
        'file_validate_extensions' => array('xml xhtml'),
      ));
      if ($file) {
        $default = file_get_contents($file->uri);
      }
    }
    if ($form_state['triggering_element']['#name'] == 'btn_available') {
      $file = $files[$form_state['input']['tbl_available']];
      if ($file) {
        $default = file_get_contents($file->uri);
      }
    }
  }
  
  if (isset($default)) $form['string_input']['#value'] = $default;
  
  $form['btn_next'] = array(
    '#type' => 'submit',
    '#value' => t('Next Step'),
  );
  
  return $form;
}

function wisski_core_path_import_form_callback($form,$form_state) {
  return $form['string_input'];
}

function wisski_core_path_import_form_validate($form,&$form_state) {

  if (empty($form_state['values']['string_input'])) {
    form_set_error('string_input',t('Please Insert some WissKI path XML'));
  } else {
    variable_set('wisski_path_import',NULL);
    $errors = array();
    try {
      $path_array = wisski_core_xml_to_array($form_state['values']['string_input'],$errors);
    } catch (Exception $e) {
      form_set_error('string_inpüt',t('Invalid XML'));
    }
    if (!empty($errors)) {
      dpm($errors,'ERRORS');
      //form_set_error('string_input',t('Invalid WissKI path XML'));
    }
    if (isset($path_array)) {
      $timestamp = time();
      variable_set('wisski_path_import',array($timestamp => $path_array['path']));
      $form_state['storage']['timestamp'] = $timestamp;
    }
  }
}

function wisski_core_path_import_form_submit($form,&$form_state) {

  drupal_goto('admin/config/wisski/pathbuilder/import/choose',array('query'=>array('imp' => $form_state['storage']['timestamp'])));
}

function wisski_core_path_import_selection_form($form,&$form_state) {

  //dpm(func_get_args(),__METHOD__);

  $input_paths = variable_get('wisski_path_import');
  if (isset($_GET['imp'])) {
    $key = $_GET['imp'];
    if (isset($input_paths[$key])) {
      $input_paths = $input_paths[$key];
    } else throw new WisskiWrongPathException($input_paths);
    $form_state['storage']['timestamp'] = $key;
    unset($key);
  } else throw new WisskiWrongPathException($input_paths);
  dpm($input_paths,'Path Input');
  
  $settings = array_keys(wisski_core_standard_path_settings());
  $settings = drupal_map_assoc(array_diff($settings,array('group_prefix','connected_field','connected_field_property','field_type')));
  
  //we do not use wisski_core_make_path_array(array(),TRUE) here to avoid multiple
  //loading of group_prefixes
  $real_paths = array();
  $print_paths = array();
  
  $uris = array();
  foreach(entity_load('wisski_core_bundle') as $bundle) {
  
    $uris[$bundle->uri][$bundle->type] = $bundle->label;
  }
  //dpm($uris,'uris');
    
  foreach($input_paths as $path) {
    
    if (!isset($path['starting_concept'])) {
      $path_array = $path['path_array'];
      if (count($path_array) % 2 === 1) {
        //we assume an old wisski export file and thus the first element
        //in the path is the starting concept
        $path['starting_concept'] = array_shift($path_array);
        $path['path_array'] = $path_array;
      }
    }
    if (!isset($path['connected_buhdle'])) {
      $path['connected_bundle'] = array();
      if (isset($uris[$path['starting_concept']])) {
        $path['connected_bundle'] = $uris[$path['starting_concept']];
      }
    } elseif (!is_array($path['connected_bundle'])) {
      $bundle = entity_load_single('wisski_core_bundle',$path['connected_bundle']);
      $path['connected_bundle'] = array($bundle->type => $bundle->label);
    }
    if (!isset($path['uuid'])) {
      $path['uuid'] = wisski_core_make_uuid('');
    }
    $key = $path['uuid'];
    $real_paths[$key] = $path;
    $print_paths[$key] = $path;
    if (!empty($path['path_array'])) $print_paths[$key]['path_array'] = implode(' -> ',$path['path_array']);
    $print_paths[$key]['connected_bundle'] = array(
      'data' => array(
        '#title' => t('Connected Bundle'),
        '#title_display' => 'invisible',
        '#type' => 'select',
        '#options' => array('empty'=>t('none')) + $path['connected_bundle'],
        '#required' => FALSE,
        '#default_value' => 'empty',
        '#name' => 'connected_bundle['.$key.']',
        '#ajax' => array(
          'callback' => 'wisski_core_path_import_selection_form_callback',
          'wrapper' => 'wisski-path-table',
        ),
      ),
    );
  }
  
  //dpm(array('header'=>$settings,'paths'=>$real_paths,'print'=>$print_paths),'input');
  
  $defs = array();
  
  $form['path_table'] = array(
    '#type' => 'tableselect',
    '#header' => $settings,
    '#options' => $print_paths,
    '#empty' => t('There are no paths to import'),
    '#weight' => -20,
    '#default_value' => $defs,
    '#tree' => TRUE,
    '#attributes' => array(
      'id' => array('wisski-path-table'),
    ),
  );
  
  $form['connected_bundle'] = array('#type' => 'value');
  $form['field'] = array('#type' => 'value');
  
  $form['button'] = array(
    '#type' => 'submit',
    '#value' => t('Import'),
  );
  
  $form_state['storage']['paths'] = $real_paths;
  return $form;
}

function wisski_core_path_import_selection_form_callback($form,$form_state) {

  return $form['path_table'];
}

function wisski_core_path_import_selection_form_validate(&$form,&$form_state) {
  
  dpm($form_state,__METHOD__);
  $sel = array_diff($form_state['input']['path_table'],array(NULL));
  if (empty($sel))
    form_set_error('path_table',t('Please select at least one path to import'));
  else {
    foreach ($sel as $key => $val) {
      if ($form_state['input']['field'][$key] === 'empty') {
        form_set_error('path_table]['.$key,t('Please select a fieldtype for all paths you want to import'));
      }
    }
    $form_state['storage']['selected'] = $sel;
  }
}

function wisski_core_path_import_selection_form_submit($form,&$form_state) {

  $str = $form_state['storage'];
  $import = array_intersect_key($str['paths'],$str['selected']);
  foreach ($import as $key => &$path) {
    $path['connected_bundle'] = $form_state['values']['connected_bundle'][$key];
    $path['field_type'] = $form_state['values']['field'][$key];
  }
  $timestamp = $form_state['storage']['timestamp'];
  variable_set('wisski_path_import_fix',array($timestamp => $import));
  drupal_goto('admin/config/wisski/pathbuilder/import/confirm',array('query'=>array('imp'=>$timestamp)));
}

function wisski_core_path_import_confirmation_form($form,&$form_state) {

  //dpm(func_get_args(),__METHOD__);

  $input_paths = variable_get('wisski_path_import_fix');
  if (isset($_GET['imp'])) {
    $key = $_GET['imp'];
    if (isset($input_paths[$key])) {
      $input_paths = $input_paths[$key];
    } else throw new WisskiWrongPathException($input_paths);
    $form_state['storage']['timestamp'] = $key;
    unset($key);
  } else throw new WisskiWrongPathException($input_paths);
  dpm($input_paths,'Path Input');
  
  if (!empty($input_paths)) $settings = drupal_map_assoc(array_keys(current($input_paths)));
  $real_paths = array();
  $print_paths = array();
  $bad_paths = array();

  $field_types = array();
  foreach (field_info_field_types() as $type => $info) {
    $field_types[$type] = $info['label'];
  }
  //dpm($field_types,'Types');
  
  foreach($input_paths as $path) {
    
    $key = $path['uuid'];
    $real_paths[$key] = $path;
    $print_paths[$key] = $path;
    if (!empty($path['path_array'])) $print_paths[$key]['path_array'] = implode(' -> ',$path['path_array']);
    if ($path['connected_bundle'] !== 'empty') {
      $data = array(
        '#title' => t('Field Type'),
        '#title_display' => 'invisible',
        '#type' => 'select',
        '#required' => TRUE,
        '#name' => 'field['.$key.']',
      );
      if (isset($path['field_type'])) {
        $data['#options'] = $field_types;
        $data['#value'] = $path['field_type'];
      } else {
        $data['#options'] = array('empty' => ' - '.t('select').' - ') + $field_types;
        $data['#value'] = 'empty';    
      }
      $print_paths[$key]['field_type'] = array('data' => $data);
    }
  }
  
  //dpm(array('header'=>$settings,'paths'=>$real_paths,'print'=>$print_paths),'input');
  
  $form['question'] = array('#type' => 'item','#markup' => t('Do you really want to import these paths and connect them with the given fields and bundles?'));
  
  $form['path_table'] = array(
    '#theme' => 'table',
    '#header' => $settings,
    '#rows' => $print_paths,
    '#empty' => t('There are no paths to import'),
    '#tree' => TRUE,
  );
  
  $form['btn_cancel'] = array(
    '#type' => 'button',
    '#value' => t('Cancel'),
    '#name' => 'btn_cancel',
    //'#submit' => array('wisski_core_path_import_confirmation_form_cancel'),
  );
  
  $form['btn_confirm'] = array(
    '#type' => 'submit',
    '#value' => t('Confirm'),
  );
  
  $form_state['storage']['paths'] = $real_paths;
  return $form;
}


function wisski_core_path_import_confirmation_form_validate($form,&$form_state) {

  dpm(func_get_args(),__FUNCTION__);
  if ($form_state['triggering_element']['#name'] === 'btn_cancel') {
    variable_del('wisski_path_import_fix');
    drupal_goto('admin/config/wisski/pathbuilder/import/choose',array('query'=>array('imp'=>$form_state['storage']['timestamp'])));
  }
}

function wisski_core_path_import_confirmation_form_submit($form,&$form_state) {
  
  $settings = wisski_core_standard_path_settings();
  
  if (isset($form_state['storage']['paths'])) {
    $paths = $form_state['storage']['paths'];
  
    foreach ($paths as $path) {
      $check = db_select('wisski_pb_pathdata','p')->fields('p')->condition('uuid',$path['uuid'],'LIKE')->execute();
      if ($check->rowCount() > 0) {
        $form_state['redirect'] = array(
          'admin/config/wisski/pathbuilder/import/conflict',
          array(
            'query' => array(
              'uuid' => $path['uuid'],
              'imp' => $form_state['storage']['timestamp'],
            ),
          ),
        );
        return;
      }
      $path['path_array'] = serialize($path['path_array']);
      $path = array_intersect_key($path,$settings);
#      drupal_set_message(serialize($path));
      db_insert('wisski_pb_pathdata')->fields($path)->execute();
      if ($path['connected_bundle'] !== 'empty') {
        if ($path['field_type'] !== 'empty') {
          $settings = array(
            'field_name' => isset($path['short_name']) ? $path['short_name'] : wisski_core_guess_path_name($path),
            'field_type' => $path['field_type'],
            'bundle_name' => $path['connected_bundle'],
          );
        }
      }
    }    
  }
  drupal_set_message(t('Imported external paths'));
  drupal_goto('admin/config/wisski/pathbuilder');
}

function wisski_core_guess_path_name($path) {

  $last = '';
  if (!empty($path['path_array'])) {
    $path_array = $path['path_array'];
    $last = array_pop($path_array);
    while (strpos('dentifi',$last) !== FALSE || strpos('pellat',$last) !== FALSE) {
      if (count($path_array) > 1) {
        array_pop($path_array);
        $last = array_pop($path_array);
      }
    }
  } elseif (!empty($path['datatype_property'])) $last = $path['datatype_property'];
  else $last = $path['starting_concept'];
  $matches = array();
  if (preg_match('/.*(?:\:|\#)([a-zA-Z]+)\>?/u',$last,$matches)) {
    return substr($matches[1],0,32);
  } else throw new WisskiWrongPathException($path);
}

function wisski_core_path_import_conflict_form($form,&$form_state) {

  dpm(func_get_args(),__FUNCTION__);    
  if (isset($_GET['uuid'])) {
    $uuid = $_GET['uuid'];
    $input_paths = variable_get('wisski_path_import_fix');
    if (isset($_GET['imp'])) {
      $key = $_GET['imp'];
      if (isset($input_paths[$key])) {
        $input_paths = $input_paths[$key];
      } else throw new WisskiWrongPathException($input_paths);
      $form_state['storage']['timestamp'] = $key;
      unset($key);
    } else throw new WisskiWrongPathException($input_paths);
    $new_path = $input_paths[$uuid];
    $new_path = array('mark' => '<b>'.t('Import').'</b>') + $new_path;
    $new_path['path_array'] = implode(' -> ',$new_path['path_array']);
    $settings = drupal_map_assoc(array_keys($new_path));
    $old_path = db_select('wisski_pb_pathdata','p')->fields('p')->condition('uuid',$uuid)->execute();
    if ($old_path->rowCount() === 1) $old_path = $old_path->fetchAssoc();
    else {
      drupal_set_message('Unexpected behaviour','error');
      return array();
    }
    $old_path['path_array'] = implode(' -> ',unserialize($old_path['path_array']));
    $old_path['mark'] = '<b>'.t('Existing').'</b>';
    dpm(array($settings,$old_path),'old path '.date('H:i:s',time()));
    $old_path = array_merge($settings,array_intersect_key($old_path,$settings));
    $form_state['storage']['uuid'] = $new_path['uuid'];
    //dpm(array($settings,$path,$old_path),'ERROR');
    $form['text'] = array(
      '#type' => 'item',
      '#title' => t('There has been an import conflict'),
      '#description' => t('A path with the given UUID does already exist'),
    );
    $form['#attributes'] = array(
      'class' => array('error'),
    );
    $form['table'] = array(
      '#theme' => 'table',
      '#header' => $settings,
      '#rows' => array($new_path,$old_path),
      
    );
    $form['btn_anyway'] = array(
      '#type' => 'submit',
      '#value' => t('Import and change UUID'),
      '#name' => 'btn_anyway',
    );
    $form['btn_cancel'] = array(
      '#type' => 'submit',
      '#value' => t('Do not import this path'),
    );
    return $form;
  }
}

function wisski_core_path_import_conflict_form_submit($form,&$form_state) {

  dpm(func_get_args(),__FUNCTION__);
  $input_paths = variable_get('wisski_path_import_fix');
  $timestamp = $form_state['storage']['timestamp'];
  if (isset($input_paths[$timestamp])) {
    $paths = &$input_paths[$timestamp];
  }
  $uuid = $form_state['storage']['uuid'];
  if (isset($paths[$uuid])) {
    $path = $paths[$uuid];
    unset($paths[$uuid]);
    if ($form_state['triggering_element']['#name'] === 'btn_anyway') {
      $uuid = wisski_core_make_uuid('');
      $path['uuid'] = $uuid;
      $paths[$uuid] = $path;
    }
    variable_set('wisski_path_import_fix',$input_paths);
  }
  drupal_goto('admin/config/wisski/pathbuilder/import/confirm',array('query'=>array('imp'=>$timestamp)));
}

function wisski_core_xml_to_array($xml,&$errors) {
  
  $sxi = new SimpleXMLIterator($xml);
  $array = array();
  $settings = array_keys(wisski_core_standard_path_settings());
  $settings = array_fill_keys(array_diff($settings,array('group_prefix','connected_bundle','connected_field','connected_field_property')),FALSE);
  $settings['starting_concept'] = TRUE;
  $settings['path_array'] = TRUE;
  $settings = array('path'=>$settings);
  $errors = _wisski_core_SXI_to_array($sxi,$array,$settings);
  dpm(array('xml'=>$xml,'settings'=>$settings,'result'=>$array,'errors'=>$errors),__METHOD__);
  return $array;
}

function _wisski_core_SXI_to_array(SimpleXMLIterator $sxi, array &$array) {
  
  foreach($sxi->attributes() as $key => $attr) {
    $array[$key] = (string)$attr;
  }
  for ($sxi->rewind(); $sxi->valid(); $sxi->next()) {
    $sk = $sxi->key();
    if ($sxi->hasChildren()) {
      $sub = array();
      _wisski_core_SXI_to_array($sxi->current(),$sub);
      $array[$sk][] = $sub;
    } else {
      if (isset($array[$sk])) {
        if (!is_array($array[$sk]) || !isset($array[$sk][0])) {
          $m = $array[$sk];
          $array[$sk] = array();
          $array[$sk][0] = $m;
        }
        $array[$sk][] = (string)$sxi->current();
      } else $array[$sk] = (string)$sxi->current();
    }
    if ($sk === 'path_array' && isset($array[$sk][0]) && is_array($array[$sk][0])) {
      //dpm($array,'path_array');
      $array[$sk] = $array[$sk][0];
    }
  }
  
}

/**
 * takes the $array as an associative array representing the key hierarchy of the final
 * output array
 */
function _wisski_core_SXI_to_structured_array(SimpleXMLIterator $sxi,array &$array,array $structure) {

  $required = array();
  foreach($structure as $key => $value) {
    if (is_bool($value)) {
      if ($value) $required[$key] = $key;
    } elseif (is_array($value)) {
      if (wisski_array_is_something_true($value)) $required[$key] = $key;
    }
  }
  dpm($required,'required');
  dpm($sxi->asXML(),'XML');
  dpm($structure,'Structure');
  for ($sxi->rewind(); $sxi->valid(); $sxi->next()) {
    $sk = $sxi->key();
    dpm($sk,'key');
    if (array_key_exists($structure,$sk)) {
      if ($sxi->hasChildren()) {
        $sub = array();
        $sub_struct = $structure[$sk];
        $sub_req = _wisski_core_SXI_to_array($sxi->current(),$sub,$sub_struct);
        if (!empty($sub_req)) {
          dpm($sxi->current()->asXML(),$sk);
          return array($sk => $sub_req);
        }
        $array[$sk][] = $sub;
      } else {
        if (isset($array[$sk])) {
          if (!is_array($array[$sk]) || !isset($array[$sk][0])) {
            $m = $array[$sk];
            $array[$sk] = array();
            $array[$sk][0] = $m;
          }
          $array[$sk][] = (string)$sxi->current();
        } else $array[$sk] = (string)$sxi->current();
      }
      if (isset($required[$sk])) unset($required[$sk]);
    }
  }
  return $required;
}

function wisski_core_array_to_xml(array $array,$tag='xml') {

  $sxe = new SimpleXMLElement("<$tag></$tag>");
  _wisski_core_array_to_SXE($array,$sxe,$tag);
  return $sxe->asXML();
}

function _wisski_core_array_to_SXE(array $array,SimpleXMLElement $sxe,$tag) {
  
  foreach ($array as $key => $value) {
    if (!ctype_alpha($key[0])) $key = $tag.$key;
    if (is_array($value)) {
      if (is_numeric(key($value))) {
        foreach($value as $sub_value) {
          if (is_array($sub_value)) {
            $child = $sxe->addChild($key);
            _wisski_core_array_to_SXE($sub_value,$child,$key);
          } else {
            $sxe->addChild($key,$sub_value);
          }
        }
      } else {
        $child = $sxe->addChild($key);
        _wisski_core_array_to_SXE($value,$child,$key);
      }
    } else {
//      if (is_numeric($key)) {
        $sxe->addChild($key,$value);
//      } else {
//        $sxe->addAttribute($key,$value);
//      }
    }
  }
}

function dm_first_error($str1,$str2) {

  $i = -1;
  $len1 = strlen($str1);
  $len2 = strlen($str2);
  $fallback = FALSE;
  if ($len1 < $len2) {
    $len = $len1;
    $fallback = TRUE;
  } else {
    $len = $len2;
    if ($len1 > $len2) {
      $fallnack = TRUE;
    }
  }
  while (++$i < $len) {
    if ($str1[$i] !== $str2[$i]) {
      //if (ctype_print($str1[$i]) && ctype_print($str2[$i])) {
        echo "At $i\n";
        echo ord($str1[$i]).': "'.$str1[$i]."\"\n";
        echo ord($str2[$i]).': "'.$str2[$i]."\"\n";
        echo "see\n\"".substr($str1,max($i-10,0),21)."\"\n";
        echo "see\n\"".substr($str2,max($i-10,0),21)."\"";
        return $i;
      //}
    }
  }
  if ($fallback) return $len;
  return -1;
}

/**
 * @param $path_choice an array of uuids of the paths to export
 * @return the export XML as a String
 */
function wisski_core_export_paths($path_choice,$old_wisski=FALSE) {
  
  $xmldoc = new SimpleXMLElement('<paths></paths>');
  $std_settings = $old_wisski ? wisski_old_standard_path_settings() :
    wisski_core_standard_path_settings();
  
  $result = db_select('wisski_pb_pathdata','p')
    ->fields('p')
    ->condition('uuid',$path_choice,'IN')
    ->execute();
  
  while($path = $result->fetchObject()) {

    $xml_path = $xmldoc->addChild('path');

    foreach ($std_settings as $set) {
      if (property_exists($path,$set)) {
        
        $xml_path->addChild($set,htmlentities((string)$path->{$set}));
      }
    }
  }
  
  return $xmldoc->asXML();
}

function wisski_file_size_pretty_print($size_in_bytes) {

  $units = array('B','KiB','MiB','GiB','TiB','PiB','EiB');
  $i = 0;
  $sib = $size_in_bytes;
  $base = 1;
  while ($sib > 512 && $i < 7) {
    $sib = $sib >> 10;
    $base = $base << 10;
    $i++;
  }
  if ($i === 0)
    return $size_in_bytes.' B';
  else
    return '~ '.(round(100*$size_in_bytes/$base)/100).' '.$units[$i].' ('.$size_in_bytes.' B)';
}

function wisski_array_is_something_true($array) {
  
  foreach ($array as $sub) {
    if (is_array($sub)) {
      if (wisski_array_is_something_true($sub)) return TRUE;
    } elseif (is_bool($sub)) {
      if ($sub) return TRUE;
    } else throw new UnexpectedValueException('Array contains non-boolean entries');
  }
  return FALSE;
}

class WisskiWrongPathException extends UnexpectedValueException {

  function __construct($wrong_path_data) {
    
    if (module_exists('devel')) dpm($wrong_path_data);
    parent::__construct('Incorrect Path given');
  }
}