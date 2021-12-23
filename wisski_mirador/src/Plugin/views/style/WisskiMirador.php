<?php

namespace Drupal\wisski_mirador\Plugin\views\style;

use Drupal\core\form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;


/**
 * Style plugin to render a mirador viewer as a 
 * views display style.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "wisskimirador",
 *   title = @Translation("WissKI Mirador"),
 *   help = @Translation("The WissKI Mirador views Plugin"),
 *   theme = "views_view_wisskimirador",
 *   display_types = { "normal" }
 * )
 */
class WisskiMirador extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['path'] = array('default' => 'wisski_mirador');
    return $options;
  }
  
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * Renders the View.
   */
  public function render() {

    $view = $this->view;
    
#    dpm($view->field);
    
    $results = $view->result;
    
#    dpm($results);
    
    $ent_list = array();
    $direct_load_list = array();
    
    $site_config = \Drupal::config('system.site');
    
    $to_print = "";
    
    if(!empty($site_config->get('name')))
      $to_print .= $site_config->get('name');
    if(!$site_config->get('slogan')) {
      if(!empty($to_print) && !empty($site_config->get('slogan'))) {
        $to_print .= " (" . $site_config->get('slogan') . ") ";
      } else {
        $to_print .= $site_config->get('slogan');
      }
    }
        
    global $base_url;
    
    if(empty($to_print))
      $to_print .= $base_url;   
    
    $iter = 0;
    $result_count = count($results);
    
    foreach($results as $result) {
    
#      dpm($result);
#      dpm(serialize($this->view));
#      dpm($result->__get('entity:wisski_individual/eid'), "res?");

      $entity_id = NULL;
      // tuning for solr which does not have eids but stores it in entity:wisski_individual/eid
      if(empty($result->eid)) {
        if(method_exists($result, "__get")) {
          if(!empty(current($result->__get('entity:wisski_individual/eid')))) {
            $entity_id = current($result->__get('entity:wisski_individual/eid'));
          }
        }
      }
        
#      $entity_id = empty($result->eid) ? if(!empty(current($result->__get('entity:wisski_individual/eid'))) { current($result->__get('entity:wisski_individual/eid')) } : $result->eid; 

#      dpm($result, "res?");
#      $ent_list[] = array("manifestId" => $base_url . "/wisski/navigate/" . $entity_id . "/iiif_manifest", "manifestUri" => $base_url . "/wisski/navigate/" . $entity_id . "/iiif_manifest", "location" => $to_print);
      if(!empty($entity_id)) {
        $ent_list[] = array("manifestId" => $base_url . "/wisski/navigate/" . $entity_id . "/iiif_manifest");
#      $direct_load_list[] = array( "loadedManifest" => $base_url . "/wisski/navigate/" . $result->eid . "/iiif_manifest", "viewType" => "ImageView" );
        if ($result_count > 1) {
          $direct_load_list[] = array( "loadedManifest" => $base_url . "/wisski/navigate/" . $entity_id . "/iiif_manifest", "availableViews" => array( 'ImageView'), "slotAddress" => "row1.column" . ++$iter, "viewType" => "ImageView", "bottomPanel" => false, "sidePanel" => false, "annotationLayer" => false);
        }
        else {
          $direct_load_list[] = array( "loadedManifest" => $base_url . "/wisski/navigate/" . $entity_id . "/iiif_manifest", "availableViews" => array( 'ImageView'), "viewType" => "ImageView", "bottomPanel" => false, "sidePanel" => false, "annotationLayer" => false);
        }
      } else {
        $field_to_load_http_uri_from = $view->field;
        $field_to_load_http_uri_from = array_keys($field_to_load_http_uri_from);
        $field_to_load_http_uri_from = current($field_to_load_http_uri_from);

#        dpm($result->$field_to_load_http_uri_from);
        
        $result_field = $result->$field_to_load_http_uri_from;
        
#        dpm($result_field);
        $result_field = current($result_field);
        if(!empty($result_field["value"]))
          $result_field = $result_field["value"];
        else
          $result_field = $result_field[0]["value"];
        
        $ent_list[] = array("manifestId" => $result_field);
        
#        dpm($ent_list, "ente?");
        
      }
#      $direct_load_list[] = array( "loadedManifest" => $base_url . "/wisski/navigate/" . $result->eid . "/iiif_manifest", "availableViews" => array( 'ImageView'), "windowOptions" => array( "zoomLevel" => 1, "osdBounds" => array(
#            "height" => 1500,
#            "width" => 1500,
#            "x" => 1000,
#            "y" => 2000,
#        )), "slotAddress" => "row1.column" . ++$iter, "viewType" => "ImageView", "bottomPanel" => false, "sidePanel" => false, "annotationLayer" => false);
    } 
    
    if(isset($view->attachment_before)) {
      $attachments = $view->attachment_before;
      
      foreach($attachments as $attachment) {
        $subview = $attachment['#view'];

        $subview->execute();
        $subcount= count($subview->result);

        foreach($subview->result as $res) {

          $entity_id = empty($res->eid) ? current($res->__get('entity:wisski_individual/eid')) : $res->eid;
          $ent_list[] = array("manifestId" => $base_url . "/wisski/navigate/" . $entity_id . "/iiif_manifest");
#          $ent_list[] = array("manifestId" => $base_url . "/wisski/navigate/" . $entity_id . "/iiif_manifest", "manifestUri" => $base_url . "/wisski/navigate/" . $entity_id . "/iiif_manifest", "location" => $to_print);
          if ($subcount > 1) {
            $direct_load_list[] = array( "loadedManifest" => $base_url . "/wisski/navigate/" . $entity_id . "/iiif_manifest", "availableViews" => array( 'ImageView'), "slotAddress" => "row1.column" . ++$iter, "viewType" => "ImageView", "bottomPanel" => false, "sidePanel" => false, "annotationLayer" => false );
          }
          else {
            $direct_load_list[] = array( "loadedManifest" => $base_url . "/wisski/navigate/" . $entity_id . "/iiif_manifest", "availableViews" => array( 'ImageView'), "viewType" => "ImageView", "bottomPanel" => false, "sidePanel" => false, "annotationLayer" => false );
          }
#          $direct_load_list[] = array( "loadedManifest" => $base_url . "/wisski/navigate/" . $res->eid . "/iiif_manifest", "viewType" => "ImageView" );
#          $direct_load_list[] = array( "loadedManifest" => $base_url . "/wisski/navigate/" . $res->eid . "/iiif_manifest", "availableViews" => array( 'ImageView'), "windowOptions" => array( "zoomLevel" => 1, "osdBounds" => array( 
#            "height" => 2000,
#            "width" => 2000,
#            "x" => 1000,
#            "y" => 2000,
#        )), "slotAddress" => "row1.column" . ++$iter, "viewType" => "ImageView", "bottomPanel" => false, "sidePanel" => false, "annotationLayer" => false );
        }

//        dpm($subview->result, "resi!");
        
      }
    }
    
#    dpm($ent_list, "ente gut...");

    $layout = count($ent_list);

    $layout_str = "";

    if($layout < 9) {
      $layout_str = "1x" . $layout;
    } else {
      $layout_str = "1x1";
    }
    
#    foreach($ent_list as $ent
    
    
    $form = array();
    
    $form['#attached']['drupalSettings']['wisski']['mirador']['data'] = $ent_list;
    $form['#attached']['drupalSettings']['wisski']['mirador']['layout'] = $layout_str;

    if($layout < 9) {
      $form['#attached']['drupalSettings']['wisski']['mirador']['windowObjects'] = $direct_load_list;
    }
        
    $form['#markup'] = '<div id="viewer"></div>';
    $form['#allowed_tags'] = array('div', 'select', 'option','a', 'script');
#    #$form['#attached']['drupalSettings']['wisski_jit'] = $wisski_individual;
    $form['#attached']['library'][] = "wisski_mirador/mirador";

    return $form;
  
  }
  
  
  
  
}     