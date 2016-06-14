<?php

namespace Drupal\wisski_core\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\wisski_core\Entity\WisskiEntity;

class WisskiBundleListBuilder extends ConfigEntityListBuilder implements EntityHandlerInterface {

  const NAVIGATE = 1;
  const CONFIG = 2;
  
  /**
   * {@inheritdoc}
   */
  public function buildHeader() {

//    $header['id'] = t('ID');
    $header['label'] = $this->t('Name');
    if ($this->type === self::CONFIG) {
      $header['parent'] = $this->t('Parent');
      $header + parent::buildHeader();
    }
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    
    switch ($this->type) {
      case self::NAVIGATE: return $this->buildNavigateRow($entity);
      case self::CONFIG: return $this->buildConfigRow($entity);
    }
    drupal_set_message($this->t('Invalid list type'),'error');
    return array();
  }
  
  private function buildNavigateRow($entity) {
    
    $row['label'] = array(
      'data' => array(
        '#type' => 'link',
        '#url' => Url::fromRoute('entity.wisski_bundle.entity_list')
          ->setRouteParameters(array('wisski_bundle' => $entity->id())),
        '#title' => $entity->label(),
      ),
    );
    return $row;
  }
  
  private function buildConfigRow($entity) {
    
    //    $row['id'] = 
    $id = $entity->get('id');
    //@TODO use EntityFieldQuery or whatsolike
    //$ents = WisskiEntity::load(array('bundle'=>$id));
    $parents = \Drupal\wisski_core\WisskiHelper::getParentBundleIds($id);
    $row['label'] = array(
      'data' => $this->getLabel($entity),
      'class' => array('menu-label'),
    );
    if (list($key,$value) = each($parents)) {
      $row['parent'] = array(
        'data' => array(
          '#type' => 'link',
          '#url' => Url::fromRoute('entity.entity_view_display.wisski_individual.default')
            ->setRouteParameters(array('wisski_bundle' => $key)),
          '#title' => $value,
        ),
      );
    } else $row['parent'] = '';
    $row += parent::buildRow($entity);
    $row['operations']['data']['#links']['add'] = array(
      'title' => $this->t('Add an Entity'),
      'url' => new Url('entity.wisski_bundle.entity_add',array('wisski_bundle' => $id),array('wisski_bundle'=> $id)),
      'weight' => 5,
    );
    if ($entity->hasLinkTemplate('edit-form')) {
      $row['operations']['data']['#links']['list'] = array(
        'title' => $this->t('List Entities'),
        'weight' => 10,
        'url' => $entity->urlInfo('entity-list'),
      );
    }
//    dpm($row['operations']['data']['#links'],__METHOD__);
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function render($type = self::CONFIG) {
    
    $this->type = $type;
    $build = parent::render();
    $build['#empty'] = t('No WissKI bundle available. <a href="@link">Add media bundle</a>.', array(
      '@link' => Url::fromRoute('entity.wisski_bundle.add')->toString(),
    ));
    return $build;
  }

}