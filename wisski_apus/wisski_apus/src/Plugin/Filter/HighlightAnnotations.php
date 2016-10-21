<?php

namespace Drupal\wisski_apus\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\wisski_apus\AnnotationHelper;
use Drupal\wisski_core\WisskiCacheHelper;


/**
 * @Filter(
 *   id = "wisski_apus_highlight",
 *   title = @Translation("WissKI Highlight Annotations Filter"),
 *   description = @Translation(""),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 * )
 */
class HighlightAnnotations extends FilterBase {
  public function process($text, $langcode) {
    
    if (preg_match_all('/href=(?:"(?:[^"]+)"|\'(?:[^\'])+\')/', $text, $matches)) {
      foreach (array_unique($matches[0]) as $match) {
        
        // take the URL and decode html entities
        $url = Html::decodeEntities(mb_substr($match, 6, -1));
        
        // do a best guess to find out whether this URL points to a
        // WIssKi entity and if the URL encodes bundle information
        list($entity_id, $bundle_id) = AnnotationHelper::getEntityAndBundleIdFromUrl($url);
        
        // if there is no bundle information we take the last one
        // used by the user
        if (!$bundle_id) {
          $bundle_id = WisskiCacheHelper::getCallingBundle($entity_id);
        }
        
        // if there is still no bundle information, we cannot really hilite it
        if (!$bundle_id) {
          continue;
        }
        
        // we add attributes to get the hiliting right
        $insert = 'data-wisski-anno="oac" data-wisski-target-ref="' . $url . '" data-wisski-anno-bundle="' . $bundle_id . '"';
        
        // ... and place the attrs in the element after the match
        $text = join("$match $insert", explode($match, $text));
        
      }
    }
    
    // prepare the filter result
    $result = new FilterProcessResult($text);

    // add libraries (js+css)
    $result->addAttachments(array(
      'library' => array(
        'wisski_apus/highlight',
        'wisski_apus/infobox'
      )
    ));

    return $result;

  }

  

}
