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
  
  private $num_entities;
  private $image_height;

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
      $this->num_entities = count($entity_ids);
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
    
    $header['preview_image'] = $this->t('Entity');
    $header['title'] = '';
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
#dpm($this);
//dpm($entity);
//    dpm($entity->tellMe('id','bundle'));
//    echo "Hello ".$id;
    //dpm($entity);
    //dpm($entity->get('preview_image'));
    $prev_id = $entity->get('preview_image')->first()->target_id;
    if ($prev_id) {
      $prev_uri = \Drupal::entityManager()->getStorage('file')->load($prev_id)->getFileUri();
      $row['preview_image'] = array('data'=>array(
        '#theme' => 'image',
        '#uri' => $prev_uri,
        '#alt' => 'preview '.$entity->label(),
        '#title' => $entity->label(),
        '#width' => $this->getImageWidth(),
        //'#height' => 100,
      ));
    } else $row['preview_image'] = $this->t('No preview available');
    $row['title'] = Link::createFromRoute($entity->label(),'entity.wisski_individual.view',array('wisski_individual'=>$entity->id()));
    return $row + parent::buildRow($entity);
  }
  
  
  
  private function getImageWidth() {
    
    if (isset($this->image_height)) return $this->image_height;
    $settings = \Drupal::config('wisski_core.settings');
    $n = $settings->get('wisski_preview_image_min_width_pixel');
    $x = $settings->get('wisski_preview_image_max_width_pixel');
    $l = $this->num_entities;
    return min(
      $x,
      max(
        $n,
        ceil((($l-1)*$n+(100-$l)*$x)/99)
      )
    );
    dpm(array('min'=>$n,'width'=>$width,'max'=>$x),__METHOD__);
    return $width;
  }

}
