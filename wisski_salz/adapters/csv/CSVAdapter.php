<?php

module_load_include('php', 'wisski_salz', "interface/AdapterInterface");

class CSVAdapter implements AdapterInterface {

  /**
  * Associative array containing the following keys:
  * 'file' .. path to the CSV file
  * 'separators' .. regex representing a group of chars, that are separating entries in the file
  * 'allowed chars' ..  regex representing a group of allowed characters
  *			for the ease of use we do not use the '/[ ]/' delimiters in the saved regexes
  * 'headline' .. boolean denoting whether the file contains a headline
  * 'key' .. column number of the (unique) key value in the file
  */
  private $settings = array();

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
    return $this->settings;
  }

  /** If $name is an array, then $value will be ignored and $name will be interpreted as array of all settings.
  */
  public function setSettings($name, $value = NULL) {
    
    if (is_array($name)) {
      $this->settings = $name;
    } elseif (is_string($name) || is_integer($name)) {
      $this->settings[$name] = $value;
    }
    if (!array_key_exists('headline',$this->settings)) $this->settings['headline'] = FALSE;
    if (!array_key_exists('key',$this->settings)) $this->settings['key'] = 0;
    if (!array_key_exists('separators',$this->settings)) $this->settings['separators'] = ',';
    if (!array_key_exists('allowed chars',$this->settings)) $this->settings['allowed chars'] = '\w\d\s\t';
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
  
  /**
  * Performs a query on the specified CSV file
  * @params $fields array of field keys to extract
  * @params $conditions associative array containing key value pairs for a direct hit
  * return associative array of result rows each an array keyed by the fields in $fields
  */
  public function queryCSV($fields,$conditions = array()) {
    
    $file = $this->loadFile();
    if (is_null($file)) return NULL;
    if (empty($file)) return array();
    if (!$this->verifyKeys($fields,array_keys(current($file)))) return NULL;
    else if (!empty($conditions) && !$this->verifyKeys(array_keys($conditions),array_keys(current($file)))) return NULL;
    //add the following line, to show the row number in the query result
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
        $result = array();
        foreach($fields as $field) {
          $result[$field] = $properties[$field];
        }
        $output[$row] = $result;
      }
    }
    return $output;
  }

  private function verifyKeys($fields,$keys) {
   
    $diff = array_diff($fields,$keys);
    if (!empty($diff)) {
      $errorstring = "Queried fields are not in scope: <br/>";
      foreach($diff as $err) $errorstring .= $err."<br/>";
      trigger_error($errorstring,E_USER_WARNING);
      return FALSE;
    }
    return TRUE;
  }
  
  public function loadFile() {
      
    if (!array_key_exists('file',$this->settings) || !isset($this->settings['file'])) {
      trigger_error("No CSV file specified for the adapter",E_USER_ERROR);
      return NULL;
    }
    $rows = file($this->settings['file']);
    if ($rows == NULL || empty($rows)) return array();
    $tokens = array();
    $count = 0;
    $headline = $this->settings['headline'];
    $head = FALSE;
    $keys = array();
    if ($headline) {
      $keys = $this->tokenizeRow(array_shift($rows));
      if ($keys !== NULL) {
        $count++;
        $head = TRUE;
        $trimmed_keys = array();
        foreach($keys as $num => $key) $trimmed_keys[$num] = trim($key);
        $keys = $trimmed_keys;
      } else return NULL;
    }
    foreach($rows as $row) {
      $count++;
      $row_toks = $this->tokenizeRow($row,$count);
      if ($row_toks === NULL) continue;
      $row_key = $row_toks[$this->settings['key']];
      if ($head) {
        $key_count = count($keys);
        while (count($row_toks) < $key_count) $row_toks[] = "";
        $row_toks = array_combine($keys,$row_toks);
      }
      $row_toks['row_number'] = $count;
      $tokens[$row_key] = $row_toks;
    }
    return $tokens;
  }
  
  private function tokenizeRow($row,$line_number = "") {
    
    $seps = $this->settings['separators'];
    $allowed = $this->settings['allowed chars'];
    if (preg_match('/[^'.$seps.$allowed.']/',$row)) {
      $naked_errors = preg_replace('/['.$seps.$allowed.']/','',$row);
      trigger_error("Row $line_number contains invalid characters: \"$naked_errors\"",E_USER_WARNING);
      return NULL;
    }
    return preg_split('/['.$seps.']/',$row);
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
}