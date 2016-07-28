<?php
/**
 * @file
 * Contains \Drupal\wisski_apus\Controller\InfoboxController.
 */
 
namespace Drupal\wisski_apus\Controller;
 
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\HtmlResponse;
use Drupal\wisski_salz\AdapterHelper;
use Drupal\wisski_core\WisskiStorage;
use Drupal\wisski_core\WisskiCacheHelper;

 
class InfoboxController extends ControllerBase {
  
  
  public function content () {
      
    $anno = $_GET['anno'];
    $content = NULL;
    
    if (isset($anno['target']['ref'])) {
      
      $content = $this->refContent($anno);
    } elseif (isset($anno['target']['type'])) {
      $content = $this->typeContent($anno);
    } else {
      $content = $this->t('No information available.');
    }

    // TODO: don't cache!
    $response = new HtmlResponse($content);

    return $response;
      
  }
  

  private function typeContent($anno) {
    return $this->t('This annotation points to an unspecified instance classified as %c.', array('%c' => $anno['target']['type']));
  }


  private function refContent($anno) {
    
    $uri = $anno['target']['ref'];
    // TODO: we currently handle only one entity per annotation
    if (is_array($uri)) $uri = $uri[0];
file_put_contents('/local/logs/dev8.log',"\n$uri\n", FILE_APPEND);      
    $id = AdapterHelper::getDrupalIdForUri($uri);
    
/*    $indiv = entity_load('wisski_individual', $id);
    $view = entity_view($indiv, 'wisski_individual.infobox');
    
    $content = \Drupal::service('renderer')->render($view);
    
    return $content;
*/    
file_put_contents('/local/logs/dev8.log',"\n$id\n", FILE_APPEND);      
    $image = WisskiCacheHelper::getPreviewImage($id);
file_put_contents('/local/logs/dev8.log',"\n$id\n", FILE_APPEND);      
    $label = WisskiCacheHelper::getEntityTitle($id);
file_put_contents('/local/logs/dev8.log',"\n$id\n", FILE_APPEND);      


    return '<h3>' . $label . '</h3><img src="' . $image . '" />';
    return array(
      'label' => array(
        '#value' => '<h3>' . $label . '</h3>',
      ),
      'image' => array(
        '#value' => '<img src="' . $image . '" />',
      ),
    );


    return $this->t(
      'This annotation points to instance %i@c.', 
      array(
        '%i' => $label,
        '@c' => isset($anno['targetType']) ?
                $this->t(' and is classified as %c', array('%c' => $anno['target']['Type'])) :
                '',
      )
    );
  }


}


?>
