<?php
/**
 * @file
 *
 */
   
namespace Drupal\wisski_odbc_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
   
   
/**
 * Overview form for ontology handling
 *
 * @return form
 *   Form for the ontology handling menu
 * @author Mark Fichtner
 */
class WisskiODBCImportForm extends FormBase {
  
  /**
   * {@inheritdoc}.
   * The Id of every WissKI form is the name of the form class
   */
  public function getFormId() {
    return 'WisskiODBCImportForm';
  }
                        
  public function buildForm(array $form, FormStateInterface $form_state) {
    $items = array();

    $items = array(
      '#attributes' => array('enctype' => "multipart/form-data"),
    );


    $items['source'] = array(
      '#type' => 'fieldset',
      '#title' => t('Specify transformation file'),
      '#required' => TRUE,
      '#weight' => 2,
      'url' => array(
        '#type' => 'textfield',
        '#title' => t('Url'),
  //    '#required' => TRUE,
        '#default_value' => '',
        '#disabled' => FALSE,
      ),
      'upload' => array(
        '#type' => 'file',
        '#title' => t('File upload'),
      ),
    );


    $items['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
      '#weight' => 100,
/*
    '#ahah' => array(
        'path' => 'datalink/start',
        'wrapper' => "wisskidatalink",
        'effect' => 'fade',
        'method' => 'prepend',
        'progress' => array(
            'type' => 'bar',
            'message' => t('Importing triples'),
            'url' => $GLOBALS['base_url']. '/datalink/progress',
        ),
    ),*/
    );


    return $items;


    // in wisski d8 there will be no local stores anymore, 
    // we assume that every store could load an ontology
    // we load all store entities and 
    // have to choose for which store we want to load an ontology     
/*
    $adapters = \Drupal\wisski_salz\Entity\Adapter::loadMultiple();      

    $adapterlist = array();
     
    // create a list of all adapters to choose from
    foreach($adapters as $adapter) {
      // if an adapter is not writable, it should not be allowed to load an ontology for that store 
      if($adapter->getEngine()->isWritable()){
        
        $adapterlist[$adapter->id()] = $adapter->label();
      }  
    }

    // check if there is a selected store
    $selected_store = "";
                                                
    $selected_store = !empty($form_state->getValue('select_store')) ? $form_state->getValue('select_store') : "0";

    // generate a select field      
    $form['select_store'] = array(
      '#type' => 'select',
      '#title' => $this->t('Select the store for which you want to load an ontology.'),
      '#default_value' => $selected_store,
      '#options' => array_merge(array("0" => 'Please select.'), $adapterlist),
      '#ajax' => array(
        'callback' => 'Drupal\wisski_core\Form\WisskiOntologyForm::ajaxStores',
        'wrapper' => 'select_store_div',
        'event' => 'change',
        #'effect' => 'slide',
           
      ),                                               
    );  
    
    // ajax wrapper 
    $form['stores'] = array(
      '#type' => 'markup',
      // The prefix/suffix provide the div that we're replacing, named by
      // #ajax['wrapper'] below.
      '#prefix' => '<div id="select_store_div">',
      '#suffix' => '</div>',
      '#value' => "",
    );
      
    // if there is already a store selected    
    if(!empty($form_state->getValue('select_store'))) {
       
      // if there is a selected store - check if there is an ontology in the store
      $selected_id = $form_state->getValue('select_store');
      $selected_name= $adapterlist[$selected_id];
      // load the store adapter entity object by means of the id of the selected store
      $selected_adapter = \Drupal\wisski_salz\Entity\Adapter::load($selected_id);      
      # drupal_set_message('Current selected adapter: ' . serialize($selected_adapter));
      // load the engine of the adapter
      $engine = $selected_adapter->getEngine();      
 
      // if the engine is of type sparql11_with_pb we can load the existing ontologies 
      if($engine->getPluginId() === 'sparql11_with_pb' ) {
       
        #drupal_set_message('Type: ' . $engine->getPluginId());
        $infos = $engine->getOntologies();
        #drupal_set_message(serialize($infos));
        #dpm($infos);
      
        // there already is an ontology
        if(!empty($infos) && count($infos) > 0 ) {
          $form['stores']['header'] = array(
            '#type' => 'item',
            '#markup' => '<b>Currently loaded Ontology:</b><br/>',
          );
          
          $table = "<table><tr><th>Name</th><th>Iri</th><th>Version</th><th>Graph</th></tr>";
          foreach($infos as $ont) {
            // $table .= "<tr><td>" . $ont->ont . "</td><td>" . $ont->iri . "</td><td>" . $ont->ver . "</td><td>" . $ont->graph . "</td></tr>";
            $table .= "<tr><td>" . $ont->ont . "</td><td>" . $ont->iri . "</td><td>" . $ont->ver . "</td><td>" . $ont->graph . "</td></tr>";
          }
          
          $table .= "</table>";
                                                                          
                                         
          $form['stores']['table'] = array(
            '#type' => 'item',
            '#markup' => $table,
          );
                                                                                                                                    
          $form['stores']['delete_ont'] = array(
            '#type' => 'submit',
            '#name' => 'Delete Ontology',
            '#value' => 'Delete Ontology',
            '#submit' => array('::deleteOntology'),
          );
        
          $ns = "";
          $ns = $engine->getNamespaces();
              
          $tablens = "<table><tr><th>Short Name</th><th>URI</th></tr>";
          foreach($ns as $key => $value) {
            $tablens .= "<tr><td>" . $key . "</td><td>" . $value . "</td></tr>";
          }
          $tablens .= "</table>";
                             
          $form['stores']['ns_table'] = array(
            '#type' => 'item',
            '#markup' => $tablens,
          );
                                             
          
        } else {
          // No ontology was found
          $form['stores']['load_onto'] = array(
            '#type' => 'textfield',
            '#title' => "Load Ontology for store <em> $selected_name </em>:",
            '#description' => 'Please give the URL to a loadable ontology.',
          );
                                                     
          $form['stores']['load_onto_submit'] = array(
            '#type' => 'submit',
            '#name' => 'Load Ontology',
            '#value' => 'Load Ontology',
           # '#submit' => array('wisski_core_load_ontology'),
          );
        }
                                                                                                             
      
                                                                                                                           
      }
    } 
 
   return $form;
  */ 
  }   

