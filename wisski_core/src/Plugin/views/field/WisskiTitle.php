<?php

namespace Drupal\wisski_core\Plugin\views\field;

use Drupal\Core\Link;
use Drupal\views\ResultRow;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\Url as Urlfield;
use \Drupal\wisski_salz\AdapterHelper;

/**
 * Default implementation of the base field plugin.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("wisski_title")
 */
class WisskiTitle extends Urlfield
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
    if($use_permalink){
      $uri = $this->getUri($values);
      if(!empty($uri)){
        $route = 'entity.wisski_individual.lod_get';
        $parameters = ['uri' => $uri];
      }
    }
    else{
      $eid = $this->getEid($values);
      if(!empty($eid)){
        $route = 'entity.wisski_individual.canonical';
        $parameters = ['wisski_individual' => $eid];
      }
    }


    // handle multiple values
    if(is_array($value)) {
      $return = [];
      foreach ($value as $v) {
        // in case of a disamb-array, go to the value.
        if(is_array($v) && isset($v["value"])){
          $v = $v["value"];
        }
        $return[] = $this->handleRoute($v, $route, $parameters);
      }
      return $return;
    }


    // handle single values
    return $this->handleRoute($value, $route, $parameters);

  }

  /**
   * Handles a route and its parameters and returns it as HTML if valid.
   * Otherwise formats the passed text value as renderable.
   * 
   * @param string $value
   *  The text of the link
   * @param string $route
   *  The route name
   * @param array $prarameters
   *  The route parameteres
   */
  private function handleRoute($value, $route, $parameters){
    if(empty($this->options['display_as_link']) || empty($route) || empty($parameters)){
      return [
        '#type' => 'item',
        '#markup' => $value,
      ];
    }

    $url = Url::fromRoute($route, $parameters);
    return Link::fromTextAndUrl($value, $url)->toRenderable();
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
  private function getEid($values){
    $entity = $values->_entity;

    // if "Entity Id" is set in bundle view
    if(property_exists($values, 'eid')){
      return $values->eid;
    }
    // try getting EID from entity
    if(!empty($entity)){
      return $entity->id();
    }
    // try via URI if "Preferred URI" is set
    if(property_exists($values, 'preferred_uri')){
      $uri = $values->preferred_uri;
      return AdapterHelper::getDrupalIdForUri($uri, false);
    }
    return null;
  }

  /**
   * Attempts to retrieve the URI by searching the 
   * passed $values object:
   * - checks the 'preferred_uri' field
   * - checks the '_entity' field
   * - tries to infer the URI by extracting the EID 
   *   and subsequently querying the AdapterHelper
   * 
   * @return string
   *  The URI or null if none was found.
   */
  private function getUri($values){
    $entity = $values->_entity;

    // if "Preferred URI" is set in bundle view
    if(property_exists($values, 'preferred_uri')){
      return $values->preferred_uri;
    }
    // try getting URI from entity
    if(!empty($entity)){
      $uri = $entity->get('wisski_uri')->value;
      if(!empty($uri)){
        return $uri;
      }
    }
    // try via EID if "Entity Id" is set in bundle view
    if(property_exists($values, 'eid')){
      $eid = $this->getEid($values);
      // take first URI
      return current(AdapterHelper::doGetUrisForDrupalIdAsArray($eid, false));
    }
    return null;
  }
}
