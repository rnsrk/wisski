<?php

module_load_include('php', 'wisski_salz', "interface/AdapterInterface");
//require '/local/srv/www/htdocs/dm_drupal7/sites/all/modules/wisski/WisskiErrorHandler.php';

class CSVAdapter implements AdapterInterface {

  /**
  * Associative array containing the following keys:
  * 'file' .. path to the CSV file
  * 'separators' .. regex representing a group of chars, that are separating entries in the file
  * 'allowed chars' ..  regex representing a group of allowed characters
  * 'delimiters' .. array of arrays containing pairs of surrounding delimiters of strings
  * 'comment signs' .. string containing characters denoting a comment line
  *			for the ease of use we do not use the '/[ ]/' delimiters in the saved regexes
  * 'headline' .. boolean denoting whether the file contains a headline
  * 'unique key' .. name or column number of the (human-readable) unique key that identifies the row
  */
  private $settings = array();
  
  function __construct($file_name) {
    
//    ExceptionThrower::Start();
    $this->setSettings('file',$file_name);
    $this->setStandardSettings();
  }

  /** Execute a SDVP query
  *
  * @param $path_definition is an array of x1 .. xn of path steps
  *   and an array of the sdvp definitions
  * 
  *
  *
  * The return value depends on the arguements:
  * ...
  * @TODO fill with purpose
  */
  public function query ($path_definition, $subject = NULL, $disamb = array(), $value = NULL) {
  
  }
  
  /** Return the settings page(s)
  */
//  public function settings_page ();


  public function getSettings($name = NULL) {
    if ($name == NULL)
      return $this->settings;
    else return array_key_exists($name,$this->settings) ? $this->settings[$name] : NULL;
  }

  private function setStandardSettings() {
  
    if (!array_key_exists('headline',$this->settings)) 
      $this->settings['headline'] = FALSE;
    if (!array_key_exists('separators',$this->settings))
      $this->settings['separators'] = ',';
    if (!array_key_exists('allowed chars',$this->settings))
      $this->settings['allowed chars'] = '\w\d\s\t';
    if (!array_key_exists('delimiters',$this->settings))
      $this->settings['delimiters'] = array();
    if (!array_key_exists('imported',$this->settings))
      $this->settings['imported'] = FALSE;
  }

  /** If $name is an array, then $value will be ignored and $name will be interpreted as array of all settings.
  */
  public function setSettings($name, $value = NULL) {
    
    if (is_array($name)) {
      foreach($name as $key => $val) {
        $this->setSettings($key,$val);
      }
    } elseif (is_string($name) || is_integer($name)) {
      if ($name == 'file' && isset($this->settings['file'])) {
        trigger_error("The associated file should not be changed",E_USER_WARNING);
        return;
      }
      $this->settings[$name] = $value;
    }
  }
  
  public function addSeparators($char) {

    $seps = &$this->settings['separators'];
    $allowed = $this->settings['allowed chars'];
    if (preg_match('/['.$allowed.']/',$char)) {
      trigger_error("Allowed chars and separators must be different",E_USER_WARNING);
      return;
    }
    if (!preg_match('/['.$seps.']/',$char)) {
      $seps .= $char;
    }
  }
  
  public function addAllowedChars($char) {
  
    $seps = $this->settings['separators'];
    $allowed = &$this->settings['allowed chars'];
    if (preg_match('/['.$seps.']/',$char)) {
      trigger_error("Allowed chars and separators must be different",E_USER_WARNING);
      return;
    }
    if (!preg_match('/['.$allowed.']/',$char)) {
      $allowed .= $char;
    }
  }
  
  public function addCommentSigns($char) {
  
    $allowed = $this->settings['allowed chars'];
    if (preg_match('/['.$allowed.']/',$char)) {
      trigger_error("Allowed chars and comment signs must be different",E_USER_WARNING);
      return;
    }
    if (isset($this->settings['comment signs'])) {
      $comments = &$this->settings['comment signs'];
      if (!preg_match('/['.$comments.']/',$char)) {
        $comments .= $char;
      }
    } else
      $this->settings['comment signs'] = $char;
  }
  
  public function addDelimiters($input) {
  
    $delimiters = array();
    if (is_array($input)) {
      if (empty($input)) return;
      if (count($input) == 1) {
        $delimiters[] = current($input);
        $delimiters[] = $delimiters[0];
      } else $delimiters = array_combine(array(0,1),$input);
    } else {
      if ($input == '') return;
      $delimiters[] = $input;
      $delimiters[] = $delimiters[0];
    }
    $del = &$this->settings['delimiters'];
    if (in_array($delimiters,$del)) return;
    $seps = $this->settings['separators'];
    foreach($delimiters as $char) {
      if (preg_match('/['.$seps.']/',$char)) {
        trigger_error("String delimiters and separators must be different",E_USER_WARNING);
        return;
      }
    }
    dpm($delimiters);
    $del[] = $delimiters;
  }
  
