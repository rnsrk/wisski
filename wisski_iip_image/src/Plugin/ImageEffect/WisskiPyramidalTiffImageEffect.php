<?php
/**
 * @file
 * Contains \Drupal\wisski_iip_image\Plugin\ImageEffect\WisskiPyramidalTiffImageEffect.
 */
 
// Ensure the namespace here matches your own modules namespace and directory structure.
namespace Drupal\wisski_iip_image\Plugin\ImageEffect;


// The various classes we will be using for the definition and application of our ImageEffect.
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\image\ImageEffectBase;
use Drupal\image\ConfigurableImageEffectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A description of the image effect plugin.
 * 
 * The annotation below is the mechanism that all plugins use. It allows you to specify metadata
 * about the class. You'll need to update this to match your use case.
 *
 * @ImageEffect(
 *   id = "WisskiPyramidalTiffImageEffect",
 *   label = @Translation("WissKI Pyramidal Tiff Convert "),
 *   description = @Translation("Creates Pyramidal Tiff Derivates.")
 * )
 */
 
class WisskiPyramidalTiffImageEffect extends ImageEffectBase implements ConfigurableImageEffectInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    // Apply any effects to the image here.
  }
               
  /**
   * {@inheritdoc}
   */
  public function getForm() {
    // Return a configuration form to allow the user to set some options.
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ConfigFactoryInterface $config) {
    // The ConfigFactoryInterface is injected from the create method below.
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, array $plugin_definition) {
    // Pull anything out of the container interface and inject it into the plugin's constructor.
    // In this case I have chosen to inject the entire config factory, however you should be as
    // specific as possible when using dependency injection.
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }
                                                      
  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    // Return a summary of the options the user has chosen. This appears after the image effect
    // name in the user interface. I have chosen to specify the option the user has selected inside
    // brackets. This seems to be a convention.
    $quality = $this->configuration['image_jpeg_quality'];
    return array(
      '#markup' => '(' . $quality . '% ' . $this->t('Quality') . ')',
    );
  }
}
                                         
          