  public function validateForm(array &$form, FormStateInterface $form_state) {
    #drupal_set_message('hello');
  }
   
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $url = $form_state->getValues('url');
    $file = (isset($_FILES['files']['tmp_name']['upload'])) ? $_FILES['files']['tmp_name']['upload'] : NULL;
    if ($file == NULL) $file = $url;

    $arr = $this->xml2array($file);

    $dbserver = $arr['server'][0]['url'];
    $dbuser = $arr['server'][0]['user'];
    $dbpass = $arr['server'][0]['password'];
    $dbport = isset($arr['server'][0]['port']) ? $arr['server'][0]['port'] : '3306';

    $alreadySeen = array();

    $db = $arr['server'][0]['database'];

    $i =0;
    while(isset($arr['server'][0]['table'][$i])) {

      $connection = mysqli_connect($dbserver, $dbuser, $dbpass, $db, $port);
  
      if(!$connection) {
        drupal_set_message("Connection could not be established!",'error');
        return;
      } else {
        drupal_set_message("Connection established!");
      }
  
#      if(!mysqli_select_db($connection, $db)) {
#        drupal_set_message("Database '$db' could not be found!", 'error');
#        return;
#      } else {
#        drupal_set_message("DB '$db' selected!");
#      }
  
    mysqli_set_charset($connection,"utf8");
     
    // delete all attachments
    unset($_FILES);  

    $this->wisski_odbc_storeTable($arr['server'][0]['table'][$i], $alreadySeen, $connection);
    $i++;
    mysqli_close($connection);
  }
  drupal_set_message("done.");

#  $form_state['redirect'] = "admin/config/wisski/odbc_import";
  return;
}

