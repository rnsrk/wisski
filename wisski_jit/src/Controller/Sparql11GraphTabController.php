<?php

namespace Drupal\wisski_jit\Controller;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use \Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\wisski_core;
use Drupal\wisski_salz\AdapterHelper;
use Drupal\wisski_salz\Plugin\wisski_salz\Engine\Sparql11Engine;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class Sparql11GraphTabController extends ControllerBase {

  public function getJson(Request $request) {
#    drupal_set_message("got: " . serialize($request->get('wisski_individual')));
#    return new JsonResponse(array());

    $mode = $request->get('mode');

    $wisski_individual = $request->get('wisski_individual');
  
    $storage = \Drupal::entityManager()->getStorage('wisski_individual');
  
    $entity = $storage->load($wisski_individual);

    // if it is empty, the entity is the starting point
    if(empty($target_uri)) {
      $target_uri = AdapterHelper::getUrisForDrupalId($entity->id());
      $target_uri = current($target_uri);
    }

    // go through all adapters    
    $adapters = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->loadMultiple();

    foreach ($adapters as $a) {
      $label = $a->label();
      $e = $a->getEngine();
      if ($e instanceof Sparql11Engine) {
        $values = 'VALUES ?x { <' . $target_uri . '> } ';
        $q = "SELECT ?g ?s ?sp ?po ?o WHERE { $values { { GRAPH ?g { ?s ?sp ?x } } UNION { GRAPH ?g { ?x ?po ?o } } } }";
#        dpm($q);
        $results = $e->directQuery($q);

        $base = array("id" => $target_uri, "name" => '<span class="wki-groupname">' . $target_uri . '</span>', "children" => array(), "data" => array("relation" => "<h2>Connections (" . $target_uri . ")</h2><ul></ul>"));            

        foreach ($results as $result) {
#var_dump($result);
          if (isset($result->sp)) {          
            $base['data']['relation'] = substr($base['data']['relation'], 0, -5);  

            $base['data']['relation'] = $base['data']['relation'] . (
               "<li>" . $result->sp->getUri() . " &raquo; " . 
               $result->s->getUri() . "</li></ul>");

              $base['children'][] = array("id" => $result->s->getUri(), "name" => $result->s->getUri());
              $curr = &$base['children'][count($base['children'])-1];
      
              if(empty($curr['data']['relation']))
                $curr['data']['relation'] = ("<h2>Connections (" . $result->s->getUri() .")</h2><ul></ul>");
          
              $curr['data']['relation'] = substr($curr['data']['relation'], 0, -5);  

              $curr['data']['relation'] = $curr['data']['relation'] . (
                "<li>" . $result->s->getUri() . " &raquo; " . 
                $result->sp->getUri() . "</li></ul>");
          }
        }
      }
    }
            
#            $existing_bundles = $e->getBundleIdsForEntityId($result->s->getUri());

#            if(empty($existing_bundles))
#              $subjecturi = \Drupal\Core\Url::fromRoute('wisski_adapter_sparql11_pb.wisski_individual.triples', array('wisski_individual' => $entity->id(), 'target_uri' => $result->s->getUri() ) );
#            else {
#              $remote_entity_id = $e->getDrupalId($result->s->getUri());
#              $subjecturi = \Drupal\Core\Url::fromRoute('wisski_adapter_sparql11_pb.wisski_individual.triples', array('wisski_individual' => $remote_entity_id, 'target_uri' => $result->s->getUri() ) );
#            }

#            $predicateuri = \Drupal\Core\Url::fromRoute('wisski_adapter_sparql11_pb.wisski_individual.triples', array('wisski_individual' => $entity->id(), 'target_uri' => $result->sp->getUri() ) );

#            $objecturi = \Drupal\Core\Url::fromRoute('wisski_adapter_sparql11_pb.wisski_individual.triples', array('wisski_individual' => $entity->id(), 'target_uri' => $target_uri ) );

#            dpm(\Drupal::l($this->t('sub'), $subjecturi));
#            $form['in_triples'][] = array(
#              "<" . $result->s->getUri() . ">",
#              Link::fromTextAndUrl($this->t($result->s->getUri()), $subjecturi)->toRenderable(),
#              Link::fromTextAndUrl($this->t($result->sp->getUri()), $predicateuri)->toRenderable(),
#              Link::fromTextAndUrl($this->t($target_uri), $objecturi)->toRenderable(),
#              array('#type' => 'item', '#title' => $result->g->getUri()),
#              array('#type' => 'item', '#title' => $label),
#            );
#          } else {
#            
#            $subjecturi = \Drupal\Core\Url::fromRoute('wisski_adapter_sparql11_pb.wisski_individual.triples', array('wisski_individual' => $entity->id(), 'target_uri' => $target_uri ) );

#            $predicateuri = \Drupal\Core\Url::fromRoute('wisski_adapter_sparql11_pb.wisski_individual.triples', array('wisski_individual' => $entity->id(), 'target_uri' => $result->po->getUri() ) );
#            
#            if($result->o instanceof \EasyRdf_Resource) {
#              try {
#              
#                $existing_bundles = $e->getBundleIdsForEntityId($result->o->getUri());
#                
#                if(empty($existing_bundles))
#                  $objecturi = \Drupal\Core\Url::fromRoute('wisski_adapter_sparql11_pb.wisski_individual.triples', array('wisski_individual' => $entity->id(), 'target_uri' => $result->o->getUri() ) );
#                else {
#                  $remote_entity_id = $e->getDrupalId($result->o->getUri());              
#                  $objecturi = \Drupal\Core\Url::fromRoute('wisski_adapter_sparql11_pb.wisski_individual.triples', array('wisski_individual' => $remote_entity_id, 'target_uri' => $result->o->getUri() ) );
#                }
#                $got_target_url = TRUE;
#              } catch (\Symfony\Component\Routing\Exception\InvalidParameterException $ex) {
#                $got_target_url = FALSE;
#              }
#              $object_text = $result->o->getUri();
#            } else {
#              $got_target_url = FALSE;
#              $object_text = $result->o->getValue();
#            }
#            $graph_uri = isset($result->g) ? $result->g->getUri() : 'DEFAULT';
#            $form['out_triples'][] = array(
#              Link::fromTextAndUrl($target_uri, $subjecturi)->toRenderable(),
#              Link::fromTextAndUrl($result->po->getUri(), $predicateuri)->toRenderable(),
#              $got_target_url ? Link::fromTextAndUrl($object_text, $objecturi)->toRenderable() : array('#type' => 'item', '#title' => $object_text),
#              array('#type' => 'item', '#title' => $graph_uri),
#              array('#type' => 'item', '#title' => $label),
#            );
#          }
#        }
#      }
#    }
#    
#
#    $form['#title'] = $this->t('View Triples for ') . $target_uri;
#
#    return $form;
  
/*    
  $item = wisski_store_getObj()->wisski_ARCAdapter_addNamespace(urldecode($item));

  $olditem = wisski_store_getObj()->wisski_ARCAdapter_delNamespace($item);

  if($type == '3') {
    $query = "SELECT * WHERE { <" . wisski_store_getObj()->wisski_ARCAdapter_delNamespace($item). "> ?p ?o }";
     
    $rows = wisski_store_getObj()->wisski_ARCAdapter_getStore()->query($query, 'rows');
    
    $base = array("id" => wisski_store_getObj()->wisski_ARCAdapter_delNamespace($item), "name" => '<span class="wki-groupname">' . $item . '</span>', "children" => array(), "data" => array("relation" => "<h2>Connections (" . $item . ")</h2><ul></ul>"));    
    if(count($rows) > 0) { 	   
      foreach($rows as $row) {
          
        $base['data']['relation'] = substr($base['data']['relation'], 0, -5);  

        $base['data']['relation'] = $base['data']['relation'] . (
           "<li>" . wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['p']) . " &raquo; " . 
           wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['o']) . "</li></ul>");

        $base['children'][] = array("id" => $row['o'], "name" => wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['o']));
        $curr = &$base['children'][count($base['children'])-1];
      
        if(empty($curr['data']['relation']))
          $curr['data']['relation'] = ("<h2>Connections (" . wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['o']) .")</h2><ul></ul>");
          
        $curr['data']['relation'] = substr($curr['data']['relation'], 0, -5);  

        $curr['data']['relation'] = $curr['data']['relation'] . (
           "<li>" . wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['s']) . " &raquo; " . 
           wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['p']) . "</li></ul>");
      }
    
      $query = "SELECT * WHERE { ?s ?p <" . wisski_store_getObj()->wisski_ARCAdapter_delNamespace($item). "> }";
    
      $rows = wisski_store_getObj()->wisski_ARCAdapter_getStore()->query($query, 'rows');
    
      foreach($rows as $row) {
          
        $base['data']['relation'] = substr($base['data']['relation'], 0, -5);  

        $base['data']['relation'] = $base['data']['relation'] . (
           "<li>" . wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['s']) . " &raquo; " . 
           wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['p']) . "</li></ul>");

        $base['children'][] = array("id" => $row['s'], "name" => wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['s']));
        $curr = &$base['children'][count($base['children'])-1];
      
        if(empty($curr['data']['relation']))
          $curr['data']['relation'] = ("<h2>Connections (" . wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['s']) .")</h2><ul></ul>");
          
        $curr['data']['relation'] = substr($curr['data']['relation'], 0, -5);  

        $curr['data']['relation'] = $curr['data']['relation'] . (
           "<li>" . wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['p']) . " &raquo; " . 
           wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['o']) . "</li></ul>");
      }
    } else {
      $query = 'SELECT * WHERE { ?s ?p "' . $item . '" }';
     
      $rows = wisski_store_getObj()->wisski_ARCAdapter_getStore()->query($query, 'rows');
    
      $base = array("id" => $item, "name" => '<span class="wki-groupname">' . $item . '</span>', "children" => array(), "data" => array("relation" => "<h2>Connections (" . $item . ")</h2><ul></ul>"));      

      if(count($rows) > 0) { 	   
        foreach($rows as $row) {
          
          $base['data']['relation'] = substr($base['data']['relation'], 0, -5);  

          $base['data']['relation'] = $base['data']['relation'] . (
           "<li>" . wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['s']) . " &raquo; " . 
           wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['p']) . "</li></ul>");

          $base['children'][] = array("id" => $row['s'], "name" => wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['s']));
        }
                
      } else {
        $base = array();
                        
      }

    }
    
    $json = json_encode($base);
      
    drupal_json($json);
    return;
  } else { // case simple or standard
  
    include_once('sites/all/modules/wisski_pathbuilder/wisski_pathbuilder.inc');
    $groupid = wisski_pathbuilder_getGroupIDForIndividual(wisski_store_getObj()->wisski_ARCAdapter_delNamespace($item));

    if(empty($groupid) || $groupid == -1) 
      return json_encode("\{\}");

    $samepart = _wisski_pathbuilder_calculate_group_samepart($groupid);
    $sparqlcondition = (" FILTER ( ?x" . (floor(count($samepart)/2)) . " = <" . wisski_store_getObj()->wisski_ARCAdapter_delNamespace($item). "> ) ");
	
  	$basename = wisski_pathbuilder_generateGroupName($item, $groupid);
	
  	$base = array("id" => (wisski_store_getObj()->wisski_ARCAdapter_delNamespace($item)), "name" => '<span class="wki-groupname">' . $basename . '</span>', "children" => array(), "data" => array("relation" => "<h2>Connections (" . $basename . ")</h2><ul></ul>")); 

    $base = wisski_jit_generateJsonArray($groupid, $base, $sparqlcondition, $type);

    $json = json_encode($base);

    drupal_json($json);  
    return;
  }
}

function wisski_jit_generateJsonArray($groupid, $base = array(), $sparqlcondition, $type = '2') {
  include_once('sites/all/modules/wisski_pathbuilder/wisski_pathbuilder.inc');
  $pathIds = wisski_pathbuilder_getMembers($groupid, TRUE);

  $samepart = _wisski_pathbuilder_calculate_group_samepart($groupid);

  foreach($pathIds as $pathid) {
    $sparql = wisski_pathbuilder_get_sparql($pathid, $sparqlcondition);
    
    $pathdata = wisski_pathbuilder_getPathData($pathid);   

    $patharray = unserialize($pathdata['path_array']);
    
    $rows = wisski_store_getObj()->wisski_ARCAdapter_getStore()->query($sparql, 'rows');

    if(empty($rows))
      continue;

    if($pathdata['is_group'])
      $patharray = _wisski_pathbuilder_calculate_group_samepart($pathid);
            

    foreach($rows as $row) {
      $curr = &$base;

      $max = floor(count($patharray)/2);
      $min = floor(count($samepart)/2) + 1;

      for($i=$min; $i<=$max; $i++) {     
        $subgroupid = wisski_pathbuilder_getGroupIDForIndividual($row['x' . $i]);
          
        $subgroupname = "";
        if($subgroupid != -1) {
          if(wisski_pathbuilder_getParentGroup($subgroupid) == 0) {
            $subgroupname = wisski_pathbuilder_generateGroupName(wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['x' . $i]), $subgroupid);
            $subgroupname = '<span class="wki-groupname">' . $subgroupname . '</span>';
          } else {
            if($type == '1')
              continue;
            $subgroupname = wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['x' . $i]);
          }
        } else {
          if($type == '1')
            continue;
          $subgroupname = wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['x' . $i]);
        }
          $curr['children'][] = array("id" => ($row['x' . $i]), "name" => $subgroupname);
          if(empty($curr['data']['relation']))
            $curr['data']['relation'] = ("<h2>Connections (" . wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['x' . ($i-1)]) .")</h2><ul></ul>");
          
          $curr['data']['relation'] = substr($curr['data']['relation'], 0, -5);  

          $curr['data']['relation'] = $curr['data']['relation'] . (
            "<li>" . wisski_store_getObj()->wisski_ARCAdapter_addNamespace($patharray["y" . ($i-1)]) . " &raquo; " . 
            wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['x' . $i]) . "</li></ul>");

          $curr = &$curr['children'][count($curr['children'])-1];
          
          if(empty($curr['data']['relation']))
            $curr['data']['relation'] = ("<h2>Connections (" . wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['x' . $i]) .")</h2><ul></ul>");
          
          $curr['data']['relation'] = substr($curr['data']['relation'], 0, -5);
          
          $curr['data']['relation'] = $curr['data']['relation'] . (
            "<li>" . wisski_store_getObj()->wisski_ARCAdapter_addNamespace($patharray["y" . ($i-1)]) . " &raquo; " .
            wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['x' . ($i-1)]) . "</li></ul>");
          if($type == '1') 
            break;
      }

      if($subgroupname != "" && $type == '1')
        continue;
        
      if(!$pathdata['is_group']) {
  
        $str = mb_substr($row['out'], 0, 15);
        if(strlen($row['out']) > 15)
          $str .= '...';

        $curr['children'][] = array("id" => $row['out'],
          "name" => '<span class="wki-primitive">' . $str . '</span>');
          
        if(empty($curr['data']['relation']))
          $curr['data']['relation'] = ("<h2>Connections (" . wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['x' . ($i-1)]) .")</h2><ul></ul>");
          
        $curr['data']['relation'] = substr($curr['data']['relation'], 0, -5);  

        $curr['data']['relation'] = $curr['data']['relation'] . (
          "<li>" . wisski_store_getObj()->wisski_ARCAdapter_addNamespace($pathdata["datatype_property"]) .  " &raquo; " .
          $row['out'] . "</li></ul>");

        $curr = &$curr['children'][count($curr['children'])-1];
          
        if(empty($curr['data']['relation']))
          $curr['data']['relation'] = ("<h2>Connections (" . $row['out'] .")</h2><ul></ul>");
          
        $curr['data']['relation'] = substr($curr['data']['relation'], 0, -5);
          
        $curr['data']['relation'] = $curr['data']['relation'] . (
          "<li>" . wisski_store_getObj()->wisski_ARCAdapter_addNamespace($pathdata["datatype_property"]) .  " &raquo; " .
          wisski_store_getObj()->wisski_ARCAdapter_addNamespace($row['x' . ($i-1)]) . "</li></ul>");
      } else {

        $curr = wisski_jit_generateJsonArray($pathid, $curr, $sparqlcondition
              . ". FILTER ( ?x" . $max . " = <" . $row['x' . $max] . "> ) ", $type);
      }
    }
  }
  
  */
  
    return new JsonResponse( $base );
        
  }

  public function forward($wisski_individual) {
   
  $form['#markup'] = '<div id="wki-graph">
            <div id="wki-infocontrol">
              <select id="wki-infoswitch" size="1">
                <option value="1">Simple View&nbsp;</option>
                <option value="2" selected="selected">Standard View&nbsp;</option>
                <option value="3">Full View&nbsp;</option>
              </select>
            </div>
            <div id="wki-infovis"></div>    
            <div id="wki-infolist"></div>
            <div id="wki-infolog"></div>
          </div>';
          
  $form['#allowed_tags'] = array('div', 'select', 'option');
  $form['#attached']['drupalSettings']['wisski_jit'] = $wisski_individual;
  $form['#attached']['library'][] = "wisski_jit/Jit";

          
  return $form;

/*
    $storage = \Drupal::entityManager()->getStorage('wisski_individual');

    //let's see if the user provided us with a bundle, if not, the storage will try to guess the right one
    $match = \Drupal::request();
    $bundle_id = $match->query->get('wisski_bundle');
    if ($bundle_id) $storage->writeToCache($wisski_individual,$bundle_id);

    // get the target uri from the parameters
    $target_uri = $match->query->get('target_uri');

    $entity = $storage->load($wisski_individual);
    
    // if it is empty, the entity is the starting point
    if(empty($target_uri)) {

      $target_uri = AdapterHelper::getUrisForDrupalId($entity->id());
      
      $target_uri = current($target_uri);
      
    } else // if not we want to view something else
      $target_uri = urldecode($target_uri);
      
    // go through all adapters    
    $adapters = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->loadMultiple();

    #$my_url = \Drupal\Core\Url::fromRoute('wisski_adapter_sparql11_pb.wisski_individual.triples', $entity->id()));

    $form['in_triples'] = array(
      '#type' => 'table',
      '#caption' => $this->t('In-coming triples'),
      '#header' => array('Subject', 'Predicate', 'Object', 'Graph', 'Adapter'),
    );
    
    $form['out_triples'] = array(
      '#type' => 'table',
      '#caption' => $this->t('Out-going triples'),
      '#header' => array('Subject', 'Predicate', 'Object', 'Graph', 'Adapter'),
    );

    foreach ($adapters as $a) {
      $label = $a->label();
      $e = $a->getEngine();
      if ($e instanceof Sparql11Engine) {
        $values = 'VALUES ?x { <' . $target_uri . '> } ';
        $q = "SELECT ?g ?s ?sp ?po ?o WHERE { $values { { GRAPH ?g { ?s ?sp ?x } } UNION { GRAPH ?g { ?x ?po ?o } } } }";
#        dpm($q);
        $results = $e->directQuery($q);
        foreach ($results as $result) {
#var_dump($result);
          if (isset($result->sp)) {
            
            $existing_bundles = $e->getBundleIdsForEntityId($result->s->getUri());

            if(empty($existing_bundles))
              $subjecturi = \Drupal\Core\Url::fromRoute('wisski_adapter_sparql11_pb.wisski_individual.triples', array('wisski_individual' => $entity->id(), 'target_uri' => $result->s->getUri() ) );
            else {
              $remote_entity_id = $e->getDrupalId($result->s->getUri());
              $subjecturi = \Drupal\Core\Url::fromRoute('wisski_adapter_sparql11_pb.wisski_individual.triples', array('wisski_individual' => $remote_entity_id, 'target_uri' => $result->s->getUri() ) );
            }

            $predicateuri = \Drupal\Core\Url::fromRoute('wisski_adapter_sparql11_pb.wisski_individual.triples', array('wisski_individual' => $entity->id(), 'target_uri' => $result->sp->getUri() ) );

            $objecturi = \Drupal\Core\Url::fromRoute('wisski_adapter_sparql11_pb.wisski_individual.triples', array('wisski_individual' => $entity->id(), 'target_uri' => $target_uri ) );

#            dpm(\Drupal::l($this->t('sub'), $subjecturi));
            $form['in_triples'][] = array(
#              "<" . $result->s->getUri() . ">",
              Link::fromTextAndUrl($this->t($result->s->getUri()), $subjecturi)->toRenderable(),
              Link::fromTextAndUrl($this->t($result->sp->getUri()), $predicateuri)->toRenderable(),
              Link::fromTextAndUrl($this->t($target_uri), $objecturi)->toRenderable(),
              array('#type' => 'item', '#title' => $result->g->getUri()),
              array('#type' => 'item', '#title' => $label),
            );
          } else {
            
            $subjecturi = \Drupal\Core\Url::fromRoute('wisski_adapter_sparql11_pb.wisski_individual.triples', array('wisski_individual' => $entity->id(), 'target_uri' => $target_uri ) );

            $predicateuri = \Drupal\Core\Url::fromRoute('wisski_adapter_sparql11_pb.wisski_individual.triples', array('wisski_individual' => $entity->id(), 'target_uri' => $result->po->getUri() ) );
            
            if($result->o instanceof \EasyRdf_Resource) {
              try {
              
                $existing_bundles = $e->getBundleIdsForEntityId($result->o->getUri());
                
                if(empty($existing_bundles))
                  $objecturi = \Drupal\Core\Url::fromRoute('wisski_adapter_sparql11_pb.wisski_individual.triples', array('wisski_individual' => $entity->id(), 'target_uri' => $result->o->getUri() ) );
                else {
                  $remote_entity_id = $e->getDrupalId($result->o->getUri());              
                  $objecturi = \Drupal\Core\Url::fromRoute('wisski_adapter_sparql11_pb.wisski_individual.triples', array('wisski_individual' => $remote_entity_id, 'target_uri' => $result->o->getUri() ) );
                }
                $got_target_url = TRUE;
              } catch (\Symfony\Component\Routing\Exception\InvalidParameterException $ex) {
                $got_target_url = FALSE;
              }
              $object_text = $result->o->getUri();
            } else {
              $got_target_url = FALSE;
              $object_text = $result->o->getValue();
            }
            $graph_uri = isset($result->g) ? $result->g->getUri() : 'DEFAULT';
            $form['out_triples'][] = array(
              Link::fromTextAndUrl($target_uri, $subjecturi)->toRenderable(),
              Link::fromTextAndUrl($result->po->getUri(), $predicateuri)->toRenderable(),
              $got_target_url ? Link::fromTextAndUrl($object_text, $objecturi)->toRenderable() : array('#type' => 'item', '#title' => $object_text),
              array('#type' => 'item', '#title' => $graph_uri),
              array('#type' => 'item', '#title' => $label),
            );
          }
        }
      }
    }
    

    $form['#title'] = $this->t('View Triples for ') . $target_uri;

    return $form;
*/
  }
}