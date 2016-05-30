<?php

namespace Drupal\wisski_core\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Provides a list controller for wisski_core entity.
 *
 */
class WisskiEntityListBuilder extends EntityListBuilder {

  private $bundle;

  /**
   * {@inheritdoc}
   *
   * We override ::render() so that we can add our own content above the table.
   * parent::render() is where EntityListBuilder creates the table using our
   * buildHeader() and buildRow() implementations.
   */
  public function render($bundle = '',$limit = NULL) {

    $this->limit = $limit;
    $this->bundle = $bundle;
    $build['table'] = parent::render();
    return $build;
  }

  /**
   * {@inheritdoc}
   * We only load entities form the specified bundle
   */
  protected function getEntityIds() {
  
    $storage = $this->getStorage();
    $query = $storage->getQuery()
      ->sort($this->entityType->getKey('id'));

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    if (!empty($this->bundle)) {
      $bundle_object = \Drupal::entityManager()->getStorage('wisski_bundle')->load($this->bundle);
      $pattern = $bundle_object->getTitlePattern();
      foreach ($pattern as $key => $attributes) {
        if ($attributes['type'] === 'field' && !$attributes['optional']) {
          $query->condition($attributes['name']);
        }
      }
      $query->condition('bundle',$this->bundle);
      $entity_ids = $query->execute();
      foreach ($entity_ids as $eid) {
        $storage->writeToCache($eid,$this->bundle);
      }
      return $entity_ids;
    } else return $query->execute();
    
  }

  /**
   * {@inheritdoc}
   *
   * Building the header and content lines for the contact list.
   *
   * Calling the parent::buildHeader() adds a column for the possible actions
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader() {
    $header['title'] = $this->t('title');
    $header['preview_image'] = '';
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
#dpm($this);
//    dpm($entity->tellMe('id','bundle'));
//    echo "Hello ".$id;
    $row['title'] = Link::createFromRoute($entity->label(),'entity.wisski_individual.view',array('wisski_individual'=>$entity->id()));
    //dpm($entity);
    $prev_id = $entity->get('preview_image')->first()->target_id;
    if ($prev_id) {
      $prev_uri = \Drupal::entityManager()->getStorage('file')->load($prev_id)->getFileUri();
      $row['preview_image'] = array('data'=>array(
        '#theme' => 'image',
        '#uri' => $prev_uri?:'',
        '#alt' => $this->t('preview %label',array('%label' => $entity->label())),
        '#title' => $entity->label(),
        '#width' => 40,
        '#height' => 20,
      ));
    } else $row['preview_image'] = $this->t('No preview available');
    return $row + parent::buildRow($entity);
  }

}
