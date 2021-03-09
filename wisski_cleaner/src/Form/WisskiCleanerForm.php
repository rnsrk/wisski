<?php
/**
 * @file
 *
 */

namespace Drupal\wisski_cleaner\Form;

use Drupal\wisski_salz\Entity\Adapter;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

use Drupal\wisski_salz\AdapterHelper;

/**
 * Overview form for ontology handling
 *
 * @return form
 *   Form for the Wisski Cleaner
 * @author Gustavo Fernández Riva
 */
class WisskiCleanerForm extends FormBase {

  /**
   * {@inheritdoc}.
   * The Id of every WissKI form is the name of the form class
   */
  public function getFormId() {
    return 'WisskiCleanerForm';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = array();

    $pbs = \Drupal::entityTypeManager()->getStorage('wisski_pathbuilder')->loadMultiple();
    $pathbuilder_options = Array();
    foreach($pbs as $pb) {
      $pathbuilderName = $pb->getName();
      $adapterId = $pb->getAdapterId();
      $pathbuilder_options[$adapterId] = $pathbuilderName;
    };

    // Adapter selector
    $form['select_adapter'] = array(
      '#type' => 'select',
      '#title' => $this->t('Select Pathbuilder in which you want to search.'),
      '#default_value' => '0',
      '#options' => array_merge(array("0" => 'Please select.'), $pathbuilder_options),
      '#attributes' => array('onchange' => 'this.form.submit();'),
      // '#ajax' => array(
      //   'callback' => '::ajaxStoresReset',
      //   'wrapper' => 'select_store_div',
      //   'event' => 'change',
      // ),
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Submit'),
      '#attributes' => array(
        'class' => array('js-hide'),
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

    
    if (!empty($form_state->getValue('select_adapter'))) { 
      $adapter_selected = $form_state->getValue('select_adapter');
      $adapter = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->load($adapter_selected);
      $engine = $adapter->getEngine();
      $query = "SELECT DISTINCT ?cl WHERE { ?cl a <http://www.w3.org/2002/07/owl#Class> } ORDER BY ?cl";
      $result = $engine->directQuery($query);
      $available_classes = array("0" => 'Please select.');
      
      foreach($result as $row) {
        $available_classes[$row->cl->getUri()] = $row->cl->getUri();
      };

      $form['stores']['select_class'] = array(
        '#type' => 'select',
        '#title' => $this->t('Select your main classes.'),
        '#default_value' => '',
        '#options' => $available_classes,
        '#ajax' => array(
          'callback' => '::ajaxStores',
          'wrapper' => 'select_store_div',
          'event' => 'change',
        ),
      );
      
      if(!empty($form_state->getValue('select_class'))){
        // Dynamic for the class selectors
        $class_selectors = array();
        foreach($form_state->getValues() as $form_key => $form_values){
          if (str_contains ( $form_key , 'select_class' )){
            array_push($class_selectors, array($form_key, $form_values));
          };
        };
        
        $class_selectors_filtered = array();
        foreach($class_selectors as $sel_key => $sel_value){
          if (empty($sel_value[1])){
            continue;
          } else {
            $class_selectors_filtered[$sel_key] = $sel_value;
          };
        };


        $number_selectors = count($class_selectors_filtered);
        for($i = 1; $i <= $number_selectors; $i++){
          $form['stores']['select_class'.strval($i)] = array(
            '#type' => 'select',
            // '#title' => $this->t('Select your main classes.'),
            '#default_value' => '',
            '#options' => $available_classes,
            '#ajax' => array(
              'callback' => '::ajaxStores',
              'wrapper' => 'select_store_div',
              'event' => 'change',
            ),
          );
        };

        $form['stores']['apply'] = array(
          '#type' => 'submit', 
          '#value' => $this->t('Find Loose Resources'),
          '#submit' => array('::search_call'),
        ); 


        // This is the adapter used in the previous search. If this is different than the new one, do not show any results
        $previous_adapter = $form_state->get('last_adapter')['adapt'];
        if ($previous_adapter == $adapter_selected){
          $form['stores']['results'] = array(
            '#type' => 'markup',
            '#prefix' => '<div id="results">',
            '#suffix' => '</div>',
            '#value' => "",
          );
  
          // If we just searched and found nothing or deleted everything, then congrats, otherwise do the other things:
          if(!empty($form_state->get('found_set')['Congrats'])){
            $form['stores']['results']['heading'] = array(
              '#type' => 'item',
              '#title' => $form_state->get('found_set')['Congrats'],
            );
          }
          else {
            $checkbox_array = array();

            // Find which ones are selected
            $selected_checkboxes = array();
            foreach($form_state->getValue('result_list') as $key_selected_check => $val_selected_check){
              if ($val_selected_check != strval('0')){
                array_push($selected_checkboxes, $val_selected_check);
              }
            };
            
            // If we searched or deleted and found something and there is nothing checked, then we use the results in the custom variable found_set
            if(!empty($form_state->get('found_set')) && empty($selected_checkboxes) ){
              $loose_array = $form_state->get('found_set');
              foreach($loose_array as $los){
                $checkbox_array[$los] = "<a>". $los."</a>";
              };
            }
            // If we just selected a checkbox
            else {
              $selected_adapter = $form_state->getValue('select_adapter');
              $selected_classes = [$form_state->getValue('select_class')];
              $adapter = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->load($selected_adapter);
              $engine = $adapter->getEngine();
              $graph = $engine->getDefaultDataGraphUri();
              
              // $loose_array = $form_state->getValue('result_list');
              $loose_array = $form_state->get('found_set');
              foreach($loose_array as $los){
                $checkbox_array[$los] = "<a>". $los."</a>";
                if (strval($form_state->getValues('stores')['explore_check']) == '1'){
                  // if ($value != '0'){
                  if (in_array($los, $selected_checkboxes)){
                    $table = "<table><tr><th>Subject</th><th>Property</th><th>Object</th></tr>";
                    $query = "SELECT ?p ?o WHERE ";
                    $query .=  "{ <".$los."> ?p ?o }";
                    $result = $engine->directQuery($query);
                    foreach($result as $row){
                      $table .= "<tr><td>".$los."</td><td>".strval($row->p)."</td><td>".strval($row->o)."</td></tr>";
                    };
    
                    $query = "SELECT ?s ?p WHERE ";
                    $query .=  "{ ?s ?p <".$los."> }";
                    $result = $engine->directQuery($query);
                    foreach($result as $row){
                      $table .= "<tr><td>".strval($row->s)."</td><td>".strval($row->p)."</td><td>".$los."</td></tr>";
                    };
    
    
                    $table .= "</table>";
                  $checkbox_array[$los] .= $table;
                  };
                };
              };
            };
            
            // Only display if there is something in $checkbox_array
            if (!empty($checkbox_array)) {
              ksort($checkbox_array);

              // Numbers for pagination
              if (empty($form_state->get('starting_page'))){
                $form_state->set('starting_page', array('start'=>1));
              };

              $starting_result = $form_state->get('starting_page')['start'];
              if (count($checkbox_array) >= 50 ){
                $finishing_result = $starting_result + 49;
              } else {
                $finishing_result = count($checkbox_array);
              };
              
              $form['stores']['results']['heading'] = array(
                '#type' => 'item',
                '#title' => 'Found: '. strval(count($checkbox_array)) .
                      ' loose resources. Displaying '.
                      strval($starting_result).
                      '-'.
                      strval($finishing_result) ,
              );
              
              $form['stores']['results']['pager'] = array(
                '#type' => 'markup',
                '#prefix' => '<div id="pager">',
                '#suffix' => '</div>',
                '#value' => "",
              );

              $form['stores']['results']['pager']['previous'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Previous'),
                '#submit' => array('::pagination_prev'),
              );

              $form['stores']['results']['pager']['next'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Next'),
                '#submit' => array('::pagination_next'),
              );

              $form['stores']['results']['explore_check'] = array(
                '#type' => 'checkbox',
                '#title' => 'Display Triples on Selection',
              );
    
              // The delete should just let the confirmation section be visible and the delete action should be after the confirm
              $form['stores']['results']['delete'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Delete Selected Resources'),
                '#submit' => array('::delete_selected'),
                '#attributes' => array('onclick' => 'if(!confirm("Are you sure you want to delete all selected resources?")){return false;}'),
              );

              $form['stores']['results']['delete_all'] = array(
                '#type' => 'submit',
                '#value' => $this->t('Delete All'),
                '#submit' => array('::delete_all'),
                '#attributes' => array('onclick' => 'if(!confirm("Are you sure you want to delete all resources found?")){return false;}'),
              );

              // Result checkboxes
              $form['stores']['results']['result_list'] = array(
                '#type' => 'checkboxes',
                '#title' => $this->t(''),
                '#options' => array_slice($checkbox_array,$starting_result - 1, 49),
                '#ajax' => array(
                  'callback' => '::checkbox_change',
                  'event'=> 'change',
                  'wrapper' => 'results',)
              );
            };
          };
        };
      };
    };
  return $form;
  }


  public static function checkbox_change(array &$form, FormStateInterface &$form_state) {      
      $form_state->setRebuild(TRUE);
      return $form['stores']['results'];
  }


  public function delete_all(array &$form, FormStateInterface &$form_state){
    // reset the pagination
    $form_state->set('starting_page', array('start'=>1));

    $selected_adapter = $form_state->getValue('select_adapter');
    $adapter = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->load($selected_adapter);
    $engine = $adapter->getEngine();
    $graph = $engine->getDefaultDataGraphUri();

    foreach($form_state->get('found_set') as $delresource){
      $delquery =  "DELETE WHERE {<". $delresource ."> ?p ?o . };";
      $delquery .= "DELETE WHERE {?s ?p1 <". $delresource."> . };";
      $engine->directUpdate($delquery);
      $form_state->unsetValue(array('result_list', $delresource));
    };

    $form_state->set('found_set', array('Congrats'=> "Deleted all loose triples"));

    $form_state->setRebuild(TRUE);
      
    return;

  }

  public function delete_selected(array &$form, FormStateInterface &$form_state){
    // reset the pagination
    $form_state->set('starting_page', array('start'=>1));

    $to_delete = [];
    foreach($form_state->getValue('result_list') as $value => $state){
      if (strval($state) != '0') {
        array_push($to_delete, $value);
      };
      };

    $selected_adapter = $form_state->getValue('select_adapter');
    $adapter = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->load($selected_adapter);
    $engine = $adapter->getEngine();
    $graph = $engine->getDefaultDataGraphUri();


    foreach($to_delete as $delresource){
      $delquery =  "DELETE WHERE {<". $delresource ."> ?p ?o . };";
      $delquery .= "DELETE WHERE {?s ?p1 <". $delresource."> . };";
      $engine->directUpdate($delquery);
      $form_state->unsetValue(array('result_list', $delresource));
    };

    
    $new_found_set = array();
    foreach($form_state->get('found_set') as $val){
      if (!in_array($val, $to_delete)){
        array_push($new_found_set, $val);
      };
    };
    
    $form_state->set('found_set', $new_found_set);

    $form_state->setRebuild(TRUE);
      
    return;
  }

  public function search_call(array $form, FormStateInterface $form_state) {

    // reset the pagination
    $form_state->set('starting_page', array('start'=>1));

    $selected_adapter = $form_state->getValue('select_adapter');
    
    
    // First Step: We find all individuals that are resources.
    $distinct_subjects = [];
    $adapter = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->load($selected_adapter);
    $engine = $adapter->getEngine();
    $graph = $engine->getDefaultDataGraphUri();
    $query = "SELECT DISTINCT ?s WHERE ";
    $query .= '{ GRAPH <'. $graph. '> ';
    $query .=  "{ ?s ?p ?o }";
    // Close graph
    $query .= "}";
    $result = $engine->directQuery($query);
    foreach($result as $row){
      array_push($distinct_subjects, $row->s->getUri());
    };
    

    // MAIN INDIVIDUALS
    $selected_classes = array();
    foreach($form_state->getValues() as $form_key => $form_values){
      if (str_contains ( $form_key , 'select_class' )){
        if (!empty($form_values)){
          array_push($selected_classes, $form_values);
        };
      };
    };

    $main_individuals = Array();
    $lastElement = end($selected_classes);
    $query = "SELECT DISTINCT ?main WHERE ";
    $query .= '{ GRAPH <'. $graph. '> {';
    foreach($selected_classes as $cls){
      $query .=  "{ ?main a <".$cls."> . }";
      if ($cls != $lastElement){
        $query .= " UNION ";
      };
    };
    // Close graph
    $query .= "}}";
    // dpm($query);
    $result = $engine->directQuery($query);
    foreach($result as $row){
      array_push($main_individuals, $row->main->getUri());
    };
    

    $filter = $main_individuals;
    $lastLevel = $main_individuals;

    $match = true;

    while ($match == true){
      $newLevel = [];
      $currentToDo = $lastLevel;
      while (count($currentToDo) > 0){
        $query = "SELECT DISTINCT ?o WHERE {GRAPH <". $graph . "> {";
        $lastElement = end(array_slice($currentToDo, 0 , 100));
        foreach ( array_slice($currentToDo, 0 , 100) as $ctd){
          if (filter_var($ctd, FILTER_VALIDATE_URL)){
            $query .= "{<".$ctd."> ?p ?o }";
            if ($ctd != $lastElement){
              $query .= " UNION ";
            };
          };
        };
        $query .= "}}";
        //dpm($query);
        $result = $engine->directQuery($query);
        foreach($result as $row){
          if (get_class($row->o) == "EasyRdf_Resource"){
            array_push($newLevel, $row->o->getUri());  
          }
        };
        $currentToDo = array_slice($currentToDo, 100);
      };


      if (count($newLevel) > 0){
        $lastLevel = Array();
        foreach($newLevel as $nlv){
          if (!in_array($nlv, $filter) ){
            array_push($filter, $nlv);
            array_push($lastLevel, $nlv);
          };
        };
      } else {
        $match = false;
      };

    };

    $final_set = array_diff($distinct_subjects, $filter);
    // If nothing is found, then write a congrats message
    if(empty($final_set)){
      $final_set = array('Congrats'=> "Congratulations, there is no garbage in your triple store!!");
    };
    $form_state->set('found_set', $final_set);

    // This is used to not show results after changing the adapter
    $form_state->set('last_adapter', array('adapt' => strval($selected_adapter)));

    $form_state->setRebuild(TRUE);

    return;
  }

  public function ajaxStores(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    return $form['stores'];
  }

  public function pagination_prev(array &$form, FormStateInterface &$form_state) {
    $current_start = $form_state->get('starting_page')['start'];

    if ($current_start -49 <= 1) {
      $form_state->set('starting_page', array('start'=>1));
    } else {
      $form_state->set('starting_page', array('start'=>$current_start -49));
    };

    $form_state->setRebuild(TRUE);
    return;
  }

  public function pagination_next(array &$form, FormStateInterface &$form_state) {
    $total = count($form_state->get('found_set'));
    $current_start = $form_state->get('starting_page')['start'];

    if ($total <= 50){
      $form_state->set('starting_page', array('start'=>1));
    } else if ($current_start + 50 <= $total) {
      $form_state->set('starting_page', array('start'=>$current_start + 49));
    } else {
      $form_state->set('starting_page', array('start'=>$total -49));
    };

    $form_state->setRebuild(TRUE);
    return;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    #drupal_set_message('hello');
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->set('last_adapter', array('adapt' => '0'));
    $form_state->setRebuild(TRUE);
    return;

  }
  
  
}
