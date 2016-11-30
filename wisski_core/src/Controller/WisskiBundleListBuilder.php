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
  const CREATE = 3;
  
  /**
   * {@inheritdoc}
   */
  public function buildHeader() {

//    $header['id'] = t('ID');
    $header['label'] = $this->t('Name');
    if ($this->type === self::CONFIG) {
      $header['parent'] = $this->t('Parent');
      $header += parent::buildHeader();
    }
    return $header;
  }
  
  /**
   * {@inheritdoc}
   */
  public function getEntityIds() {

    // only get topids
    $topIds = \Drupal\wisski_core\WisskiHelper::getTopBundleIds();
    
    $query = $this->getStorage()->getQuery()->sort($this->entityType->getKey('id'));

    if($this->type == self::NAVIGATE || $this->type == self::CREATE) {
      // add a condition for the topids    
      $query->condition('id', array_values($topIds), 'IN');
    }
    
    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    
    // old: in case of navigate and create - exclude all non-top-groups
    // we don't need to do this here anymore, because we do this in
    // the entity query above.
    /*
    if($this->type == self::NAVIGATE || $this->type == self::CREATE) {
      // get all top groups from pbs
      $parents = \Drupal\wisski_core\WisskiHelper::getTopBundleIds();    

      // only show top groups
      if(!in_array($entity->id, $parents)) {
        drupal_set_message('Bundle '.$entity->id().' is not shown here since it is not a top bundle');
        return false;
        return array();
      }
    }
    */
    switch ($this->type) {
      case self::NAVIGATE: return $this->buildNavigateRow($entity);
      case self::CREATE: return $this->buildCreateRow($entity);
      case self::CONFIG: return $this->buildConfigRow($entity);
    }
    drupal_set_message($this->t('Invalid list type'),'error');
    return array();
  }
  
  private function buildNavigateRow($entity) {
#    dpm($entity);    
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
    $parents = $entity->getParentBundleIds();
    $row['label'] = array(
      'data' => $this->getLabel($entity),
      'class' => array('menu-label'),
    );
    if (list($key,$value) = each($parents)) {
      $row['parent'] = array(
        'data' => array(
          '#type' => 'link',
          '#url' => new Url('entity.entity_view_display.wisski_individual.default',array('wisski_bundle' => $key)),
          '#title' => $value,
        ),
      );
    } else $row['parent'] = '';
    $row += parent::buildRow($entity);
    $row['operations']['data']['#links']['add'] = array(
      'title' => $this->t('Add an Entity'),
      'url' => new Url('entity.wisski_individual.add',array('wisski_bundle' => $id)),
      'weight' => 5,
    );
    $row['operations']['data']['#links']['list'] = array(
      'title' => $this->t('List Entities'),
      'weight' => 10,
      'url' => $entity->urlInfo('entity-list'),
    );
//    dpm($row['operations']['data']['#links'],__METHOD__);
    return $row;
  }

  private function buildCreateRow($entity) {
#    dpm($entity);    
    $row['label'] = array(
      'data' => array(
        '#type' => 'link',
        '#url' => Url::fromRoute('entity.wisski_individual.add')
#        '#url' => Url::fromRoute('entity.wisski_bundle.entity_list')
          ->setRouteParameters(array('wisski_bundle' => $entity->id())),
        '#title' => $entity->label(),
      ),
    );
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