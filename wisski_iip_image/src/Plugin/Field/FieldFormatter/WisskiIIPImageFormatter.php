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

      $files = $this->getEntitiesToView($items, $langcode);

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

      foreach ($files as $delta => $file) {
 
        $image_uri = ImageStyle::load('wisski_pyramid')->buildUri($file->getFileUri());
        $image_style->createDerivative($file->getFileUri(),$image_uri);

#        $url = Url::fromUri(file_create_url($image_uri));     

      }
#      dpm($elements);

      return $elements;

    }
    

  }