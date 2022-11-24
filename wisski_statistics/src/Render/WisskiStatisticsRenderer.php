<?php

namespace Drupal\wisski_statistics\Render;

use Drupal\Core\Url;
use Drupal\Core\Link;

use Drupal\Core\Utility\TableSort;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// depencency injection
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\State\State;
use Drupal\Core\Messenger\Messenger;

use Drupal\wisski_statistics\Statistics\StatisticType;

/**
 * Controller routines for statistics.
 * Adaption from https://git.drupalcode.org/project/entity_count/-/blob/8.x-1.x/src/Controller/EntityCountController.php
 */
class WisskiStatisticsRenderer {

  /**
   * The date formatter
   *
   * @var Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The Drupal state
   *
   * @var Drupal\Core\State\State
   */
  protected $state;

  /**
   * The Drupal messenger
   *
   * @var Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * The constructor.
   */
  public function __construct(
      DateFormatter $dateFormatter,
      State $state,
      Messenger $messenger,
      ) {
    $this->dateFormatter = $dateFormatter;
    $this->state = $state;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('date.formatter'),
        $container->get('state'),
        $container->get('messenger'),
        );
  }


  //------------------------//
  //  Statistics Rendering  //
  //------------------------//


  /**
   * Renders a single statistics group 
   */
  function renderStatisticsGroup(Request $request, $groupName){
    $map = self::defaultStatisticsMap();
    if(!array_key_exists($groupName, $map)){
      throw new NotFoundHttpException();
    }
    $newMap[$groupName] = $map[$groupName];
    return $this->renderStatistics($request, $newMap);
  }


  /**
   * Renders the statistics.
   *
   * @param Request $request
   *  The HTTP request. Only used for sorting tables.
   *
   * @param array $map
   *  The statistics map @see WissKiStatisticsController::defaultStatisticsMap()
   *
   * @param array $statistics
   *  The statistics values as from WissKiStatisticsController::generateStatistics()
   *
   * @return array
   *  A render array.
   */
  function renderStatistics(Request $request, array $config, array $statisticsValues) : array {
    $render = [];
    //$map = $this->statisticsMap($statistics);

    foreach($config as $groupName => $stat){
      if(!array_key_exists($groupName, $config))
        continue;
      // initialize container with header and label if present
      $group = array(
          '#type' => 'container',
          '#suffix' => '<br>'
          );


      // format the heading as links to the separate group page
      $url = Url::fromRoute('wisski.wisski_statistics.groups', ['groupName' => $groupName]);
      $link = Link::fromTextAndUrl(toLabel($groupName), $url);
      $group['heading'] = $link->toRenderable();
      $tag = "h3";
      $group['heading']['#prefix'] = "<$tag>";
      $group['heading']['#suffix'] = "</$tag>";


      $statCount = 0;
      foreach($stat as $function => $attributes){
        if(!$attributes['enabled'] || !array_key_exists($function, $statisticsValues[$groupName]))
          continue;

        // take value from the passed statistics
        $value = $statisticsValues[$groupName][$function];
        $statisticType = $attributes['type']; 

        $renderedStatistic = [];
        // check for extra UI function
        if(method_exists(__CLASS__, $function . "Ui")){
          $renderedStatistic = [$this, $function . "Ui"]($request, $config, $groupName, $function, $value);
        }
        // handle table statistics 
        elseif($statisticType == StatisticType::TABLE){
          $sort = array_key_exists('sort', $attributes) ? $attributes['sort'] : null;
          $table = $this->toSortableTable($attributes['columns'], $value, $sort);
          $renderedStatistic = $this->sortTable($table, $request);
        }
        // handle scalar statistics
        else{
          $label = toLabel($function);
          $formattedValue = $this->formatValue($value, $statisticType);
          $renderedStatistic = $this->toHTMLTag("$label: $formattedValue");
        }
        $group[$function] = $renderedStatistic;
        $statCount++;
      }
      // dont render groups that have no statistics
      if($statCount > 0){
        $render[$groupName] = $group;
      }
    }
    return $render;
  }


  //-----------------------//
  //  Datatype Formatters  //
  //-----------------------//

  protected function formatValue($value, $statisticType){
    switch ($statisticType){
      case StatisticType::DATE:
        $formattedValue = $this->formatDate($value);
        break;
      case StatisticType::BOOLEAN:
        $formattedValue = $this->formatBoolean($value);
        break;
      case StatisticType::LINK:
        $url = Url::fromUri($value);
        $formattedValue = Link::fromTextAndUrl($value, $url)->toString();
        break;
      case StatisticType::WISSKI_BUNDLE_ID:
        $formattedValue = Link::createFromRoute($value, "view.$value.$value");
        break;
      default:
        $formattedValue = $value;
        break;
    }
    return $formattedValue;
  }

  protected function formatDate($timestamp){
    return $timestamp ? $this->dateFormatter->format($timestamp) : "";
  }

  protected function formatBoolean($boolean){
    return $boolean ? html_entity_decode("&#9989;") : html_entity_decode("&#10060;");
  }

  //----------------------//
  //  Custom Ui Functions //
  //----------------------//

  protected function bundleStatisticsUiTest(Request $request, $config, $groupName, $function, $value){
    $mainBundleRows = [];
    $subBundleRows = [];

    foreach($value as $row){
      $isMainBundle = $row['mainBundle'];
      if($isMainBundle){
        $mainBundleRows[] = $row;
        continue;
      }
      $subBundleRows[] = $row;
    }

    $mainBundleTable = $this->toSortableTable($config[$groupName][$function]['columns'], $mainBundleRows);
    $render[] = $this->sortTable($mainBundleTable, $request);

    $subBundleTable = $this->toSortableTable($config[$groupName][$function]['columns'], $subBundleRows);
    $render[] = $this->sortTable($subBundleTable, $request);

    return $render;
  }

  //--------------//
  //  UI Helpers  //
  //--------------//

  /**
   * Converts headers and rows to a sortable 'table' RenderArray
   *
   * @param array $headers
   *  The headers for the table.
   * @param array $rows
   *  The content for the rows.
   *
   * @return array
   *  The 'table' RenderArray
   */
  protected function toSortableTable(array $columns, array $rows, array $sort = null) : array {
    $formattedHeader = [];
    $formattedRows = [];

    // format headers
    foreach($columns as $function => $statisticType){
      $header =  [
        'data' => toLabel($function),
        'field' => $function,
      ];
      // if a default sort is set add it
      if($sort && count($sort) == 2 && $sort[0] == $function){
        $header['sort'] = $sort[1];
      }
      $formattedHeader[] = $header;

    }

    // format and reorder according to columns
    foreach($rows as $row){
      $formattedRow = [];
      foreach($columns as $function => $statisticType){
        $formattedRow[$function] = $this->formatValue($row[$function], $statisticType);
      }
      $formattedRows[] = ['data' => $formattedRow ];
    }

    return [
      '#type' => 'table',
      '#header' => $formattedHeader,
      '#rows' => $formattedRows,
    ];
  }

  /**
   * Sorts a table according to URL parameters of the request.
   *
   * @param array $table
   *  Table RenderArray.
   * @param Request $request
   *  The HTTP request.
   *
   * @return array
   *  The sorted table RenderArray
   */
  protected function sortTable(array $table, Request $request) : array{
    $rows = $table['#rows'];
    $header = $table['#header'];
    $firstHeader = $header[0]['field'];
    // get the column and ordering
    $order = TableSort::getOrder($header, $request);
    $sort = TableSort::getSort($header, $request);
    if (!empty($order) && $order['sql']){
      $field = $order['sql'];
      if (!empty($sort)){
        $desc = function ($a, $b) use ($field){
          return $b['data'][$field] <=> $a['data'][$field];
        };
        $asc = function ($a, $b) use ($field){
          return $a['data'][$field] <=> $b['data'][$field];
        };
        $sort == 'desc' ? usort($rows, $desc) : usort($rows, $asc);
      }
    }
    else {
      usort($rows, function ($a, $b) use ($firstHeader){
          return strcmp($a['data'][$firstHeader], $b['data'][$firstHeader]);
          });
    }

    $table['#rows'] = $rows;
    return $table;
  }

  /**
   * Wraps a string with a 'html_tag' RenderArray
   *
   * @param string $string
   *  The string to be wrapped.
   * @param string $tag
   *  The tag to be used.
   *
   * @return array
   *  'html_tag' RenderArray
   */
  function toHTMLTag(string $string, string $tag = "div") : array {
    return array(
        '#type' => 'html_tag',
        '#tag' => $tag,
        '#value' => $string,
        );
  }

}
