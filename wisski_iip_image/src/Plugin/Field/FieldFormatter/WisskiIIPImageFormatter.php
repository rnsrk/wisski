<?php
 /**
 * @file
 * Definition of Drupal\wisski_iip_image\Plugin\field\formatter\WisskiIIPImageFormatter.
 */
   
  namespace Drupal\wisski_iip_image\Plugin\Field\FieldFormatter;
   
  use Drupal\Core\Entity\EntityStorageInterface;
  use Drupal\Core\Field\FieldItemListInterface;
  use Drupal\Core\Field\FieldDefinitionInterface;
  use Drupal\Core\Link;
  use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
  use Drupal\Core\Session\AccountInterface;
  use Drupal\Core\Url;
  use Drupal\image\Entity\ImageStyle;
  use Symfony\Component\DependencyInjection\ContainerInterface;
  use Drupal\Core\Form\FormStateInterface;
  use Drupal\Core\Cache\Cache;
  use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatterBase;
  use Drupal\colorbox\Plugin\Field\FieldFormatter\ColorboxFormatter;
  
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
#  class WisskiIIPImageFormatter extends ImageFormatterBase {
  class WisskiIIPImageFormatter extends ColorboxFormatter {
    /**
     * {@inheritdoc}
     */
    public function viewElements(FieldItemListInterface $items, $langcode) {

      dpm($this->attachment);
      $elements = parent::viewElements($items, $langcode);    
      
      $elements['#attached']['library'][] = 'wisski_iip_image/iipmooviewer';
      $elements['#attached']['library'][] = 'wisski_iip_image/iip_integration';      
      dpm($elements);

      $script = '<script type="text/javascript">
        $(document).ready(function() {
        var server = "/fcgi-bin/iipsrv.fcgi";
        var images = ["' . $imagepath . '"];
        var credit = \'&copy; <a href="http://www.gnm.de/">Germanisches Nationalmuseum</a>\';
        var iipmooviewer = new IIPMooViewer( "viewer", {
          image: images,
          server: server,
          credit: credit,
          prefix: \'' . $base_path . drupal_get_path('module', 'wisski_iip') . '/iipmooviewer/images/\',
          ' . $scale . '
          showNavWindow: true,
          showNavButtons: true,
          winResize: true,
          protocol: \'iip\',
        });
      });</script>';
                                                                                    

      return $elements;


/*      
      $elements = array();
      $files = $this->getEntitiesToView($items, $langcode);
#     drupal_set_message(serialize($files));

    // Early opt-out if the field is empty.
    if (empty($files)) {
      return $elements;
    }

    $image_style_name = 'wisski_pyramid';

    if(! $image_style = \Drupal\image\Entity\ImageStyle::load($image_style_name)) {
      $values = array('name'=>$image_style_name,'label'=>'Wisski Pyramid Style');
      $image_style = \Drupal\image\Entity\ImageStyle::create($values);
#      $image_style->addImageEffect('WisskiPyramidalTiffImageEffect', array());
      $image_style->save();
    }
    
#    drupal_set_message("image_style: " . serialize($image_style));

#    $image_uri = ImageStyle::load('your_style-name')->buildUrl($file->getFileUri());
#    drupal_set_message(serialize($
        
    
    

    $url = NULL;
    $image_link_setting = $this->getSetting('image_link');
    // Check if the formatter involves a link.
    if ($image_link_setting == 'content') {
      $entity = $items->getEntity();
      if (!$entity->isNew()) {
        $url = $entity->urlInfo();
      }
    }
    elseif ($image_link_setting == 'file') {
      $link_file = TRUE;
    }

    $image_style_setting = $this->getSetting('image_style');

    // Collect cache tags to be added for each item in the field.
    $cache_tags = array();
    if (!empty($image_style_setting)) {
      $image_style = $this->imageStyleStorage->load($image_style_setting);
      $cache_tags = $image_style->getCacheTags();
    }

    foreach ($files as $delta => $file) {
 
      $image_uri = ImageStyle::load('wisski_pyramid')->buildUri($file->getFileUri());
      drupal_set_message(serialize($image_uri));
      
      drupal_set_message("1: " . serialize($file->getFileUri()));
      drupal_set_message("2: " . serialize($image_uri));
      drupal_set_message("cd: " . serialize($image_style->createDerivative($file->getFileUri(),$image_uri)));
      
      drupal_set_message(serialize($image_style));
      $url = Url::fromUri(file_create_url($image_uri));     
 
      $cache_contexts = array();
      if (isset($link_file)) {
        $image_uri = $file->getFileUri();
        // @todo Wrap in file_url_transform_relative(). This is currently
        // impossible. As a work-around, we currently add the 'url.site' cache
        // context to ensure different file URLs are generated for different
        // sites in a multisite setup, including HTTP and HTTPS versions of the
        // same site. Fix in https://www.drupal.org/node/2646744.
        $url = Url::fromUri(file_create_url($image_uri));
        $cache_contexts[] = 'url.site';
      }
      $cache_tags = Cache::mergeTags($cache_tags, $file->getCacheTags());

      // Extract field item attributes for the theme function, and unset them
      // from the $item so that the field template does not re-render them.
      $item = $file->_referringItem;
      $item_attributes = $item->_attributes;
      unset($item->_attributes);

#      drupal_set_message("url: " . serialize($item));

      drupal_set_message("miauz: " . serialize($items->getEntity()));

      $elements[$delta] = array(
#        '#theme' => 'image_formatter',
        '#theme' => 'colorbox_formatter',
        '#item' => $item,
        '#item_attributes' => $item_attributes,
        '#entity' => $items->getEntity(),
#        '#image_style' => $image_style_setting,
#        '#url' => $url,
        '#cache' => array(
          'tags' => $cache_tags,
          'contexts' => $cache_contexts,
        ),
      );
    }

    return $elements;
*/
    }
    

  }