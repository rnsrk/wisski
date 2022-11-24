<?php

namespace Drupal\wisski_statistics\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

use Drupal\Core\Url;
use Drupal\Core\Link;

use Drupal\wisski_statistics\Controller\WissKiStatisticsController as Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\State;
use Drupal\Core\Messenger\Messenger;
use Drupal\wisski_statistics\Statistics\WisskiStatistics;
use Drupal\wisski_statistics\Render\WisskiStatisticsRenderer;

use Drupal\wisski_statistics\Form\WisskiStatisticsConfigurationForm as Config;

/**
 * Provides a 'Statistics' Block.
 *
 * @Block(
 *   id = "statistics_block",
 *   admin_label = @Translation("WissKI Statistics"),
 *   category = @Translation("WissKI Statistics"),
 * )
 */
class StatisticsBlock extends BlockBase implements ContainerFactoryPluginInterface {


  /**
   * The Drupal messenger
   *
   * @var Drupal\Core\Messenger\Messenger
   */
  protected $messenger;


  /**
   * The Drupal state
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;


  /*
   * The Statistics generator
   *
   * @var Drupal\wisski_statistics\Statistics\WisskiStatistics
   */
  protected $statistics;

  /*
   * The Statistics renderer
   *
   * @var Drupal\wisski_statistics\Render\WisskiStatisticsRenderer
   */
  protected $renderer;

  /**
   * The Settings form
   *
   * @var Drupal\wisski_statistics\Form\WisskiStatisticsConfigurationForm
   */
  protected $configForm;




  /**
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   */
  public function __construct(array $configuration, 
      $plugin_id, 
      $plugin_definition, 
      Messenger $messenger, 
      State $state,
      WisskiStatistics $statistics,
      WisskiStatisticsRenderer $renderer,
      Config $configForm,
      ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->messenger = $messenger;
    $this->state = $state;
    $this->statistics = $statistics;
    $this->renderer = $renderer;
    $this->configForm = $configForm;
    $this->configForm->blockConfig();
    }

  /**
   * {@inheritdoc}
   */
  public static function create(
      ContainerInterface $container, 
      array $configuration, 
      $plugin_id, 
      $plugin_definition,
      ) {
    return new static(
        $configuration,
        $plugin_id,
        $plugin_definition,
        $container->get('messenger'),
        $container->get('state'),
        $container->get('wisski_statistics.statistics'),
        $container->get('wisski_statistics.renderer'),
        $container->get('wisski_statistics.config'),
        );
  }


  /**
   * {@inheritdoc}
   */
  public function build() {
    // read saved statistic values
    $statisticsValues = $this->statistics->read();
    if(!$statisticsValues || empty($statisticsValues)){
      $statisticsValues = $this->statistics->update();
    }

    // read config from state
    $config = $this->configForm->getConfig();
    if(!$config || empty($config)){
      $config = $this->configForm->getDefaultMap();
    }
    return $this->renderer->renderStatistics(new Request(), $config, $statisticsValues);
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    return $this->configForm->buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configForm->submitForm($form, $form_state);
  }
}

