<?php
/**
 * @file
 *
 */
   
namespace Drupal\wisski_odbc_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;

   
/**
 * Overview form for ontology handling
 *
 * @return form
 *   Form for the ontology handling menu
 * @author Mark Fichtner
 */
class WisskiODBCImportForm extends FormBase {
  
  protected $useDrupalDb = FALSE;

  /**
   * {@inheritdoc}.
   * The Id of every WissKI form is the name of the form class
   */
  public function getFormId() {
    return 'WisskiODBCImportForm';
  }
                        
  public function buildForm(array $form, FormStateInterface $form_state) {
    $items = array();

    $items['source'] = array(
      '#type' => 'fieldset',
      '#title' => t('Specify transformation file'),
      '#required' => TRUE,
      '#weight' => 2,
      'url' => array(
        '#type' => 'textfield',
        '#title' => t('Url'),
        '#default_value' => '',
        '#disabled' => FALSE,
      ),
      'upload' => array(
        '#type' => 'file',
        '#title' => t('File upload'),
        // port to D8:
        // we must explicitly set the extension validation to allow xml files
        // to be uploaded.
        // an empty array disables the extension restrictions:
        // this is theoretically somewhat insecure but we get away with it ftm...
        '#upload_validators' => array(
          'file_validate_extensions' => array(),  // => array('xml')
        ),  
      ),
    );

    $items['batch_limit'] = array(
      '#type' => 'number',
      '#title' => $this->t('Items per batch run'),
      '#default_value' => 20,
      '#min' => 0,
      '#max' => 1000,
      '#description' => $this->t('Items / Entities imported per run. Reduce amount to avoid server timeouts. 0 disables batch processing.'),
      '#weight' => 50,
    );

    $items['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
      '#weight' => 100,
    );

    return $items;

  }   

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Port to D8 file form element handling:
    // file..save_upload must be called in validate, not in submit.
    // it gives us all uploaded files as File objects.
    $file_url = NULL;
    $files = file_managed_file_save_upload($form['source']['upload'], $form_state);
    if ($files) {
      $file = reset($files);  // first one in array (array is keyed by file id)
      $file_url = $file->getFileUri();
    }
    else {
      $file_url = $form_state->getValues()['url'];
    }
    // if no file is given, it is an error
    if (!$file_url) {
      $form_state->setError($form['source'], $this->t('You must specify an import script.'));
    }
    // as we have saved the file already, we cache its path for submitForm()
    $storage = $form_state->getStorage();
    $storage['file_url'] = $file_url;
    $form_state->setStorage($storage);
  }
  
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // parse the import script file into an xml array
    // TODO: replace the array parser by DOMDocument or SimpleXML as
    // the xml->array mapping is buggy, e.g. it doesn't account for mixing
    // text and element nodes as subnodes.
    $file_url = $form_state->getStorage()['file_url'];
    $arr = $this->xml2array($file_url);
    // parse the db parameters 
    $db_params = $this->getConnectionParams($arr);
    // we have two operation modes: batch and non-batch
    // if limit is 0 we are in non-batch mode, else batch
    $limit = $form_state->getValues()['batch_limit'];
    if ($limit) { // batch mode
      // define the batch
      $batch = [
        'title' => $this->t('Importing'),
        'operations' => [],
        'progress_message' => 'Completed @current / @total tables; time elapsed: @elapsed',
        'progressive' => TRUE,
        'finished' => [static::class, 'finishBatch'],
      ];
      // each table import instruction is a separate operation
      $i = 0;
      while(isset($arr['server'][0]['table'][$i])) {
        $batch['operations'][] = [
          [static::class, 'storeTableBatch'],
          [$db_params, $i, $arr['server'][0]['table'][$i], $limit],
        ];
        $i++;
      }
      $i--;
      // register the batch; batch_process is called automatically(!?)
      batch_set($batch);
    }
    else { // non-batch mode
      $this->importInOneGo($arr, $db_params);
    }
  }
  
  
  /**
   * Helper operation to parse the db connection parameters
   */
  protected function getConnectionParams($import_script) {
    $params = [
      'is_drupal_db' => FALSE,
    ];
    // if there is a special <connection> node: use it to get the connection 
    // params, otherwise they are intermingled in the top <server> element
    if (isset($import_script['server'][0]['connection_attr']['use_drupal_db']) && $import_script['server'][0]['connection_attr']['use_drupal_db']) {
      // we have to make this if branch as the array will represent a single
      // empty xml tag differently than multiple or non-empty tags.
      $params['is_drupal_db'] = TRUE;
    }
    else {
      if (isset($import_script['server'][0]['connection'])) {
        $import_script = $import_script['server'][0]['connection'];
      }
      else {
        $import_script = $import_script['server'];
      }
      if (isset($import_script['0_attr']['use_drupal_db']) && $import_script['0_attr']['use_drupal_db']) {
        $params['is_drupal_db'] = TRUE;
      }
      else {
        $params['dbserver'] = $import_script[0]['url'];
        $params['dbuser'] = $import_script[0]['user'];
        $params['dbpass'] = $import_script[0]['password'];
        $params['db'] = $import_script[0]['database'];
        $params['dbport'] = isset($import_script[0]['port']) ? $import_script[0]['port'] : '3306';
      }
    }
    return $params; 
  }
  
  
  /**
   * Helper function that establishes a db connection from the given params.
   */
  public static function getConnection($params) {
    if ($params['is_drupal_db']) {
      $connection = \Drupal::database();
    }
    else {
      $connection = mysqli_connect(
        $params['dbserver'], 
        $params['dbuser'], 
        $params['dbpass'], 
        $params['db'], 
        $params['dbport']
      );
      if(!$connection) {
        drupal_set_message("Connection could not be established!",'error');
        return;
      } else {
        drupal_set_message("Connection established!");
      }
      mysqli_set_charset($connection,"utf8");
    }
    return $connection;
  }

  
  /**
   * Helper function that closes the db connectin if necessary
   */
  public static function closeConnection($connection, $params) {
    if (!$params['is_drupal_db']) {
      mysqli_close($connection);
    }
  }

  
  /** 
   * The main batch operation function and callback.
   * Actually a batch wrapper around the the main import function storeTable().
   * @see callback_batch_operation()
   */
  public static function storeTableBatch($db_params, $table_index, $import_script, $limit, &$context) {
    // get the db connection
    $connection = self::getConnection($db_params);
    // init the sandbox
    if (empty($context['sandbox'])) {
      $context['message'] = t('Processing table @t', ['@t' => $table_index]);
      $context['sandbox'] = [
        'offset' => 0,
        'already_seen' => [],
      ];
      // take already seen entities from previous operation
      if (isset($context['results']['table'][$table_index - 1]['already_seen'])) {
        $context['sandbox']['already_seen'] = $context['results']['table'][$table_index - 1]['already_seen'];
      }
      \Drupal::logger('WissKI Import')->info("Start import of table index $table_index");
      $context['sandbox']['total_rows'] = self::totalRowCount($db_params, $connection, $import_script);
    }
    // get data from last run
    $offset = $context['sandbox']['offset'];
    $already_seen = $context['sandbox']['already_seen'];
    // do the import
    $row_count = self::storeTable(
      $import_script, 
      $already_seen, 
      $connection, 
      $db_params['is_drupal_db'],
      $offset,
      $limit
    );
    self::closeConnection($connection, $db_params);
    // check if we are done with this table and store (intermediate) results
    if ($row_count < $limit) {
      $context['finished'] = 1;
      $context['results']['table'][$table_index]['total'] = $offset + $row_count;
      $context['results']['table'][$table_index]['already_seen'] = $already_seen;
      \Drupal::logger('WissKI Import')->info("Finished import of table index $table_index");
    }
    else {
      // we didn't count total rows, so just make up some %-number
      if ($context['sandbox']['total_rows'] !== NULL) {
        $context['finished'] = (0.0 + $offset + $row_count) / $context['sandbox']['total_rows'];
      }
      else {
        $context['finished'] = max(0, min(0.999, 1 - ($limit**2 / ($offset + $row_count))));
      }
      $context['sandbox']['offset'] = $offset + $row_count;
      $context['sandbox']['already_seen'] = $already_seen;
    } 
  }

  
  /**
   * Callback when batch has finished
   * @see callback_batch_finished()
   */
  public static function finishBatch($success, $results, $operations) {
    if ($success) {
      drupal_set_message(t('Finished import.'));
      \Drupal::logger('WissKI Import')->info('Successfully completed import');
    }
    else {
      drupal_set_message(t('Errors importing tables. @c tables could not be imported.', ['@c' => count($operations)]), 'error');
      \Drupal::logger('WissKI Import')->error(
        'Errors while processing import: {operations} operations left',
        [
          'operations' => count($operations),
        ]
      );
    }
  }


  /**
   * non-batch import function
   */
  public function importInOneGo($arr, $db_params) {
    $connection = self::getConnection($db_params);
    $alreadySeen = array();

    $i =0;
    while(isset($arr['server'][0]['table'][$i])) {
      self::storeTable($arr['server'][0]['table'][$i], $alreadySeen, $connection, $db_params['is_drupal_db']);
      $i++;
    }
    drupal_set_message("done.");
    self::closeConnection($connection, $db_params);
  }

  
  /**
   * Compute the total amount of rows to import for a <table> tag
   */
  public static function totalRowCount($db_params, $connection, $table) {
    // we look for a special <countSql> tag that provides a ready-to-be-used
    // sql query
    $sql = isset($table['countSql']) ? trim($table['countSql']) : '';
    if (!$sql) {
      $sql = isset($table['sql']) ? trim($table['sql']) : '';
      if ($sql) {
        // for complete sql queries we cannot compute the count
        return NULL;
      }
      // build the count query
      $tablename = isset($table['name']) ? $table['name'] : '';
      $append = isset($table['append']) ? $table['append'] : '';
      $sql = "SELECT COUNT(*) FROM `$tablename` $append";
    }

    // do the db query; distinguish if local connection or not
    if ($db_params['is_drupal_db']) {
      try {
        return $connection->query($sql)->fetchField();
      }
      catch (\Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
        return NULL;
      }
    }
    else {
      $qry = mysqli_query($connection, $sql);
      if(!$qry) {
        drupal_set_message("Anfrage '$sql' gescheitert!",'error');
        drupal_set_message(mysqli_error($connection), 'error');
        return NULL;
      }
      $row = mysqli_fetch_array($qry);
      return $row[0];
    }
  }

  
  /**
   * Main import function
   *
   * TODO: is $alreadySeen still used??? it is never assigned a value!
   */
  public static function storeTable($table, &$alreadySeen, $connection, $is_drupal_db, $offset = 0, $limit = 0) {
    $rowiter = 0;
    $delimiter = isset($table['delimiter']) ? $table['delimiter'] : '';
    $trim = isset($table['trim']) ? $table['trim'] : FALSE;  
  
    $sql = isset($table['sql']) ? trim($table['sql']) : '';
    // we introduce the special <sql> tag if you want to define a whole sql 
    // select query. This is more readable for more complex cases.
    if (empty($sql)) {
      $tablename = isset($table['name']) ? $table['name'] : '';
      $append = isset($table['append']) ? $table['append'] : '';
      $select = isset($table['select']) ? $table['select'] : '';
      if(empty($append))
        $append = "";
      $sql = "SELECT $select FROM `$tablename` $append";
    }
    if ($limit) {
      $sql .= " LIMIT $limit";
    }
    if ($offset) {
      $sql .= " OFFSET $offset";
    }
    // do the db query; distinguish if local connection or not
    if ($is_drupal_db) {
      try {
        $qry = $connection->query($sql);
      }
      catch (\Exception $e) {
        drupal_set_message($e->getMessage(), 'error');
        return;
      }
    }
    else {
      $qry = mysqli_query($connection, $sql);
      if(!$qry) {
        drupal_set_message("Anfrage '$sql' gescheitert!",'error');
        drupal_set_message(mysqli_error($connection), 'error');
        return;
      }
    }
    // iterate thru the result and create entities for each result row
    while($is_drupal_db ? $row = $qry->fetchAssoc() : $row = mysqli_fetch_array($qry)) {
      foreach($table['row'] as $XMLrow) {
        $alreadySeen += self::storeRow($row, $XMLrow, $alreadySeen, $delimiter, $trim);
      }
      $rowiter++;
    }
    return $rowiter;
  }

  
  public static function storeRow($row, $XMLrows, $alreadySeen, $delimiter, $trim) {
    $i = 0;
    $entity_ids = [];
    foreach($XMLrows as $key => $value) { 
      $i = 0;
      if($key == "bundle") {
        while(isset($value[$i])) {
          $bundleid = $value[$i . '_attr']['id'];
          $entity_id = self::storeBundle($row, $value[$i], $bundleid, $delimiter, $trim);
          if ($entity_id) {
            $entity_ids[$entity_id] = $entity_id;
          }
          $i++;
        }
      }
    }
    return $entity_ids;
  }


  public static function storeBundle($row, $XMLrows, $bundleid, $delimiter, $trim) {

    $entity_fields = array();
    $entity_fields["bundle"] = $bundleid;
    $found_something = false;

    foreach($XMLrows as $key => $value) {
      $i = 0;
        
      if($key == "bundle") {
        while(isset($value[$i])) {
          // this could also be a field id of an entity reference
          // so we have to check the target.
          $localbundleid = $value[$i . '_attr']['id'];

          // as the id attrib name only specifies the field id and the target 
          // bundle is guessed, we provide more unambiguous attributes
          // fieldId and bundleId that override the default id+autodetect
          if (isset($value[$i . '_attr']['fieldId'])) {
            $localbundleid = $value[$i . '_attr']['fieldId'];
          }
          if (isset($value[$i . '_attr']['bundleId'])) {
            $targetbundleid = $value[$i . '_attr']['bundleId'];
          }
          else {
            // load the fieldconfig
            $fc = FieldConfig::load('wisski_individual.' . $bundleid. '.' . $localbundleid);
            // get the target bundle id of the field config
            $targetbundleid = $fc->getSettings()['handler_settings']['target_bundles'];
            $targetbundleid = current($targetbundleid);
          }
          
          // create the referenced entity and set the reference
          $ref_entity_id = self::storeBundle($row, $value[$i], $targetbundleid, $delimiter, $trim);
          if ($ref_entity_id) {
            $entity_fields[$localbundleid][] = $ref_entity_id;
            $found_something = true;
          }
        
          $i++;
        }
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
      }  
    
    }

    // if absolutely nothing was stored - don't create an entity, as it will only
    // take time and produce nothing
    if(!$found_something)
      return;

    // generate entity
    $entity = entity_create('wisski_individual', $entity_fields);
    $entity->save();
  
    // return the id
    return $entity->id();
  }
  

  /**
   * Helper function to parse the import script
   * TODO: replace with DOMDocument or SimpleXML as this is a bit buggy
   */
  protected function xml2array($url, $get_attributes = 1, $priority = 'tag') {
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
