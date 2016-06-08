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
  use Drupal\Core\Template\Attribute;
    
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

      $elements = parent::viewElements($items, $langcode);    
      
      $elements['#attached']['library'][] = 'wisski_iip_image/iipmooviewer';
      $elements['#attached']['library'][] = 'wisski_iip_image/iip_integration';      

      dpm($elements);

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
        $image_style->addImageEffect(array('id' => 'WisskiPyramidalTiffImageEffect'));
        $image_style->save();
      }
    
#    drupal_set_message("image_style: " . serialize($image_style));

#    $image_uri = ImageStyle::load('your_style-name')->buildUrl($file->getFileUri());
#    drupal_set_message(serialize($
        
    
    

#      $url = NULL;
#      $image_link_setting = $this->getSetting('image_link');
#      // Check if the formatter involves a link.
#      if ($image_link_setting == 'content') {
#        $entity = $items->getEntity();
#        if (!$entity->isNew()) {
#          $url = $entity->urlInfo();
#        }
#      }
#      elseif ($image_link_setting == 'file') {
#        $link_file = TRUE;
#      }

#      $image_style_setting = $this->getSetting('image_style');

#      // Collect cache tags to be added for each item in the field.
#      $cache_tags = array();
#      if (!empty($image_style_setting)) {
#        $image_style = $this->imageStyleStorage->load($image_style_setting);
#        $cache_tags = $image_style->getCacheTags();
#      }

      foreach ($files as $delta => $file) {
 
        $image_uri = ImageStyle::load('wisski_pyramid')->buildUri($file->getFileUri());
        $image_style->createDerivative($file->getFileUri(),$image_uri);
#        drupal_set_message(serialize($image_uri));
      
#      drupal_set_message("1: " . serialize($file->getFileUri()));
#      drupal_set_message("2: " . serialize($image_uri));
#      drupal_set_message("cd: " . serialize($image_style->createDerivative($file->getFileUri(),$image_uri)));
#      
 #     drupal_set_message(serialize($image_style));
# $url = '/srv/www/htdocs/dev/sites/default/files/styles/wisski_pyramid/public/de514d90b6b6862363818e117ebf7e59.jpg';
        $url = Url::fromUri(file_create_url($image_uri));     
#$url = drupal_realpath($image_uri);
 
#        $cache_contexts = array();
#        if (isset($link_file)) {
#          $image_uri = $file->getFileUri();
#          // @todo Wrap in file_url_transform_relative(). This is currently
#          // impossible. As a work-around, we currently add the 'url.site' cache
#          // context to ensure different file URLs are generated for different
#          // sites in a multisite setup, including HTTP and HTTPS versions of the
#          // same site. Fix in https://www.drupal.org/node/2646744.
#          $url = Url::fromUri(file_create_url($image_uri));
#          $cache_contexts[] = 'url.site';
#        }
#        $cache_tags = Cache::mergeTags($cache_tags, $file->getCacheTags());

        // Extract field item attributes for the theme function, and unset them
        // from the $item so that the field template does not re-render them.
#        $item = $file->_referringItem;
#        $item_attributes = $item->_attributes;
#        unset($item->_attributes);

#      drupal_set_message("url: " . serialize($item));

#        drupal_set_message("miauz: " . serialize($items->getEntity()));

#          $elements[$delta]['#item'] = $item;
#          $elements[$delta]['#entity'] = $items->getEntity();
#          $elements[$delta]['#item_attributes'] = $item_attributes;
#          $elements[$delta]['#theme'] = 'image_formatter';
#          $item = $elements[$delta]['#item'];
#          $item->entity->set('uri', "mi://au.de");
#          $elements[$delta]['#item'] = $item;
          
          #$attributes = $elements[$delta]['#item_attributes'];
          
          #$attributes['iip_path'] = drupal_realpath($image_uri);
          
#          $elements[$delta]['#item_attributes'] = array('longdesc' => 'blue');
          
          #$elements[$delta]['#item']->_attributes = array('class' => 'blue');
          
          
          #dpm($attributes);
          
          #$elements[$delta]['#url'] = $url;
#          $elements[$delta]['#settings']['colorbox_image_style'] = 'wisski_pyramid';
          #$elements[$delta]['#iip_path'] = drupal_realpath($image_uri);          
          
/*
        $elements[$delta] = array(
#        '#theme' => 'image_formatter',
          '#theme' => 'colorbox_formatter',
          '#item' => $item,
          '#item_attributes' => $item_attributes,
          '#entity' => $items->getEntity(),
#          '#image_style' => $image_style_setting,
#          '#url' => $url,
          '#cache' => array(
            'tags' => $cache_tags,
            'contexts' => $cache_contexts,
          ),
        );
        */
      }
      dpm($elements);

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