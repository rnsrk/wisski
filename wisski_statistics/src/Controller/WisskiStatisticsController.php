<?php

namespace Drupal\wisski_statistics\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;

use Drupal\Core\Utility\TableSort;
use Drupal\Core\Form\FormStateInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// depencency injection
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\mysql\Driver\Database\mysql\Connection;
use Drupal\Component\Datetime\Time;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\State\State;
use Drupal\Core\Messenger\Messenger;

use Drupal\wisski_statistics\Statistics\WisskiStatistics;
use Drupal\wisski_statistics\Render\WisskiStatisticsRenderer;
use Drupal\wisski_statistics\Form\WisskiStatisticsConfigurationForm as Config;

/**
 * Controller routines for statistics.
 * Adaption from https://git.drupalcode.org/project/entity_count/-/blob/8.x-1.x/src/Controller/EntityCountController.php
 */
class WisskiStatisticsController extends ControllerBase {

  protected $state;

  /**
   * The date formatter
   *
   * @var Drupal\Core\Datetime\DateFormatter
   */
  protected $statistics;

  /**
   * The Drupal state
   *
   * @var Drupal\Core\State\State
   */
  protected $renderer;

  /**
   * The Configuration form
   *
   * @var Drupal\wisski_statistics\Form\WisskiStatisticsConfigurationForm
   */
  protected $configForm;


  /**
   * The constructor.
   */
  public function __construct(
      State $state,
      WisskiStatistics $statistics,
      WisskiStatisticsRenderer $renderer,
      Config $configForm,
      ) {
    $this->state = $state;
    $this->statistics = $statistics;
    $this->renderer = $renderer;
    $this->configForm = $configForm;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('state'),
        $container->get('wisski_statistics.statistics'),
        $container->get('wisski_statistics.renderer'),
        $container->get('wisski_statistics.config'),
        );
  }

  /**
   * Renders the statistics page
   */
  public function statistics(Request $request){
    // read saved statistic values from
    $statisticsValues = $this->statistics->read();
    
    // read config from state
    $config = $this->configForm->getConfig();
    if(!$config || empty($config)){
      $config = $this->configForm->getDefaultMap();
    }
    return $this->renderer->renderStatistics($request, $config, $statisticsValues);
  }

  /**
   * Renders a single statistics group 
   */
  public function statisticsGroup(Request $request, $groupName, $useConfig = false){
    $config = $this->configForm->getDefaultMap();

    if($useConfig){
      $savedConfig = $this->configForm->getConfig();
      $config = $savedConfig ?? $config;
    }

    if(!array_key_exists($groupName, $config)){
      throw new NotFoundHttpException();
    }
    $statisticsValues = $this->statistics->read();
    $newConfig[$groupName] = $config[$groupName];
    return $this->renderer->renderStatistics($request, $newConfig, $statisticsValues);
  }

  /**
   * Returns a link to the Statistics Page
   * Used for displaying the title as a link
   * using _title_callback in routing.yml 
   */
  public static function linkToStatistics($label = null){
    $label = $label ?? "Statistics";
    $url = Url::fromRoute('wisski.wisski_statistics');
    $link = Link::fromTextAndUrl($label, $url);
    return $link->toRenderable();
  }
}


