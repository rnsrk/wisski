<?php

namespace Drupal\wisski_adapter_gnd\Plugin\wisski_salz\Engine;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\wisski_adapter_gnd\Query\Query;
use Drupal\wisski_pathbuilder\Entity\WisskiPathEntity;
use Drupal\wisski_pathbuilder\PathbuilderEngineInterface;
use Drupal\wisski_salz\NonWritableEngineBase;
use Drupal\wisski_salz\AdapterHelper;
use EasyRdf_Graph;
use EasyRdf_Namespace;
use EasyRdf_Literal;

/**
 * Wiki implementation of an external entity storage client.
 *
 * @Engine(
 *   id = "gnd",
 *   name = @Translation("DNB GND"),
 *   description = @Translation("Provides access to the Gemeinsame Normdatei of the Deutsche Nationalbibliothek")
 * )
 */
class GndEngine extends NonWritableEngineBase implements PathbuilderEngineInterface {

  protected $uriPattern = "!^http://d-nb.info/gnd/(.+)$!u";
  protected $fetchTemplate = "http://d-nb.info/gnd/{id}/about/lds";

  /**
   * Workaround for super-annoying easyrdf buggy behavior:
   * it will only work on prefixed properties.
   */
  protected $rdfNamespaces = [
    'gnd' => 'http://d-nb.info/standards/elementset/gnd#',
    'geo' => 'http://www.opengis.net/ont/geosparql#',
    'sf' => 'http://www.opengis.net/ont/sf#',
  ];



