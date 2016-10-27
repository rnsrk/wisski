<?php

/**
 * @file
 * Contains Drupal\wisski_adapter_gnd\Plugin\wisski_salz\Engine\GndEngine.
 */

namespace Drupal\wisski_adapter_gnd\Plugin\wisski_salz\Engine;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\wisski_adapter_gnd\Query\Query;
use Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity; 
use Drupal\wisski_pathbuilder\Entity\WisskiPathEntity; 
use Drupal\wisski_pathbuilder\PathbuilderEngineInterface;
use Drupal\wisski_salz\EngineBase;
use Drupal\wisski_salz\AdapterHelper;
use DOMDocument;
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
class GndEngine extends EngineBase implements PathbuilderEngineInterface {
  
  protected $uriPattern  = "!^http://d-nb.info/gnd/(\w+)$!u";
  protected $fetchTemplate = "http://d-nb.info/gnd/{id}/about/lds";
  
  /**
   * Workaround for super-annoying easyrdf buggy behavior:
   * it will only work on prefixed properties
   */
  protected $rdfNamespaces = array(
    'gnd' => 'http://d-nb.info/standards/elementset/gnd#',
  );
  


  protected $possibleSteps = array(
      'ConferenceOrEvent' => array(
        'gnd:preferredNameForTheConferenceOrEvent' => NULL,
        'gnd:variantNameForTheConferenceOrEvent' => NULL,
        ),
      'CorporateBody' => array(
        'gnd:preferredNameForTheCorporateBody' => NULL,
        'gnd:variantNameForTheCorporateBody' => NULL,
        ),
      'Family' => array(
        'gnd:preferredNameForTheFamily' => NULL,
        'gnd:variantNameForTheFamily' => NULL,
        ),
      'Person' => array(
        'gnd:preferredNameForThePerson' => NULL,
        'gnd:variantNameForThePerson' => NULL,
        ),
      'PlaceOrGeographicName' => array(
        'gnd:preferredNameForThePlaceOrGeographicName' => NULL,
        'gnd:variantNameForThePlaceOrGeographicName' => NULL,
        ),
      'SubjectHeading' => array(
        'gnd:preferredNameForTheSubjectHeading' => NULL,
        'gnd:variantNameForTheSubjectHeading' => NULL,
        ),
      'Work' => array(
        'gnd:preferredNameForTheWork' => NULL,
        'gnd:variantNameForTheWork' => NULL,
        ),

  );


  /**
   * {@inheritdoc} 
   */
  public function hasEntity($entity_id) {
    
    $uris = AdapterHelper::getUrisForDrupalId($entity_id);

    foreach ($uris as $uri) {
      // fetchData also checks if the URI matches the GND URI pattern
      // and if so tries to get the data.
      if ($this->fetchData($uri)) {
        return TRUE;
      }
    }

    return TRUE;
  }


  public function fetchData($uri = NULL, $id = NULL) {
    
    if (!$id) {
      if (!$uri) {
        return FALSE;
      } elseif (preg_match($this->uriPattern, $uri, $matches)) {
        $id = $matches[1];
      } else {
        // not a URI
        return FALSE;
      }
    }
    
    // 
    $cache = \Drupal::cache('wisski_adapter_gnd');
    $data = $cache->get($id);
    if ($data) {
      return $data->data;
    }

    $replaces = array(
      '{id}' => $id,
    );
    $fetchUrl = strtr($this->fetchTemplate, $replaces);

    $data = file_get_contents($fetchUrl);
    if ($data === FALSE || empty($data)) {
      return FALSE;
    }

#    $doc = new DOMDocument();
#    if (!$doc->load($fetchUrl)) {
#      return FALSE;
#    }

    $graph = new EasyRdf_Graph($fetchUrl, $data, 'turtle');
    if ($graph->countTriples() == 0) {
      return FALSE;
    }

    foreach ($this->rdfNamespaces as $prefix => $ns) {
      EasyRdf_Namespace::set($prefix, $ns);
    }

    $data = array();
    foreach ($this->possibleSteps as $concept => $rdfPropertyChains) {
      foreach ($rdfPropertyChains as $propChain => $tmp) {
        $pChain = explode(' ', $propChain);
        $dtProp = NULL;
        if ($tmp === NULL) {
          // last property is a datatype property
          $dtProp = array_pop($pChain);
        }
        $resources = array($uri => $uri);
        foreach ($pChain as $prop) {
          $newResources = array();
          foreach ($resources as $resource) {
            foreach ($graph->allResources($resource, $prop) as $r) {
              $newResources[$r] = $r;
            }
          }
          $resources = $newResources;
        }
        if ($dtProp) {
          foreach ($resources as $resource) {
            foreach ($graph->all($resource, $dtProp) as $thing) {
              if ($thing instanceof EasyRdf_Literal) {
                $data[$concept][$propChain][] = $thing->getValue();
//              } else {
//                $data[$field][] = $thing->getUri();
              }
            }
          }
        }      
      }
    }

    $cache->set($id, $data);

    return $data;

  }

  
  /**
   * {@inheritdoc} 
   */
  public function createEntity($entity) {
    return;
  }
  

  public function getBundleIdsForEntityId($id) {
    $uri = $this->getUriForDrupalId($id);
    $data = $this->fetchData($uri);
    
    $pbs = array($this->getPbForThis());
    $bundle_ids = array();
    foreach($pbs as $key => $pb) {
      $groups = $pb->getMainGroups();
      foreach ($groups as $group) {
        $path = $group->getPathArray(); 
#dpm(array($path,$group, $pb->getPbPath($group->getID())),'bundlep');
        if (isset($data[$path[0]])) {
          $bid = $pb->getPbPath($group->getID())['bundle'];
#dpm(array($bundle_ids,$bid),'bundlesi');
          $bundle_ids[] = $bid;
        }
      }
    }
    
#dpm($bundle_ids,'bundles');

    return $bundle_ids;

  }


