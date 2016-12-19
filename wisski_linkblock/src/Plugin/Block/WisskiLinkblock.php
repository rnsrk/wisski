<?php

namespace Drupal\wisski_linkblock\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;

use Drupal\wisski_salz\AdapterHelper;


/**
 * Provides the WissKI Linkblock
 *
 * @Block(
 *   id = "wisski_linkblock",
 *   admin_label = @Translation("WissKI Linkblock"),
 * )
 */

class WisskiLinkblock extends BlockBase {
  
  /**
   * {@inheritdoc}
   */

  public function blockForm($form, FormStateInterface $form_state) {
    
    $form = parent::blockForm($form, $form_state);
    
    $linkblockpbid = "wisski_linkblock";
    
    $form = parent::blockForm($form, $form_state);
    
    $config = $this->getConfiguration();
    
    $pb = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::load($linkblockpbid);
    
    if(empty($pb)) {
      $pb = new \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity(array("id" => $linkblockpbid, "name" => "WissKI Linkblock PB"), "wisski_pathbuilder");
      $pb->save();
    }
    

#    $form = \Drupal::formBuilder()->getForm('Drupal\wisski_pathbuilder\Form\WisskiPathbuilderForm');
    
#    dpm($pb);
    
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function build() {

    $out = array();

    $individualid = \Drupal::routeMatch()->getParameter('wisski_individual');
    if ($individualid instanceof \Drupal\wisski_core\Entity\WisskiEntity) $individualid = $individualid->id();
    
    if(empty($individualid)) {
      return $out;
    }
    
    $linkblockpbid = "wisski_linkblock";
    
    $pb = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::load($linkblockpbid);
    
    if(empty($pb)) {
      $pb = new \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity(array("id" => $linkblockpbid, "name" => "WissKI Linkblock PB"), "wisski_pathbuilder");
      $pb->save();
    }
    
#    $adapter = \Drupal\wisski_salz\Entity\Adapter::load($pb->getAdapterId());
    
    $pbs = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::loadMultiple();
    
    $dataout = array();
#return $out;
    foreach($pbs as $datapb) {
    
      // skip the own one...
      if($pb == $datapb)
        continue;
    
      $bundleid = $datapb->getBundleIdForEntityId($individualid);
       # dpm($datapb);
      $groups = $datapb->getGroupsForBundle($bundleid);
    
      foreach($groups as $group) {
        $linkgroup = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($group->id());

        if(!empty($linkgroup)) {

          $allpbpaths = $pb->getPbPaths();
          $pbtree = $pb->getPathTree();
          
          // if there is nothing, then don't show up!
          if(empty($allpbpaths) || isset($allpbpaths[$linkgroup->id()]));
            return NULL;
          
          $pbarray = $allpbpaths[$linkgroup->id()];
                    
          foreach($pbtree[$linkgroup->id()]['children'] as $child) {
            $childid = $child['id'];

            // better catch these.            
            if(empty($childid))
              continue;
            
            $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($childid);
            
#            $adapters = \Drupal\wisski_salz\Entity\WisskiSalzAdapter
            $adapters = entity_load_multiple('wisski_salz_adapter');            
            
            foreach($adapters as $adapter) {
              $engine = $adapter->getEngine();

              $tmpdata = $engine->pathToReturnValue($path, $pb, $individualid, 0, 'target_id');

              if(!empty($tmpdata)) {
                $dataout[$path->id()]['path'] = $path;

                if(!isset($dataout[$path->id()]['data']))
                  $dataout[$path->id()]['data'] = array();

                $dataout[$path->id()]['data'] = array_merge($dataout[$path->id()]['data'], $tmpdata);
              }
            }
            
          }
          
        }
        #dpm($linkgroup);
      }
    }

    // cache for 2 seconds so subsequent queries seem to be fast
    $out[]['#cache']['max-age'] = 2;
    // this does not work
#    $out['#cache']['disabled'] = TRUE;
#    $out[] = [ '#markup' => 'Time : ' . date("H:i:s"),];

    foreach($dataout as $pathid => $dataarray) {
      $path = $dataarray['path'];
      
      if(empty($dataarray['data']))
        continue;
      
      $out[] = [ '#markup' => '<h3>' . $path->getName() . '</h3>'];
      
      foreach($dataarray['data'] as $data) {
      
        $url = $data['wisskiDisamb'];
        if(!empty($url)) {

          $entity_id = AdapterHelper::getDrupalIdForUri($url);
      
          $url = 'wisski/navigate/' . $entity_id . '/view';
      
          $out[] = array(
            '#type' => 'link',
            '#title' => $data['target_id'],
            '#url' => Url::fromUri('internal:/' . $url),
          );
        } else {
          $out[] = array(
            '#type' => 'item',
            '#markup' =>  $data['target_id'],
          );
        }
        
      }  
    }

    return $out;
  }

  public function getCacheTags() {
    //With this when your node change your block will rebuild
    if ($node = \Drupal::routeMatch()->getParameter('wisski_individual')) {
      //if there is node add its cachetag
      return Cache::mergeTags(parent::getCacheTags(), array('wisski_individual:' . $node));
    } else {
      //Return default tags instead.
      return parent::getCacheTags();
    }
  }

  public function getCacheContexts() {
    //if you depend on \Drupal::routeMatch()
    //you must set context of this block with 'route' context tag.
    //Every new route this block will rebuild
    return Cache::mergeContexts(parent::getCacheContexts(), array('route'));
  }

}

?>