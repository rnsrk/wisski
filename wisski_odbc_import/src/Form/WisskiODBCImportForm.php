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

  }   

  /**
   * {inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    #drupal_set_message('hello');
  }
  
  /**
   * Submit handler for the import
   * taken from drupal 6
   */
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

      $connection = mysqli_connect($dbserver, $dbuser, $dbpass, $db, $dbport);
  
      if(!$connection) {
        drupal_set_message("Connection could not be established!",'error');
        return;
      } else {
        drupal_set_message("Connection established!");
      }
  
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
    $delimiter = isset($table['delimiter']) ? $table['delimiter'] : '';
    $trim = isset($table['trim']) ? $table['trim'] : FALSE;  
  
    //$id = isset($table['id']) ? $table['id'] : '';
    $sql = isset($table['sql']) ? trim($table['sql']) : '';
    // we introduce the special <sql> tag if you want to define a whole sql 
    // select query. This is more readable for more complex cases.
    if (empty($sql)) {
      $tablename = $table['name'];
      $append = $table['append'];
      $select = $table['select'];
      if(empty($append))
        $append = "";
      $sql = "SELECT $select FROM `$tablename` $append";
    }
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
  
#  drupal_set_message("rows: " . serialize($XMLrows));

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
          // this could also be a field id of an entity reference
          // so we have to check the target.
          $localbundleid = $value[$i . '_attr']['id'];
          
          // load the fieldconfig
          $fc = FieldConfig::load('wisski_individual.' . $bundleid. '.' . $localbundleid);

          // get the target bundle id of the field config
          $targetbundleid = $fc->getSettings()['handler_settings']['target_bundles'];
          $targetbundleid = current($targetbundleid);
                                        
          $ref_entity_id = $this->wisski_odbc_storeBundle($row, $value[$i], $targetbundleid, $delimiter, $trim);
          $entity_fields[$localbundleid][] = $ref_entity_id;
        
          if($ref_entity_id)
            $found_something = true;
        
          $i++;
        }
        $i = 0;
      }
    
      if($key == "field") {
        while(isset($value[$i])) {
          $attrs = isset($value[$i . '_attr']) ? $value[$i . '_attr'] : array();
          $fieldid = $attrs['id'];
          $local_delimiter = isset($attrs['delimiter']) ? $attrs['delimiter'] : NULL;
          $local_trim = isset($attrs['trim']) ? $attrs['trim'] : NULL;
        
          $field_row_id = $value[$i]["fieldname"];
        
          // if there is something set on the local delimiters override the global ones
          // so the local ones can deactivate the global setting because isset 
          // reacts just on NULL and empty later on reacts on everything.
          $factual_delimiter = isset($local_delimiter) ? $local_delimiter : $delimiter;
          $factual_trim = isset($local_trim) ? $local_trim : $trim;
        
          // if there is a delimiter set and we find it
          if(!empty($factual_delimiter) && strpos($row[$field_row_id], $factual_delimiter)) {
            // separate the parts
            $field_row_array = explode($factual_delimiter, $row[$field_row_id]);
          
            // go through it, trim and add it.
            foreach($field_row_array as $one_part) {
              $entity_fields[$fieldid][] = ($trim) ? trim($one_part) : $one_part;
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