function wisski_odbc_storeTable($table, &$alreadySeen, &$connection) {

  $rowiter = 0;
  $tablename = $table['name'];
  $delimiter = $table['delimiter'];
  $trim = $table['trim'];  
  //drupal_set_message("delim is: " );
  //drupal_set_message($delimiter);
  //return;
  
//  $maptoconcept = $table['concept'];
  $id = $table['id'];
  $append = $table['append'];
  $select = $table['select'];
  if(empty($append))
      $append = "";
      
  $sql = "SELECT $select FROM `$tablename` $append";
#  drupal_set_message(serialize(mysqli_query($connection,"SELECT * FROM `fuehrerbau1` WHERE 1")));
#    drupal_set_message(htmlentities($sql));  
  $qry = mysqli_query($connection, $sql);
  
#  $qry = mysqli_query($connection,"SELECT * FROM `fuehrerbau1` WHERE 1");
  
#  drupal_set_message(serialize($qry));

  if(!$qry) {
    drupal_set_message("Anfrage '$sql' gescheitert!",'error');
#    drupal_set_message($mysqli->character_set_name(), 'error');
    drupal_set_message(mysqli_error($connection), 'error');
    return;
  }
  
  $numrows = mysqli_num_rows($qry);
  
  $rows = array();
  
#  drupal_set_message("table: " . serialize($table));
  
  while($row = mysqli_fetch_array($qry)) {
    foreach($table['row'] as $XMLrow) {

      $this->wisski_odbc_storeRow($row, $XMLrow, $alreadySeen, $delimiter, $trim);

    }
//    break;
    $rowiter++;
#    variable_set("wisski_sql_import_progress", ($rowiter/$numrows) * 100);
  }
}

function wisski_odbc_storeRow($row, $XMLrows, $alreadySeen, $delimiter, $trim) {
  $i = 0;
  $tree = array();
  
  drupal_set_message("rows: " . serialize($XMLrows));

  foreach($XMLrows as $key => $value) { 
    $i = 0;
#    drupal_set_message("my key: " . serialize($key));
    if($key == "bundle") {
      while(isset($value[$i])) {
        $bundleid = $value[$i . '_attr']['id'];
        $this->wisski_odbc_storeBundle($row, $value[$i], $bundleid, $delimiter, $trim);
        
        $i++;
      }
    }

//      $triples = array_merge($triples, $tmptrip);
//    return $triples;
//    $i++;
  }
  return;
}

function wisski_odbc_storeBundle($row, $XMLrows, $bundleid, $delimiter, $trim) {

  $entity_fields = array();
  
  $entity_fields["bundle"] = $bundleid;
  
#  drupal_set_message("bundle: " . serialize($XMLrows));

  $found_something = false;
  
  foreach($XMLrows as $key => $value) {
    $i = 0;
    
#    drupal_set_message($key . " " . serialize($value));
        
    if($key == "bundle") {
//        dpm($row);
//        dpm($XMLrow);
      while(isset($value[$i])) {
        $bundleid = $value[$i . '_attr']['id'];
        $ref_entity_id = $this->wisski_odbc_storeBundle($row, $value[$i], $bundleid, $delimiter, $trim);
        $entity_fields[$bundleid][] = $ref_entity_id;
        
        if($ref_entity_id)
          $found_something = true;
        
        $i++;
      }
      $i = 0;
    }
    
    if($key == "field") {
      while(isset($value[$i])) {
        $fieldid = $value[$i . '_attr']['id'];
        $field_row_id = $value[$i]["fieldname"];
        
        // if there is a delimiter set and we find it
        if($delimiter && strpos($row[$field_row_id], $delimiter)) {
          // separate the parts
          $field_row_array = explode($delimiter, $row[$field_row_id]);
          
          // go through it, trim and add it.
          foreach($field_row_array as $one_part) {
            $entity_fields[$fieldid][] ($trim) ? trim($one_part) : $one_part;
          }
        
        // else - do the normal way, just trim and add.
        } else {
          $entity_fields[$fieldid][] = ($trim) ? trim($row[$field_row_id]) : $row[$field_row_id];
        }
        
        // if we found something, we have to go on, otherwise we can skip later.
        if(!empty($row[$field_row_id]))
          $found_something = true;

        $i++;
      }
      $i = 0;
    }  
    
  }

  // if absolutely nothing was stored - don't create an entity, as it will only
  // take time and produce nothing
  if(!$found_something)
    return;

#  drupal_set_message("gathered values: " . serialize($entity_fields));
  
  // generate entity
  $entity = entity_create('wisski_individual', $entity_fields);
  
  // return the id
  $entity->save();
  return $entity->id();

}

