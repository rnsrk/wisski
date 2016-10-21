<?php


function wisski_texttrip_admin_main($form_state) {

  $form = array(
    '#tree' => TRUE,
  );
  $form['wisski_texttrip_trigger_on_save'] = array(
    '#type' => 'checkbox',
    '#title' => t('Automatically extract triples on node save.'),
    '#default_value' => variable_get('wisski_texttrip_trigger_on_save', FALSE),
    '#weight' => 0,
  );
  
  $formats = filter_formats();
  foreach ($formats as $format => $f) $formats[$format] = $f->name;
  $formats_opt = $formats;
  $use_default_format = variable_get('wisski_texttrip_use_default_format', array_keys($formats_opt));

  $form["use_default_format"] = array(
    '#type' => 'checkboxes',
    '#title' => t('Use default format for'),
    '#options' => $formats_opt,
    '#default_value' => $use_default_format,
    '#weight' => 40,
  );
  
  $formats[0] = t('System default');

  $extr_formats = wisski_texttrip_format_get(NULL, NULL, FALSE);
  $extractors = wisski_texttrip_extractor_get(); 
  $extractor_types = wisski_texttrip_extractor_types();
  $extractor_types_opt = array('' => t('Nothing'));
  foreach ($extractor_types as $m => $d_n)
    foreach ($d_n as $d => $n) $extractor_types_opt[$m]["${d}_$m"] = $n;
  $context_opt = array('default' => t('Default graph'), 'node' => t('Text node'));
  $mode_opt = array('save' => t('On save'), 'view' => t('On view'));


  $form["extractors"] = array(
    '#type' => 'fieldset',
    '#title' => t('Extractors'),
    '#weight' => 30,
  );
  $form["extractors"]['table'] = array(
    '#type' => 'markup',
    '#prefix' => '<div>',
    '#theme' => 'wisski_texttrip_table',
    '#suffix' => '</div>',
  );
  $form["extractors"]['add_type'] = array(
    '#type' => 'select',
    '#title' => t('Add new extractor'),
    '#options' => $extractor_types_opt,
    '#default_value' => '', 
  );

  $form["formats"] = array(
    '#type' => 'fieldset',
    '#title' => t('Input formats'),
    '#weight' => 50,
  );
  
  foreach ($formats as $format => $name) {
  
    $form["formats"]["format_$format"] = array(
      '#type' => 'fieldset',
      '#title' => $name,
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#access' => !in_array($format, $use_default_format),
    );

    $form["formats"]["format_$format"]['table'] = array(
      '#prefix' => '<div>',
      '#theme' => 'wisski_texttrip_table',
      '#suffix' => '</div>',
//      '#attributes' => array('id' => "format-$format-table", 'class' => 'draggable'),
    );
    
//    drupal_add_tabledrag("format-$format-table", "order", 'sibling', 'weight');

  }

  foreach ($extractors as $e) {
    
    $op = array();
    $op[] = l(t('Configure'), 'admin/settings/wisski/text/trip/' . $e['eid'] . '/edit');
    $op[] = l(t('Delete'), 'admin/settings/wisski/text/trip/' . $e['eid'] . '/delete');

    $form['extractors']['table']["extractor_" . $e['eid']] = array(
      "name" => array(
        '#type' => 'markup',
        '#title' => t('Name'),
        '#value' => $e['name'],
      ),
      "context" => array(
        '#type' => 'item',
        '#title' => t('Context'),
        '#value' => $context_opt[$e['context']],
      ),
      "op" => array(
        '#type' => 'markup',
        '#title' => t('Operations'),
        '#value' => join(' ', $op),
      ),
    );
      
    foreach ($formats as $format => $name) {
      $form["formats"]["format_$format"]['table']['extractor_' . $e['eid']] = array(
        "weight" => array(
          '#type' => 'weight',
          '#title' => t('Weight'),
          '#default_value' => 100,
          '#delta' => 100,
//          '#attributes' => array('class' => 'weight', 'hidden' => 'hidden'),
        ),
        "name" => array(
          '#type' => 'markup',
          '#title' => t('Name'),
          '#value' => $e['name'],
        ),
        "mode" => array(
          '#type' => 'checkboxes',
          '#title' => t('Mode'),
          '#default_value' => array(),
          '#options' => $mode_opt,
        ),
      );
    }

  } 

  foreach ($extr_formats as $format => $f) {
    foreach ($f as $e) {

      $form["formats"]["format_$format"]['table']['extractor_' . $e['extractor']]['#weight'] = $e['weight'];
      $form["formats"]["format_$format"]['table']['extractor_' . $e['extractor']]['weight']['#default_value'] = $e['weight'];
      $form["formats"]["format_$format"]['table']['extractor_' . $e['extractor']]['mode']['#default_value'][] = $e['mode'];

    }

  }

  $form['buttons']['#weight'] = 100;
  $form['buttons']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save configuration'),
  );
  
  return $form;

}



