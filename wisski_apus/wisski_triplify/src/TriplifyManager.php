<?php
/**
 * @file
 * Contains \Drupal\wisski_triplify\Util.
 */

namespace Drupal\wisski_triplify;

use Drupal\wisski_salz\AdapterHelper;
use Drupal\wisski_pipe\PipeManager;

class TriplifyManager {

  public function triplify ($entity) {
    
    $triplifyFieldTypes = array(
      'text_long_summary' => array('text' => 'value', 'constraints' => array('property:format' => 'full_html')),
      'text_long' => array('text' => 'value', 'constraints' => array('property:format' => 'full_html')),
    );

    $triplifyFieldIds = array(
      'f72ca3e589be8d883a01bd215aff9cc2' => array('text' => 'value', 'constraints' => array('property:format' => 'full_html'), 'pipe' => "triplify_babberle"),
    );

    $doc_inst = AdapterHelper::getUrisForDrupalId($entity->id())[0];

    if (empty($doc_inst)) return array();
    
    $definitions = $entity->getFieldDefinitions();
    $fields = $entity->getFields(false);

    foreach ($definitions as $name => $fieldDef) {
      $config = NULL;
      if (isset($triplifyFieldIds[$name])) {
        $config = $triplifyFieldIds[$name];
      } elseif (isset($triplifyFieldTypes[$fieldDef->getType()])) {
        $config = $triplifyFieldTypes[$fieldDef->getType()];
      }
      if ($config) {
        $fieldItemList = $fields[$name];

        $lang = $fieldItemList->getLangcode();
        
        foreach ($fieldItemList as $weight => $item) {
          $properties = $item->getProperties();

          if (isset($config['constraints'])) {
            foreach ($config['constraints'] as $on => $constraint) {
              if (substr($on, 0, 9) == 'property:') {
                $onProp = substr($on, 9);
                if (!preg_match("/$constraint/u", $properties[$onProp]->getValue())) {
                  continue;
                }
              }
            }
          }
          
          $pipeId = isset($config['pipe']) ? $config['pipe'] : 'triplify_html_default';
          $ticket = 'tr';
          $data = array(
            'document' => $doc_inst,
            'document_entity' => $entity,
            'text' => $properties[$config['text']]->getValue(),
          );
          $pipe_result = \Drupal::service('wisski_pipe.pipe')->run($pipeId, $data, $ticket, \Drupal::logger('triplify'));

dpm(array($pipeId, $data, $pipe_result));          
        }


      }
    }


    
  }

}

