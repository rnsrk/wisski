<?php
 /**
 * @file
 * Definition of Drupal\wisski_iip_image\Plugin\field\formatter\WisskiIIPImageFormatter.
 */
   
  namespace Drupal\wisski_iip_image\Plugin\Field\FieldFormatter;
   
  use Drupal\Core\Field\FieldItemListInterface;
  use Drupal\Core\Field\FormatterBase;
  use Drupal;
  
  /**
   * Plugin implementation of the 'wisski_iip_image' formatter.
   *
   * @FieldFormatter(
   *   id = "wisski_iip_image",
   *   module = "wisski_iip_image",
   *   label = @Translation("WissKI IIP Image Viewer"),
   *   field_types = {
   *     "image"
   *   }
   * )
   */
  class WisskiIIPImageFormatter extends FormatterBase {
    /**
     * {@inheritdoc}
     */
    public function viewElements(Drupal\Core\Field\FieldItemListInterface $items, $langcode) {
      $elements = array();
      dpm($items);
#      $countries = \Drupal::service('country_manager')->getList();
#      foreach ($items as $delta => $item) {
#        if (isset($countries[$item->value])) {
#          $elements[$delta] = array('#markup' => $countries[$item->value]);
#        }
#      }
      return $elements;
    }
    

  }