<?php

namespace Drupal\wisski_core\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Render\ViewsRenderPipelineMarkup;
use Drupal\Core\Url;
use Drupal\wisski_salz\AdapterHelper;

/**
 * Default implementation of the base field plugin.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("wisski_preview_image")
 */
class PreviewImage extends FieldPluginBase
{

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values)
  {
    $value = $this->getValue($values);
    $route = null;
    $parameters = null;

    $use_permalink = \Drupal::service('config.factory')->getEditable('wisski_core.settings')->get('use_get_canonical');
    if ($use_permalink) {
      $uri = $this->getUri($values);
      if (!empty($uri)) {
        $route = 'entity.wisski_individual.lod_get';
        $parameters = ['uri' => $uri];
      }
    } else {
      $eid = $this->getEid($values);
      if (!empty($eid)) {
        $route = 'entity.wisski_individual.canonical';
        $parameters = ['wisski_individual' => $eid];
      }
    }


    // handle single values
    if (!is_array($value)) {
      return $this->handleRoute($value, $route, $parameters);
    }

    // handle multiple values
    $return = [];
    foreach ($value as $v) {
      $return[] = $this->handleRoute($v, $route, $parameters);
    }
    return $return;
  }

  /**
   * Handles a route and its parameters and returns it as 
   * ViewsRenderPipelineMarkup.
   * 
   * @param string $value
   *  The text of the link
   * @param string $route
   *  The route name
   * @param array $prarameters
   *  The route parameteres
   * 
   * @return ViewsRenderPipelineMarkup
   *  The renderable markup
   */
  private function handleRoute($value, $route, $parameters)
  {
    if(empty($route) || empty($parameters)){
      return ViewsRenderPipelineMarkup::create($value);
    }

    // extract src from the HTML
    $doc = new \DOMDocument();
    $doc->loadHTML($value);
    $src = $doc->getElementsByTagName('img')->item(0)->getAttribute('src');

    // build Url
    $urlString = Url::fromRoute($route, $parameters)->toString();

    $html = "<a href=\"{$urlString}\"><img src=\"$src\" /></a>";
    return ViewsRenderPipelineMarkup::create($html);
  }


  /**
   * Attempts to retrieve the EID by searching the 
   * passed $values object:
   * - checks the 'eid' field
   * - checks the '_entity' field
   * - tries to infer the EID by extracting the URI
   *   and subsequently querying the AdapterHelper
   * 
   * @return string
   *  The EID or null if none was found.
   */
  private function getEid($values)
  {
    $entity = $values->_entity;

    // if "EID" is set in bundle view
    if (property_exists($values, 'eid')) {
      return $values->eid;
    }
    // try getting EID from entity
    if (!empty($entity)) {
      return $entity->id();
    }
    // extract EID from href if possible
    if (property_exists($values, 'preview_image')) {
      $html = $values->preview_image;
      // extract href from the HTML
      $doc = new \DOMDocument();
      $doc->loadHTML($html);
      $href = $doc->getElementsByTagName('a')->item(0)->getAttribute('href');
      $eids = [];
      if (preg_match('/navigate\/\d+\/view/', $href, $eids)) {
        return explode("/", current($eids), 3)[1];
      }
    }
    // try via URI if "Preferred URI" is set
    if (property_exists($values, 'preferred_uri')) {
      $uri = $values->preferred_uri;
      return AdapterHelper::getDrupalIdForUri($uri, false);
    }
    return null;
  }

  /**
   * Attempts to retrieve the URI by searching the 
   * passed $values object:
   * - checks the 'preferred_uri' field
   * - checks the  '_entity' field
   * - tries to infer the URI by extracting the EID 
   *   and subsequently querying the AdapterHelper
   * 
   * @return string
   *  The URI or null if none was found.
   */
  private function getUri($values)
  {
    $entity = $values->_entity;

    // if "Preferred URI" is set in bundle view
    if (property_exists($values, 'preferred_uri')) {
      return $values->preferred_uri;
    }
    // try getting URI from entity
    if (!empty($entity)) {
      $uri = $entity->get('wisski_uri')->value;
      if (!empty($uri)) {
        return $uri;
      }
    }
    // try via EID if "EID" is set
    if (property_exists($values, 'eid')) {
      $eid = $this->getEid($values);
      // take first URI
      $uris = AdapterHelper::doGetUrisForDrupalIdAsArray($eid, false);
      return current($uris);
    }
    return null;
  }
}
