<?php

namespace Drupal\wisski_statistics\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// depencency injection
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\State\State;
use Drupal\wisski_statistics\Render\WisskiStatisticsRenderer;
use Drupal\wisski_statistics\Statistics\WisskiStatistics;



/**
 * Configuration for statistics.
 */
class WisskiStatisticsConfigurationForm extends ConfigFormBase {
  public const CONFIG = "wisski_statistics.config";

  public const BLOCK = "Block";
  public const PAGE = "Page";


  /**
   * The global configuratio of this form.
   * Either self::BLOCK or self::Page
   *
   * @var string
   */
  protected string $currentConfig;

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

  /**
   * The Statistics generator
   *
   * @var \Drupal\wisski_statistics\Statistics\WisskiStatistics
   */
  protected $statistics;

  /**
   * The Statistics renderer
   *
   * @var \Drupal\wisski_statistics\Render\WisskiStatisticsRenderer
   */
  protected $renderer;


  public function __construct( 
      Messenger $messenger, 
      State $state,
      WisskiStatistics $statistics,
      ) {
    $this->messenger = $messenger;
    $this->state = $state;
    $this->statistics = $statistics;
    // default to
    $this->currentConfig = self::PAGE;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
      ContainerInterface $container,
      ) {
    return new static(
        $container->get('messenger'),
        $container->get('state'),
        $container->get('wisski_statistics.statistics'),
        );
  }

  /** 
   * {@inheritdoc}
   */
  public function getFormId() {
    return self::class;
  }

  /** 
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::CONFIG,
    ];
  }

  /**
   * {inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state){
    // Buttons

    $form['buttons'] = array(
        '#type' => 'container',
        ); 
    $form['buttons']['update'] = array(
        '#type' => 'submit',
        '#value' => t("Update Statistics"),
        '#submit' => array([$this, "updateStatistics"]),
        );
    $form['buttons']['reset_config'] = array(
        '#type' => 'submit',
        '#value' => t("Reset {$this->currentConfig} Configuration"),
        '#submit' => array([$this ,"resetConfiguration"]),
        );

    // get config
    $config = $this->getConfig();
    if(!$config || empty($config)){
      $config = $this->getDefaultMap();
    }

    // build table
    $table = $this->buildTableFromConfig($config);
    // attach to form
    $form = array_merge($form, $table);

    // don't show button if used as block config 
    if($this->currentConfig == self::BLOCK)
      return $form;

    return array_merge($form, array(
          'submit' => array(
            '#type' => 'submit',
            '#value' => t("Save"),
            // makes the button blue, yay
            '#attributes' => ['class' => ["button button--primary js-form-submit form-submit"]]
            ),
        ));
  }

  //-------------------------//
  //  Form Building Helpers  //
  //-------------------------//

  /**
   * Generates a renderable form from a config
   *
   * @see self::getDefaultStatistics()
   *
   * @return array
   *  The form
   */
  protected function buildTableFromConfig($config){
    $class = "table-class";
    $tableTemplate= array(
        '#type' => 'table',
        '#empty' => $this->t('No items.'),
        '#tabledrag' => [[
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => $class,
        ]]
        );
    $form['groups'] = $tableTemplate;
    $form['groups']['#header'] = array(
        t("Label"),
        t('Statistics'),
        t('Weight'),
        );

    foreach($config as $groupName => $stats){
      $group = array('#type' => "container");

      $label = toLabel($groupName);
      $group['label'] = array(
          '#type' => "markup",
          '#markup' => $label
          );

      $group['#attributes']['class'][] = 'draggable';
      $group['table'] = $tableTemplate;

      $group['weight'] = array(
          '#type' => 'weight',
          '#title' => $this->t('Weight for @title', ['@title' => $label]),
          '#title_display' => 'invisible',
          '#default_value' => 0,
          '#attributes' => ['class' => [$class]],
          );

      foreach($stats as $function => $attributes){
        $group['table'][$function] = $this->createRow($function, $attributes);
      }
      $form['groups'][$groupName] = $group;
    }

    // attach css to line up the table columns
    $form['#attached']['library'][] = 'wisski_statistics/wisski_statistics';
    return $form;
  }


