<?php

namespace Drupal\wisski_core\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\wisski_core\Entity\WisskiEntity;

class WisskiBundleListBuilder extends ConfigEntityListBuilder implements EntityHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {

    $header['id'] = t('ID');
    $header['label'] = t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {

    $row['id'] = $id = $entity->get('id');
    //@TODO use EntityFieldQuery or whatsolike
    //$ents = WisskiEntity::load(array('bundle'=>$id));
    $row['label'] = array(
      'data' => $this->getLabel($entity),
      'class' => array('menu-label'),
    );
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
   /*
  public function render() {
    $build = parent::render();
    $build['#empty'] = t('No media bundle available. <a href="@link">Add media bundle</a>.', array(
      '@link' => Url::fromRoute('media.bundle_add')->toString(),
    ));
    return $build;
  }
*/
}