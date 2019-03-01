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
    
//    dpm($view);
    
    $results = $view->result;
    
    $ent_list = array();
    
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
    
    foreach($results as $result) {
      $ent_list[] = array("manifestUri" => $base_url . "/wisski/navigate/" . $result->eid . "/iiif_manifest", "location" => $to_print);
    } 
    
#    dpm($ent_list, "ente gut...");
    
    $form = array();
    
    $form['#attached']['drupalSettings']['wisski']['mirador']['data'] = $ent_list;
        
    $form['#markup'] = '<div id="viewer"></div>';
    $form['#allowed_tags'] = array('div', 'select', 'option','a', 'script');
#    #$form['#attached']['drupalSettings']['wisski_jit'] = $wisski_individual;
    $form['#attached']['library'][] = "wisski_mirador/mirador";

    return $form;
  
  }
  
  
  
  
}     