function wisski_texttrip_admin_main_submit($form, $form_state) {
  
  $v = $form_state['values'];

  variable_set('wisski_texttrip_trigger_on_save', $v['wisski_texttrip_trigger_on_save']);

  $use_default_format = $v['use_default_format'];
  $use_default_format = array_values(array_filter($use_default_format, function($a) { return $a != 0; }));
  variable_set('wisski_texttrip_use_default_format', $use_default_format);

  if ($v['extractors']['add_type'] != '') {
    list($d, $m) = explode('_', $v['extractors']['add_type'], 2);
    db_query("INSERT INTO {wisski_texttrip_extractors} (`module`, `delta`, `name`, `context`, `settings`) VALUES ('%s', %d, '%s', 'node', '%s')", $m, $d, t('New !e', array('!e' => $m)), serialize(NULL));
  }
  
  db_query('TRUNCATE {wisski_texttrip_formats}');
  
  if (isset($v['formats'])) {
    foreach ($v['formats'] as $ftmp => $f_s) {
      list(,$format) = explode('_', $ftmp, 2);
      foreach($f_s['table'] as $etmp => $e_s) {
        list(,$eid) = explode('_', $etmp, 2);
        
        $mode = array_filter($e_s['mode']);
        if (isset($mode[0])) unset($mode[0]);
        
        foreach ($mode as $m) {
          db_query("INSERT INTO {wisski_texttrip_formats} (`format`, `mode`, `weight`,  `extractor`) VALUES (%d, '%s', %d, %d)", $format, $m, $e_s['weight'], $eid);
        }
    
      }
    }
  }

}



function wisski_texttrip_admin_configure_extractor($form, $eid) {

  $e = wisski_texttrip_extractor_get($eid);
  if ($e == NULL) {
    drupal_set_message(t('No valid extractor found with that ID: %eid', array('%eid' => $eid)), 'error');
    return array();
  }
  
  $context_opt = array('default' => t('Default graph'), 'node' => t('Text node'));
  $form = array(
    '#tree' => TRUE,
  );
  $form['general']['eid'] = array(
    '#type' => 'hidden',
    '#value' => $eid,
  );
  $form['general']['module'] = array(
    '#type' => 'hidden',
    '#value' => $e['module'],
  );
  $form['general']['delta'] = array(
    '#type' => 'hidden',
    '#value' => $e['delta'],
  );
  $form['general']['name'] = array(
    '#type' => 'textfield',
    '#title' => t('Name'),
    '#default_value' => $e['name'],
    '#maxlength' => 100,
  );
  $form['general']['context'] = array(
    '#type' => 'radios',
    '#title' => t('Triple write context'),
    '#description' => t('Determines the context (aka RDF graph) where the triples will be inserted.'),
    '#options' => $context_opt,
    '#default_value' => $e['context'],
  );
  
  $form['specific'] = module_invoke($e['module'], 'wisski_texttrip_extractor', 'settings', $e['delta'], $e['settings']);

  $form['buttons']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save configuration'),
  );
  $form['buttons']['cancel'] = array(
    '#value' => l(t('Cancel'), 'admin/settings/wisski/text/trip'),
  );

  return $form;

}


function wisski_texttrip_admin_configure_extractor_submit($form, &$form_state) {

  $e = $form_state['values']['general'];
  $s = $form_state['values']['specific'];

  $settings = module_invoke($e['module'], 'wisski_texttrip_extractor', 'submit', $e['delta'], $s);
  
  db_query("UPDATE {wisski_texttrip_extractors} SET `name` = '%s', `context` = '%s', `settings` = '%s' WHERE `eid` = %d", $e['name'], $e['context'], serialize($settings), $e['eid']);

  $form_state['redirect'] = 'admin/settings/wisski/text/trip';

}


function wisski_texttrip_admin_delete_extractor($form, $eid) {
  
  $e = wisski_texttrip_extractor_get($eid);
  if ($e == NULL) {
    drupal_set_message(t('No valid extractor found with that ID: %eid', array('%eid' => $eid)), 'error');
    return array();
  }
  
  $form = array();
  $form['eid'] = array(
    '#type' => 'hidden',
    '#value' => $eid,
  );
  $form['eid_name'] = array(
    '#type' => 'hidden',
    '#value' => $e['name'],
  );
  $form = confirm_form($form, t('Are you sure you want to delete extractor %e?', array('%e' => $e['name'])), 'admin/settings/wisski/text/trip');

  return $form;

}


function wisski_texttrip_admin_delete_extractor_submit($form, &$form_state) {
  $eid = $form_state['values']['eid'];
  $name = $form_state['values']['eid_name'];
  db_query('DELETE FROM {wisski_texttrip_extractors} WHERE `eid` = %d', $eid);
  db_query('DELETE FROM {wisski_texttrip_formats} WHERE `extractor` = %d', $eid);
  drupal_set_message(t('Deleted extractor %n.', array('%n' => $name)));
  $form_state['redirect'] = 'admin/settings/wisski/text/trip';
}

