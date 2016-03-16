<?php


interface WriteableAdapter extends AdapterInterface {
    
  
  

  /** Execute a SDVP write operation
  *
  * @param $path_definition is an array of x1 .. xn of path steps
  *   and an array of the sdvp definitions
  * 
  *
  *
  * The return value depends on the arguements:
  * ...
  */
  public function write ($path_definition, $subject, $disamb, $value);


}