  public function importToDB() {
  
    list($keys,$results) = $this->loadFile();
    $file_name = $this->settings['file'];
    dpm($file_name);
    $short_name = strrchr($file_name,'/');
    dpm($short_name);
    $table_name = 'wisski_'.preg_replace('/[^a-zA-Z0-9_]/','_',$short_name);
    dpm($table_name);
    if(!db_table_exists($table_name)) {
      $schema = array(
        'description' => 'import table for file: "'.$file_name,
      );
      $fields = array();
      foreach ($keys as $key) {
        $fields[$key] = array(
          'description' => $key,
          'type' => 'varchar',
          'length' => 255,
          'default' => '',
        );
      }
      $id_name = 'import_id';
      while (in_array($id_name,$keys)) $id_name .= '_';
      $schema['primary key'] = array($id_name);
      $fields[$id_name] = array(
        'not null' => TRUE,
        'type' => 'serial',
      );
      $schema['fields'] = $fields;
      db_create_table($table_name,$schema);
      drupal_set_message('Table '.$table_name.' has successfully been created');
    }
    $this->settings['db_table'] = $table_name;
    foreach($results as $row) {
      unset($row['row_number']);
      $unique = $this->settings['unique key'];
      $result = db_select($table_name,'tab')->fields('tab')->condition($unique,$row[$unique],'LIKE')->execute()->fetchObject();
      if (empty($result)) db_insert($table_name)->fields($row)->execute();
    }
    $this->settings['imported'] = TRUE;
  }

  /**
  * insert a new entry line at the end of the file, Entries are given either by key-value-pairs
  * in a keyed file or otherwise as an ordered and fully specified array
  * @params $fields array containing the values to put in
  * @params auto_increment boolean denoting whether to autoincrement
  * @params increment_key if set, the key on which we will increment is (re)set to this
  */
  public function insertCSV($fields) {
    
    if (empty($fields)) return;
    if (!array_key_exists('file',$this->settings) || !isset($this->settings['file'])) {
      trigger_error('No CSV file specified for the adapter',E_USER_ERROR);
      return;
    }
    list($keys,$file) = $this->loadFile();
    if (!$this->validateKeys(array_keys($fields),$keys)) {
      trigger_error('Wrong keys specified in fields',E_USER_ERROR);
      return;
    }    
    $outstring = "\n".$fields[array_shift($keys)];;
    foreach($keys as $key) {
      if (isset($fields[$key])) {
        $string = $fields[$key];
        if (preg_match('/[^'.$this->settings['allowed chars'].']/',$string)) {
          $naked_errors = preg_replace('/['.$this->settings['allowed chars'].']/','',$string);
          trigger_error('Value '.$string.' contains invalid characters '.$naked_errors,E_USER_NOTICE);
        }
        else {
          $outstring .= ','.$string;
        }
      }
    }
    file_put_contents($this->settings['file'],$outstring,FILE_APPEND);
  }

  /**
  * we do not update CSVs
  */
  public function updateCSV($fields,$conditions = array()) {
  
  }
  
  /**
  * Performs a query on the specified CSV file
  * @params $fields array of field keys to extract
  * @params $conditions associative array containing key value pairs for a direct hit
  * return associative array of result rows each an array keyed by the fields in $fields
  */
  public function queryCSV($fields,$conditions = array()) {
    
    if ($this->settings['imported']) {
      $query = db_select($this->settings['db_table'],'tab')
              ->fields('tab',$fields);
      foreach ($conditions as $field => $condition) {
        $query = $query->condition($field,$condition,'LIKE');
      }
      return $query->execute()->fetchAllAssoc($this->settings['unique key']);
    } else {
      list($keys,$file) = $this->loadFile();
      if (is_null($file)) return NULL;
      if (empty($file)) return array();
      if (!$this->validateKeys($fields,$keys)) {
        trigger_error('Wrong keys specified in fields',E_USER_ERROR);
        return;
      }
      else if (!empty($conditions) && !$this->validateKeys(array_keys($conditions),$keys)) {
        trigger_error('Wrong keys specified in conditions',E_USER_ERROR);
        return;
      }
      //add the following line to show the row number in the query result
      //$fields[] = 'row_number';
      $output = array();
      foreach($file as $row => $properties) {
        $go = TRUE;
        foreach($conditions as $key => $value) {
          if ($properties[$key] != $value) {
            $go = FALSE;
            continue;
          }
        }
        if ($go) {
          $result = new stdClass();
          foreach($fields as $field) {
            $result->$field = $properties[$field];
          }
          $output[$row] = $result;
        }
      }
      return $output;
    }
  }

  private function validateKeys($fields,$keys) {
   
    $diff = array_diff($fields,$keys);
    if (!empty($diff)) {
      $errorstring = "Queried fields are not in scope: <br/>";
      foreach($diff as $err) $errorstring .= $err."<br/>";
      trigger_error($errorstring,E_USER_WARNING);
      return FALSE;
    }
    return TRUE;
  }
  
