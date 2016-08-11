<?php

namespace Drupal\wisski_linkblock\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity;

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
#      $pb->getEntityType();
      $pb->save();
    }

    

#    $form = \Drupal::formBuilder()->getForm('Drupal\wisski_pathbuilder\Form\WisskiPathbuilderForm');
    
    dpm($pb);
    
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('Hello, World!'),
    );
  }
}

?>