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
    
#    $form = parent::blockForm($form, $form_state);
    
    $config = $this->getConfiguration();

    $form['better_lb'] = [
      '#type' => 'checkbox',
      '#title' => 'Use linkblock only with given adapter',
      '#default_value' => isset($config['better_lb']) ? $config['better_lb'] : 0,
    ];
    
    $pb = \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::load($linkblockpbid);
    
    if(empty($pb)) {
      $pb = new \Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity(array("id" => $linkblockpbid, "name" => "WissKI Linkblock PB"), "wisski_pathbuilder");
      $pb->save();
    }
    

#    $form = \Drupal::formBuilder()->getForm('Drupal\wisski_pathbuilder\Form\WisskiPathbuilderForm');
    
#    dpm($pb);

    #dpm($form, "form!!");
    
    return $form;
  }
  

  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['better_lb'] = $form_state->getValue('better_lb');
  }

    

  /**
   * {@inheritdoc}
   */
  public function betterBuild() {

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
    
    $dataout = array();
    
    $bundleid = $pb->getBundleIdForEntityId($individualid);
       # dpm($datapb);
    $groups = $pb->getGroupsForBundle($bundleid);
    
    foreach($groups as $linkgroup) {
      $allpbpaths = $pb->getPbPaths();
      $pbtree = $pb->getPathTree();
      // if there is nothing, then don't show up!
      if(empty($allpbpaths) || !isset($allpbpaths[$linkgroup->id()]))
        return;
      
      $pbarray = $allpbpaths[$linkgroup->id()];
      foreach($pbtree[$linkgroup->id()]['children'] as $child) {
        $childid = $child['id'];

        // better catch these.            
        if(empty($childid))
          continue;
        
        $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($childid);
#drupal_set_message("child: " . serialize($childid));            
#            $adapters = \Drupal\wisski_salz\Entity\WisskiSalzAdapter
        $adapter = entity_load('wisski_salz_adapter', $pb->getAdapterId());            
        $engine = $adapter->getEngine();
        $tmpdata = $engine->pathToReturnValue($path, $pb, $individualid, 0, 'target_id', FALSE);
        if(!empty($tmpdata)) {
          $dataout[$path->id()]['path'] = $path;
          if(!isset($dataout[$path->id()]['data']))
            $dataout[$path->id()]['data'] = array();
          $dataout[$path->id()]['data'] = array_merge($dataout[$path->id()]['data'], $tmpdata);
        }
      }
    }

    // cache for 2 seconds so subsequent queries seem to be fast
    if(!empty($dataout))  
      $out[]['#cache']['max-age'] = 2;
    // this does not work
#    $out['#cache']['disabled'] = TRUE;
#    $out[] = [ '#markup' => 'Time : ' . date("H:i:s"),];
#    drupal_set_message(serialize($dataout));
    foreach($dataout as $pathid => $dataarray) {
      $path = $dataarray['path'];
      
      if(empty($dataarray['data']))
        continue;
      
      $out[] = [ '#markup' => '<h3>' . $path->getName() . '</h3>'];
      
      foreach($dataarray['data'] as $data) {

        if(isset($data['wisskiDisamb']))  	    
          $url = $data['wisskiDisamb'];

        if(!empty($url)) {

          $entity_id = AdapterHelper::getDrupalIdForUri($url);
      
          $url = 'wisski/navigate/' . $entity_id . '/view';
      
          $out[] = array(
            '#type' => 'link',
            '#title' => $data['target_id'],
            '#url' => Url::fromUri('internal:/' . $url),
          );
          $out[] = [ '#markup' => '</br>' ];
        } else {
          $out[] = array(
            '#type' => 'item',
            '#markup' =>  $data['target_id'],
          );
          $out[] = [ '#markup' => '</br>' ];
        }
        
      }  
    }

    return $out;
  }





  /**
   * {@inheritdoc}
   */
  public function build() {

    $config = $this->getConfiguration();
    if (isset($config['better_lb']) && $config['better_lb']) {
      return $this->betterBuild();
    }

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
    
#    drupal_set_message(serialize($pbs));
#return $out;
    foreach($pbs as $datapb) {
    
      // skip the own one...
      if($pb == $datapb)
        continue;
    
      $bundleid = $datapb->getBundleIdForEntityId($individualid);
       # dpm($datapb);
      $groups = $datapb->getGroupsForBundle($bundleid);
#      drupal_set_message(serialize($datapb));
    
      foreach($groups as $group) {
        $linkgroup = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($group->id());
#        drupal_set_message(serialize("yay!"));
        if(!empty($linkgroup)) {
          $allpbpaths = $pb->getPbPaths();
          $pbtree = $pb->getPathTree();
          // if there is nothing, then don't show up!
          if(empty($allpbpaths) || !isset($allpbpaths[$linkgroup->id()]))
            return;
          
          $pbarray = $allpbpaths[$linkgroup->id()];
          foreach($pbtree[$linkgroup->id()]['children'] as $child) {
            $childid = $child['id'];

            // better catch these.            
            if(empty($childid))
              continue;
            
            $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($childid);
#drupal_set_message("child: " . serialize($childid));            
#            $adapters = \Drupal\wisski_salz\Entity\WisskiSalzAdapter
            $adapters = entity_load_multiple('wisski_salz_adapter');            
            
            foreach($adapters as $adapter) {
              $engine = $adapter->getEngine();

              $tmpdata = $engine->pathToReturnValue($path, $pb, $individualid, 0, 'target_id', FALSE);
#              drupal_set_message("path: " . serialize($path));
#              drupal_set_message(serialize($tmpdata));

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
#    if(!empty($dataout))  
    $out[]['#cache']['max-age'] = 2;
    // this does not work
#    $out['#cache']['disabled'] = TRUE;
#    $out[] = [ '#markup' => 'Time : ' . date("H:i:s"),];
#    drupal_set_message(serialize($dataout));
    foreach($dataout as $pathid => $dataarray) {
      $path = $dataarray['path'];
      
      if(empty($dataarray['data']))
        continue;
      
      $out[] = [ '#markup' => '<h3>' . $path->getName() . '</h3>'];
      
      foreach($dataarray['data'] as $data) {

        if(isset($data['wisskiDisamb']))  	    
          $url = $data['wisskiDisamb'];

        if(!empty($url)) {

          $entity_id = AdapterHelper::getDrupalIdForUri($url);
      
          $url = 'wisski/navigate/' . $entity_id . '/view';
      
          $out[] = array(
            '#type' => 'link',
            '#title' => $data['target_id'],
            '#url' => Url::fromUri('internal:/' . $url),
          );
          $out[] = [ '#markup' => '</br>' ];
        } else {
          $out[] = array(
            '#type' => 'item',
            '#markup' =>  $data['target_id'],
          );
          $out[] = [ '#markup' => '</br>' ];
        }
        
      }  
    }

    return $out;
  }

  public function getCacheTags() {
  
    $node = \Drupal::routeMatch()->getParameter('wisski_individual');

    // if the node is an object, reduce it to its id
    if(is_object($node))
      $node = $node->id();
    
    //With this when your node change your block will rebuild
    if ($node) {
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