  protected $possibleSteps = [
    'ConferenceOrEvent' => [
      'gnd:preferredNameForTheConferenceOrEvent' => NULL,
      'gnd:variantNameForTheConferenceOrEvent' => NULL,
    ],
    'CorporateBody' => [
      'gnd:preferredNameForTheCorporateBody' => NULL,
      'gnd:variantNameForTheCorporateBody' => NULL,
    ],
    'Family' => [
      'gnd:preferredNameForTheFamily' => NULL,
      'gnd:variantNameForTheFamily' => NULL,
    ],
    'Person' => [
      'gnd:preferredNameForThePerson' => NULL,
      'gnd:variantNameForThePerson' => NULL,
    ],
    'PlaceOrGeographicName' => [
      'gnd:preferredNameForThePlaceOrGeographicName' => NULL,
      'gnd:variantNameForThePlaceOrGeographicName' => NULL,
    ],
    'TerritorialCorporateBodyOrAdministrativeUnit' => [
      'gnd:preferredNameForThePlaceOrGeographicName' => NULL,
      'gnd:variantNameForThePlaceOrGeographicName' => NULL,
      'geo:hasGeometry geo:asWKT' => NULL,
    ],
    'SubjectHeading' => [
      'gnd:preferredNameForTheSubjectHeading' => NULL,
      'gnd:variantNameForTheSubjectHeading' => NULL,
    ],
    'Work' => [
      'gnd:preferredNameForTheWork' => NULL,
      'gnd:variantNameForTheWork' => NULL,
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function hasEntity($entity_id) {
    // Use the new function.
    $uris = AdapterHelper::doGetUrisForDrupalIdAsArray($entity_id);
    if (empty($uris)) {
      return FALSE;
    }
    foreach ($uris as $uri) {
      // fetchData also checks if the URI matches the GND URI pattern
      // and if so tries to get the data.
      if ($this->fetchData($uri)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   *
   */
  public function fetchData($uri = NULL, $id = NULL) {

    if (!$id) {
      if (!$uri) {
        return FALSE;
      }
      elseif (preg_match($this->uriPattern, $uri, $matches)) {
        $id = $matches[1];
      }
      else {
        // Not a URI.
        return FALSE;
      }
    }

    $cache = \Drupal::cache('wisski_adapter_gnd');
    $data = $cache->get($id);
    if ($data) {
      return $data->data;
    }

    $replaces = [
      '{id}' => $id,
    ];
    $fetchUrl = strtr($this->fetchTemplate, $replaces);

    $data = file_get_contents($fetchUrl);
    if ($data === FALSE || empty($data)) {
      return FALSE;
    }

    // $doc = new DOMDocument();
    // if (!$doc->load($fetchUrl)) {
    // return FALSE;
    // }.
    $graph = new EasyRdf_Graph($fetchUrl, $data, 'turtle');
    if ($graph->countTriples() == 0) {
      return FALSE;
    }

    foreach ($this->rdfNamespaces as $prefix => $ns) {
      EasyRdf_Namespace::set($prefix, $ns);
    }

    $data = [];

    // Property Chains don't work with unnamed bnodes :/.
    foreach ($this->possibleSteps as $concept => $rdfPropertyChains) {
      foreach ($rdfPropertyChains as $propChain => $tmp) {
        $pChain = explode(' ', $propChain);
        $dtProp = NULL;
        if ($tmp === NULL) {
          // Last property is a datatype property.
          $dtProp = array_pop($pChain);
        }
        // dpm($dtProp, "yay!");.
        $resources = [$uri => $uri];
        foreach ($pChain as $prop) {
          $newResources = [];
          foreach ($resources as $resource) {
            // dpm($graph->properties($resource), "props");
            // dpm($graph->allResources($resource, $prop), "Getting Resource $resource for prop $prop");.
            foreach ($graph->allResources($resource, $prop) as $r) {
              // dpm($r, "er");
              // if(!empty($r->getUri())
              $newResources[$r->getUri()] = $r;
            }
          }
          // dpm($resources, "old");
          // dpm($newResources, "new");.
          $resources = $newResources;
        }
        if ($dtProp) {
          foreach ($resources as $resource) {
            foreach ($graph->all($resource, $dtProp) as $thing) {
              // dpm($thing->getDatatype(), "thing");.
              if ($thing->getDatatype() == "geo:wktLiteral") {
                // Unluckily GND is not very WKT-conforming...
                $value = $thing->getValue();
                $value = str_replace("+", "", $value);
                $value = str_replace("Point", "POINT", $value);
                $value = str_replace(" ( ", "(", $value);
                $value = str_replace(" ) ", ")", $value);

                // $value = "POINT ( 011 011 )";.
                $data[$concept][$propChain][] = $value;
              }
              elseif ($thing instanceof EasyRdf_Literal) {
                $data[$concept][$propChain][] = $thing->getValue();
                // } else {
                //                $data[$field][] = $thing->getUri();
              }
            }
          }
        }
      }
    }

    $cache->set($id, $data);
    // dpm($data, "data");.
    return $data;

  }

  /**
   * {@inheritdoc}
   */
  public function checkUriExists($uri) {
    return !empty($this->fetchData($uri));
  }

  /**
   * {@inheritdoc}
   */
  public function createEntity($entity) {
    return;
  }

  /**
   *
   */
  public function getBundleIdsForEntityId($id) {
    $uri = $this->getUriForDrupalId($id);
    $data = $this->fetchData($uri);

    $pbs = $this->getPbsForThis();
    $bundle_ids = [];
    foreach ($pbs as $key => $pb) {
      $groups = $pb->getMainGroups();
      foreach ($groups as $group) {
        $path = $group->getPathArray();
        // dpm(array($path,$group, $pb->getPbPath($group->getID())),'bundlep');
        if (isset($data[$path[0]])) {
          $bid = $pb->getPbPath($group->getID())['bundle'];
          // dpm(array($bundle_ids,$bid),'bundlesi');.
          $bundle_ids[] = $bid;
        }
      }
    }

    // dpm($bundle_ids,'bundles');.
    return $bundle_ids;

  }

  /**
   * {@inheritdoc}
   */
  public function loadFieldValues(array $entity_ids = NULL, array $field_ids = NULL, $bundle = NULL, $language = LanguageInterface::LANGCODE_DEFAULT) {

    if (!$entity_ids) {
      // TODO: get all entities.
      $entity_ids = [
        "http://d-nb.info/gnd/11852786X",
      ];
    }

    $out = [];

    foreach ($entity_ids as $eid) {

      foreach ($field_ids as $fkey => $fieldid) {

        $got = $this->loadPropertyValuesForField($fieldid, [], $entity_ids, $bundleid_in, $language);

        if (empty($out)) {
          $out = $got;
        }
        else {
          foreach ($got as $eid => $value) {
            if (empty($out[$eid])) {
              $out[$eid] = $got[$eid];
            }
            else {
              $out[$eid] = array_merge($out[$eid], $got[$eid]);
            }
          }
        }

      }

    }

    return $out;

  }

  /**
   * {@inheritdoc}
   */
  public function loadPropertyValuesForField($field_id, array $property_ids, array $entity_ids = NULL, $bundleid_in = NULL, $language = LanguageInterface::LANGCODE_DEFAULT) {
    // dpm(func_get_args(), 'lpvff');.
    $main_property = FieldStorageConfig::loadByName('wisski_individual', $field_id);
    if (!empty($main_property)) {
      $main_property = $main_property->getMainPropertyName();
    }

    // drupal_set_message("mp: " . serialize($main_property) . "for field " . serialize($field_id));
    // if (in_array($main_property,$property_ids)) {
    // return $this->loadFieldValues($entity_ids,array($field_id),$language);
    // }
    // return array();
    if (!empty($field_id) && empty($bundleid_in)) {
      drupal_set_message("Es wurde $field_id angefragt und bundle ist aber leer.", "error");
      dpm(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
      return;
    }

    $pbs = [$this->getPbForThis()];
    $paths = [];
    foreach ($pbs as $key => $pb) {
      if (!$pb) {
        continue;
      }
      $field = $pb->getPbEntriesForFid($field_id);
      // dpm(array($key,$field),'öäü');.
      if (is_array($field) && !empty($field['id'])) {
        $paths[] = WisskiPathEntity::load($field["id"]);
      }
    }

    $out = [];

    foreach ($entity_ids as $eid) {

      if ($field_id == "eid") {
        $out[$eid][$field_id] = [$eid];
      }
      elseif ($field_id == "name") {
        // Tempo hack.
        $out[$eid][$field_id] = [$eid];
        continue;
      }
      elseif ($field_id == "bundle") {

        // Bundle is a special case.
        // If we are asked for a bundle, we first look in the pb cache for the bundle
        // because it could have been set by
        // measures like navigate or something - so the entity is always displayed in
        // a correct manor.
        // If this is not set we just select the first bundle that might be appropriate.
        // We select this with the first field that is there. @TODO:
        // There might be a better solution to this.
        // e.g. knowing what bundle was used for this id etc...
        // however this would need more tables with mappings that will be slow in case
        // of a lot of data...
        if (!empty($bundleid_in)) {
          $out[$eid]['bundle'] = [$bundleid_in];
          continue;
        }
        else {
          // If there is none return NULL.
          $out[$eid]['bundle'] = NULL;
          continue;
        }
      }
      else {

        if (empty($paths)) {
          // $out[$eid][$field_id] = NULL;.
        }
        else {

          foreach ($paths as $key => $path) {
            $values = $this->pathToReturnValue($path, $pbs[$key], $eid, 0, $main_property);
            if (!empty($values)) {
              foreach ($values as $v) {
                $out[$eid][$field_id][] = $v;
              }
            }
          }
        }
      }
    }

    // dpm($out, 'lfp');.
    return $out;

  }

  /**
   *
   */
  public function pathToReturnValue($path, $pb, $eid = NULL, $position = 0, $main_property = NULL) {
    // dpm($path->getName(), 'spam');.
    $field_id = $pb->getPbPath($path->getID())["field"];

    $uri = AdapterHelper::getUrisForDrupalId($eid, $this->adapterId());
    $data = $this->fetchData($uri);
    // dpm($data, "data");.
    if (!$data) {
      return [];
    }
    $path_array = $path->getPathArray();
    $path_array[] = $path->getDatatypeProperty();
    $data_walk = $data;
    // dpm($data_walk, "data");
    // dpm($path_array, "pa");.
    do {
      $step = array_shift($path_array);
      if (isset($data_walk[$step])) {
        $data_walk = $data_walk[$step];
      }
      else {
        // This is oversimplified in case there is another path in question but this
        // one had no data. E.g. a preferred name exists, but no variant name and
        // the variant name is questioned. Then it will resolve most of the array
        // up to the property and then stop here.
        //
        // in this case nothing should stay in $data_walk because
        // the foreach below would generate empty data if there is something
        // left.
        // By Mark: I don't know if this really is what should be here, martin.
        // @Martin: Pls check :)
        $data_walk = [];
        // Go to the next path.
        continue;
      }
    } while (!empty($path_array));
    // Now data_walk contains only the values.
    $out = [];
    // dpm($data_walk, "walk");
    // return $out;.
    foreach ($data_walk as $value) {
      if (empty($main_property)) {
        $out[] = $value;
      }
      else {
        $out[] = [$main_property => $value];
      }
    }
    // drupal_set_message(serialize($out));
    return $out;

  }

  /**
   * {@inheritdoc}
   */
  public function getPathAlternatives($history = [], $future = []) {
    // dpm($history);
    if (empty($history)) {
      $keys = array_keys($this->possibleSteps);
      return array_combine($keys, $keys);
    }
    else {
      // dpm($history, "hist");.
      $steps = $this->possibleSteps;

      // dpm($steps, "keys");
      // go through the history deeper and deeper!
      foreach ($history as $hist) {
        // $keys = array_keys($this->possibleSteps);.
        // If this is not set, we can not go in there.
        if (!isset($steps[$hist])) {
          return [];
        }
        else {
          $steps = $steps[$hist];
        }
      }

      // See if there is something.
      $keys = array_keys($steps);

      if (!empty($keys)) {
        return array_combine($keys, $keys);
      }

      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPrimitiveMapping($step) {
    $keys = array_keys($this->possibleSteps[$step]);
    return array_combine($keys, $keys);
  }

  /**
   * {@inheritdoc}
   */
  public function getStepInfo($step, $history = [], $future = []) {
    return [$step, ''];
  }

  /**
   *
   */
  public function getQueryObject(EntityTypeInterface $entity_type, $condition, array $namespaces) {
    return new Query($entity_type, $condition, $namespaces);
  }

  /**
   *
   */
  public function providesDatatypeProperty() {
    return TRUE;
  }

}