function xml2array($url, $get_attributes = 1, $priority = 'tag')
{
    $contents = "";
    if (!function_exists('xml_parser_create'))
    {
        return array ();
    }
    $parser = xml_parser_create('');
    if (!($fp = @ fopen($url, 'rb')))
    {
        return array ();
    }
    while (!feof($fp))
    {
        $contents .= fread($fp, 8192);
    }
    fclose($fp);
    xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
    xml_parse_into_struct($parser, trim($contents), $xml_values);
    xml_parser_free($parser);
    if (!$xml_values)
        return; //Hmm...
    $xml_array = array ();
    $parents = array ();
    $opened_tags = array ();
    $arr = array ();
    $current = & $xml_array;
    $repeated_tag_index = array ();
    //drupal_set_message(serialize($xml_values));
    foreach ($xml_values as $data)
    {
        unset ($attributes, $value);
        extract($data);
        $result = array ();
        $attributes_data = array ();
        if (isset ($value))
        {
            if ($priority == 'tag')
                $result = $value;
            else
                $result['value'] = $value;
        }
        if (isset ($attributes) and $get_attributes)
        {
            foreach ($attributes as $attr => $val)
            {
                if ($priority == 'tag')
                    $attributes_data[$attr] = $val;
                else
                    $result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
            }
        }
        if ($type == "open")
        {
            $parent[$level -1] = & $current;
            
            if (!is_array($current) or (!in_array($tag, array_keys($current))))
            {
/*
                $current[$tag] = $result;
                if ($attributes_data)
                    $current[$tag . '_attr'] = $attributes_data;
                $repeated_tag_index[$tag . '_' . $level] = 1;
                $current = & $current[$tag];
*/
//              drupal_set_message(serialize($current));
//              drupal_set_message(serialize($tag));
              
              $current[$tag][0] = $result;
              $repeated_tag_index[$tag . '_' . $level] = 1;
              if ($attributes_data)
                $current[$tag]['0_attr'] = $attributes_data;
              $last_item_index = $repeated_tag_index[$tag . '_' . $level] - 1;
              $current = & $current[$tag][$last_item_index];
                                              
            }
            else
            {
                if (isset ($current[$tag][0]))
                {
                    $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                    $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                    $repeated_tag_index[$tag . '_' . $level]++;
                }
                else
                {
                    $current[$tag] = array (
                        $current[$tag],
                        $result
                    );
                    $repeated_tag_index[$tag . '_' . $level] = 2;
                    if (isset ($current[$tag . '_attr']))
                    {
                        $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                        unset ($current[$tag . '_attr']);
                    }
                }
                $last_item_index = $repeated_tag_index[$tag . '_' . $level] - 1;
                $current = & $current[$tag][$last_item_index];
            }
        }
        elseif ($type == "complete")
        {
            if (!isset ($current[$tag]))
            {
                $current[$tag] = $result;
                $repeated_tag_index[$tag . '_' . $level] = 1;
                if ($priority == 'tag' and $attributes_data)
                    $current[$tag . '_attr'] = $attributes_data;
            }
            else
            {
                if (isset ($current[$tag][0]) and is_array($current[$tag]))
                {
                    $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                    if ($priority == 'tag' and $get_attributes and $attributes_data)
                    {
                        $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                    }
                    $repeated_tag_index[$tag . '_' . $level]++;
                }
                else
                {
                    $current[$tag] = array (
                        $current[$tag],
                        $result
                    );
                    $repeated_tag_index[$tag . '_' . $level] = 1;
                    if ($priority == 'tag' and $get_attributes)
                    {
                        if (isset ($current[$tag . '_attr']))
                        {
//                            drupal_set_message(serialize($current));
//                            drupal_set_message(serialize($tag));
                            $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                            unset ($current[$tag . '_attr']);
                        }
                        if ($attributes_data)
                        {
                            $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                        }
                    }
                    $repeated_tag_index[$tag . '_' . $level]++; //0 and 1 index is already taken
                }
            }
        }
        elseif ($type == 'close')
        {
            $current = & $parent[$level -1];
        }
    }
    return ($xml_array);
}
             
}                                                                                                                                                                                                                                                                          