  /**
   * {@inheritdoc} 
   */
  public function loadFieldValues(array $entity_ids = NULL, array $field_ids = NULL, $bundle = NULL,$language = LanguageInterface::LANGCODE_DEFAULT) {
    
    if (!$entity_ids) {
      // TODO: get all entities
      $entity_ids = array(
        "http://d-nb.info/gnd/11852786X"
      );
    }
    
    $out = array();

    foreach ($entity_ids as $eid) {

      foreach($field_ids as $fkey => $fieldid) {  
        
        $got = $this->loadPropertyValuesForField($fieldid, array(), $entity_ids, $bundleid_in, $language);

        if (empty($out)) {
          $out = $got;
        } else {
          foreach($got as $eid => $value) {
            if(empty($out[$eid])) {
              $out[$eid] = $got[$eid];
            } else {
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
  public function loadPropertyValuesForField($field_id, array $property_ids, array $entity_ids = NULL, $bundleid_in = NULL,$language = LanguageInterface::LANGCODE_DEFAULT) {
#dpm(func_get_args(), 'lpvff');

    $main_property = \Drupal\field\Entity\FieldStorageConfig::loadByName('wisski_individual', $field_id);
    if(!empty($main_property)) {
      $main_property = $main_property->getMainPropertyName();
    }
dpm($main_property, 'löä');    
    
#     drupal_set_message("mp: " . serialize($main_property) . "for field " . serialize($field_id));
#    if (in_array($main_property,$property_ids)) {
#      return $this->loadFieldValues($entity_ids,array($field_id),$language);
#    }
#    return array();

    if(!empty($field_id) && empty($bundleid_in)) {
      drupal_set_message("Es wurde $field_id angefragt und bundle ist aber leer.", "error");
      dpm(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
      return;
    }
    

    $pbs = array($this->getPbForThis());
    $paths = array();
    foreach($pbs as $key => $pb) {
      $field = $pb->getPbEntriesForFid($field_id);
#dpm(array($key,$field),'öäü');
      if (is_array($field) && !empty($field['id'])) {
        $paths[] = WisskiPathEntity::load($field["id"]);
      }
    }
//dpm($paths, 'paths');
      
    $out = array();

    foreach ($entity_ids as $eid) {
      
      if($field_id == "eid") {
        $out[$eid][$field_id] = array($eid);
      } elseif($field_id == "name") {
        // tempo hack
        $out[$eid][$field_id] = array($eid);
        continue;
      } elseif ($field_id == "bundle") {
      
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
        
        if(!empty($bundleid_in)) {
          $out[$eid]['bundle'] = array($bundleid_in);
          continue;
        } else {
          // if there is none return NULL
          $out[$eid]['bundle'] = NULL;              
          continue;
        }
      } else {
        
        if (empty($paths)) {
          $out[$eid][$field_id] = NULL;              
        } else {
          
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
    
    return $out;

  }


  public function pathToReturnValue($path, $pb, $eid = NULL, $position = 0, $main_property = NULL) {
    $field_id = $pb->getPbPath($path->getID())["field"];
dpm($main_property, 'mp');

    $uri = AdapterHelper::getUrisForDrupalId($eid)[0];
    $data = $this->fetchData($uri);

    $pa = $path->getPathArray();
    $pa[] = $path->getDatatypeProperty();
    $data_walk = $data;
    do {
#dpm($data_walk, 'walk');
      $step = array_shift($pa);
      if (isset($data_walk[$step])) {
        $data_walk = $data_walk[$step];
      } else {
        continue 2; // go to the next path
      }
    } while (!empty($pa));
#dpm($data_walk, 'wale');
    // now data_walk contains only the values
    $out = array();
    foreach ($data_walk as $value) {
      if (empty($main_property)) {
        $out[] = $value;
      } else {
        $out[] = array($main_property => $value);
      }
    }
    
    return $out;

  }


  /**
   * {@inheritdoc} 
   */
  public function getPathAlternatives($history = [], $future = []) {
    if (empty($history)) {
      $keys = array_keys($this->possibleSteps);
      return array_combine($keys, $keys);
    } else {
      return array();
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
    return array($step, '');
  }


  public function providesCacheMode() {
    return FALSE;
  }


  public function providesFastMode() {
    return FALSE;
  }

  public function providesDatatypeProperty() {
	  return TRUE;
	}

  public function getQueryObject(EntityTypeInterface $entity_type,$condition, array $namespaces) {
    return new Query($entity_type,$condition,$namespaces);
  }

	/**
	 * this is not a true alias for {@see self::getDrupalIdForUri}
	 * since it is the internal function that needs EXTERNAL information, i.e. from the AdapterHelper
	 * while getDrupalIdForUri works fully internally but is only working correctly for the preferred Local Store
	 * Additionally, this function here does a format check, too, finding out whether we already have an EID
	 * in this case it just returns the input
	 */
	public function getDrupalId($uri) {
	  if (is_numeric($uri)) {
	    //danger zone, we assume a numeric $uri to be an entity ID itself
	    return $uri;
	  }
    return NULL;
	}

  public function getDrupalIdForUri($uri,$adapter_id=NULL) {
    return NULL;
  }
  
  public function getUrisForDrupalId($id) {
    return array();
  }
  
  /**
   * {@inheritdoc}
   */
  public function getSameUris($uri) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getSameUri($uri, $adapter_id) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setSameUris($uris, $entity_id) {
    return FALSE;
  }
  

} 
