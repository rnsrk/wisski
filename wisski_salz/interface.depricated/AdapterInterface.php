<?php


interface AdapterInterface {
    
  
  

  /** Execute a SDVP query
  *
  * @param $path_definition is an array of x1 .. xn of path steps
  *   and an array of the sdvp definitions
  * 
  *
  *
  * The return value depends on the arguements:
  * ...
  */
//  public function query ($path_definition, $subject = NULL, $disamb = array(), $value = NULL);

  
  /** Return the settings page(s)
  */
  public function settingsForm();


  public function getSettings($name = NULL);

  /** If $name is an array, then $value will be ignored and $name will be interpreted as array of all settings.
  */
  public function setSettings($name, $value = NULL);

  
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
  public function pb_definition_settings_page ($path_steps = array());



  public function getExternalLinkURL($uri);

  

}