  /**
   * Creates a draggable row for a table.
   *
   * @param string $function
   *  The name of the function computing the value.
   * @param array attributes
   *  The function attributes for the checkbox.
   * @param string $class
   *  The class attached to the row weights
   *
   * @return array
   *  The table row.
   */
  protected function createRow($function, $attributes, $class = 'table-class'){
    $enabled = $attributes['enabled'];

    $row['#attributes']['class'][] = 'draggable';
    $row['label'] = [
      '#markup' => toLabel($function),
    ];
    $row['enabled'] = array(
        '#type' => 'checkbox',
        '#title' => t("Enable"),
        '#default_value' => $enabled,
        );

    $row['weight'] = array(
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $function]),
        '#title_display' => 'invisible',
        '#default_value' => 0,
        '#attributes' => ['class' => [$class]],
        );
    return $row;
  }


  /**
   * Converts the values from $form_state to a config array.
   *
   * @param array $values
   *  The values from the $form_state
   *
   * @return array
   *  The configuration
   *
   * @see self::getDefaultMap()
   * @see WisskiStatistics::fullStatisticsMap()
   */
  protected function valuesToMap(array $values){
    $groups = $values['groups'];
    $map = [];
    $statistics = WisskiStatistics::fullStatisticsMap();
    foreach($groups as $groupName => $groupAttributes){
      foreach($groupAttributes['table'] as $function => $statAttributes){
        $map[$groupName][$function]['enabled'] = $statAttributes['enabled'] == 1 ? true : false;
        $map[$groupName][$function]['type'] = $statistics[$groupName][$function]['type'];
        foreach($statistics[$groupName][$function] as $key => $value){
          $map[$groupName][$function][$key] = $value;
        }
      }
    }
    return $map;
  }

  //--------------------------//
  //  Global Config Handling  //
  //--------------------------//
 
  /**  
   * Makes this form handle Statistics Block configuration.
   */
  public function blockConfig(){
    $this->currentConfig = self::BLOCK;
  }

  /**
   * Makes this form handle the Statistics Page configuration.
   * Enabled by default.
   */
  public function pageConfig(){
    $this->currentConfig = self::PAGE;
  }

  //------------------------------//
  //  Statistics Config Handling  //
  //------------------------------//

  /**
   * Returns the current statistics configuration
   *
   * @return array
   *  The statistics configuration
   */
  public function getConfig(){
    return $this->config(self::CONFIG)->get($this->currentConfig);
  }

  /**
   * Sets the given statistics configuration as current configuration.
   *
   * @param $value array
   *  The new statistics configuration
   */
  public function saveConfig(array $value){
    $this->config(self::CONFIG)->set($this->currentConfig, $value)->save();
  }

  /**
   * Generates the default statistics configuration
   * for the current setup (Block / Page)
   * Enables all available statistics by default.
   *
   * @return array
   *  The default statistics configuration
   *
   *  @see WisskiStatistics::fullStatisticsMap
   *  @see WisskiStatistics::scalarStatisticsMap
   */
  public function getDefaultMap(){
    switch ($this->currentConfig) {
      case self::PAGE:
        $config = WisskiStatistics::fullStatisticsMap();
        break;
      case self::BLOCK:
        $config = WisskiStatistics::scalarStatisticsMap();
        break;
      default:
        return [];
    }

    foreach($config as $groupName => $stats){
      foreach($stats as $function => $attributes){
        $config[$groupName][$function]['enabled'] = true;
      }   
    }   
    return $config;
  }


  //--------------------//
  //  Submit Callbacks  //
  //--------------------//

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = $this->valuesToMap($values);

    $this->saveConfig($config);

    if($this->currentConfig == self::PAGE){
      $this->messenger()->addMessage(t("Configuration saved!"));
    }
    
    // clear the drupal page_cache entries
    $database = \Drupal::database();
    $database->delete("cache_page")
      ->condition('cid', "%" . $database->escapeLike("wisski/statistics:"), "LIKE")
      ->execute();
    $database->delete("cache_dynamic_page_cache")
      ->condition('cid', "%" . $database->escapeLike("wisski.wisski_statistics") . "%", "LIKE")
      ->execute();

    $form_state->setRedirect('wisski.wisski_statistics');
  }

  /**
   * Forces statistic generation
   */
  public function updateStatistics(&$form, FormStateInterface $form_state){
    $this->statistics->update();
    $this->messenger()->addMessage(t("Updated Statistics"));
    $form_state->setRebuild();
  }

  /**
   * Resets the statistics configuration 
   */
  public function resetConfiguration(&$form, FormStateInterface &$form_state){
    $defaultConfig = $this->getDefaultMap();
    $this->saveConfig($defaultConfig);
    $this->messenger()->addMessage(t("Reset {$this->currentConfig} Config"));
    $form_state->setRebuild();
    $form_state->setUserInput([]);
  }

}