  public function cleanFile() {
    $mem = file($this->settings['file'],FILE_SKIP_EMPTY_LINES);
    file_put_contents($this->settings['file'],$mem);
  }
  
  public function loadFile() {
      
    if (!array_key_exists('file',$this->settings) || !isset($this->settings['file'])) {
      trigger_error('No CSV file specified for the adapter',E_USER_ERROR);
      return;
    }
    $tokens = array();
    $count = -1;
    $head = FALSE;
    $keys = array();
    $handle = fopen($this->settings['file'],"r+");
    if (!$handle) {
      trigger_error('file '.$this->settings['file'].' could not be opened correctly',E_USER_ERROR);
      return;
    }
    while (!feof($handle)) {
      $row = fgets($handle);
      if ($this->settings['headline'] && !$head) {
        if (trim($row) == '') {
          trigger_error('No Keys specified in file',E_USER_ERROR);
          return;
        }
        $keys = $this->tokenizeRow($row);
        if ($keys !== NULL) {
          $count++;
          $head = TRUE;
          $trimmed_keys = array();
          foreach($keys as $num => $key) $trimmed_keys[$num] = trim($key);
          $keys = $trimmed_keys;
          $key_count = count($keys);
        } else continue;
      } else {
        if (trim($row) == '') continue;
        $count++;
        $row_toks = $this->tokenizeRow($row,$count);
        if ($row_toks === NULL) continue;
        $row_key = $row_toks[array_search($this->settings['unique key'],$keys)];
        if (array_key_exists($row_key,$tokens)) {
          trigger_error('The value "'.$row_key.'" used as "'.$this->settings['unique key'].'" is not unique (in row '.$count.')',E_USER_WARNING);
          continue;
        }
        if ($head) {
          if (count($row_toks) > $key_count) {
            trigger_error('Too many values specified in row '.$count,E_USER_WARNING);
            continue;
          }    
          while (count($row_toks) < $key_count) $row_toks[] = "";
          $row_toks = array_combine($keys,$row_toks);
        } else $keys[$count] = $count;
        $row_toks['row_number'] = $count;
        $tokens[$row_key] = $row_toks;
      }
    }
    fclose($handle);
    return array($keys,$tokens);
  }
  
  private function tokenizeRow($row,$line_number = "") {
    
    if (isset($this->settings['comment signs']) && preg_match('/^['.$this->settings['comment signs'].'].*/',$row)) return NULL;
    $allowed = $this->settings['allowed chars'];
    $dirty = preg_split('/['.$this->settings['separators'].']/',$row);
    $clean = array();
    foreach($dirty as $key => $value) {
      $value = trim($value);
      //crops the string between string delimiters
      foreach ($this->settings['delimiters'] as $pair) {
        $value = preg_replace('/^'.$pair[0].'(.+)'.$pair[1].'$/','$1',$value);
      }
      //checks if all left chars are allowed
      if (preg_match('/[^'.$allowed.']/',$value)) {
        $naked_errors = preg_replace('/['.$allowed.']/',' ',$value);
        trigger_error('Entry '.$value.' in row '.$line_number.' contains invalid characters: "'.$naked_errors.' "',E_USER_NOTICE);
      } else $clean[$key] = $value;
    }
//    dpm("Tokenized $row \n".serialize($clean));
    return $clean;
  }
  
  public function initializeTest() {
  
//    $this->cleanFile();
    $this->setSettings('headline',TRUE);
    $this->setSettings('unique key','name');
    $this->addDelimiters(array('»','«'));
    $this->addDelimiters("\"");
    $this->addDelimiters('\'');
    $this->addSeparators('\;');
    $this->addAllowedChars('\-\"\'äöüÄÖÜß');
  }
  
  /**
  * Returns an array of possible values for further refining the path.
  * When in path definition mode, the selected value will be appended to to $path_steps.
  * 
  * @param $path_steps An array describing the defined steps
  * @return an array that contains possible values to select for further refining the path definition.
  *   The key is an identifier (string) and the value is
  *   a) a label that is to be displayed.
  *   b) an array containing again a subset of key-value pairs; in this case the identifier is the label of the category
  *   It depends on the adapter, how the identifier is interpreted (As a function, as symbol...). 
  * 
  */
  public function pb_definition_settings_page ($path_steps = array()) {    
  }

  public function getExternalLinkURL($uri){
  }
  
  const SEVERE = 0;
  const NOTICE = 1;
  const IGNORE = 2;
  
  private function fail($message,$line = FALSE,$code = self::SEVERE) {
    
    if ($line) $line = ' (line '.$line.')';
    else $line ='';
    switch($code) {
      case self::SEVERE: drupal_set_message('Severe error in '.__FILE__.$line.': <br>'.$message);
      throw new CSVAdapterException("There were severe errors");
      case self::NOTICE: drupal_set_message('Error in '.__FILE__.$line.': <br>'.$message);
      break;
      default: break;
    }
  }
  
}

class CSVAdapterException extends Exception {}
