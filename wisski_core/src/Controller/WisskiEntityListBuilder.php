<?php

namespace Drupal\wisski_core\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

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
  public function render($bundle = '',$entity = NULL) {
  
    if (!isset($this->limit))
      $this->limit = \Drupal::config('wisski_core.settings')->get('wisski_max_entities_per_page');
    $this->bundle = \Drupal::entityManager()->getStorage('wisski_bundle')->load($bundle);
    $this->entity = $entity;
    $build['table'] = array(
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => $this->getTitle(),
      '#rows' => array(),
      '#empty' => $this->t('There is no @label yet.', array('@label' => $this->entityType->getLabel())),
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    );
    foreach ($this->getEntityIds() as $entity_id) {
      if ($row = $this->buildRowForId($entity_id)) {
        $build['table']['#rows'][$entity_id] = $row;
      }
    }

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $build['pager'] = array(
        '#type' => 'pager',
      );
    }
    return $build;
  }

  protected $then = 0;

  protected function tick($name='') {
    
    $now = microtime(TRUE)*1000;
    dpm(ceil($now-$this->then).' ms',$name);
    $this->then = $now;
  }

  /**
   * {@inheritdoc}
   * We only load entities form the specified bundle
   */
  protected function getEntityIds() {
#   dpm($this); 
    $this->tick('init');
    if (isset($this->entity)) dpm($this->entity);
    $storage = $this->getStorage();
    $query = $storage->getQuery()
      ->sort($this->entityType->getKey('id'));

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    $this->tick('prepare');
    if (!empty($this->bundle)) {
      if ($pattern = $this->bundle->getTitlePattern()) {
        foreach ($pattern as $key => $attributes) {
          if ($attributes['type'] === 'field' && !$attributes['optional']) {
            $query->condition($attributes['name']);
          }
        }
      }
      $query->condition('bundle',$this->bundle->id());
      $this->tick('bundle pattern');
      $entity_ids = $query->execute();
      foreach ($entity_ids as $eid) {
        $storage->writeToCache($eid,$this->bundle->id());
      }
      $this->num_entities = count($entity_ids);
      $this->tick('Caching');
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
#dpm($entity);
//    dpm($entity->tellMe('id','bundle'));
//    echo "Hello ".$id;
    //dpm($entity);
    //dpm($entity->get('preview_image'));
    $row_preview_image = $this->t('No preview available');
    $prev = $entity->get('preview_image')->first();
    if ($prev) {
      $prev_id = $prev->target_id;
      $prev_file = \Drupal::entityManager()->getStorage('file')->load($prev_id);
      $prev_uri = $prev_file->getFileUri();
      $prev_mime = $prev_file->getMimeType();
      if (explode('/',$prev_mime)[0] === 'image') {
        $row_preview_image = array('data'=>array(
          '#theme' => 'image',
          '#uri' => $prev_uri,
          '#alt' => 'preview '.$entity->label(),
          '#title' => $entity->label(),
        ));
      }
    }
    $row['preview_image'] = $row_preview_image;
    $row['title'] = Link::createFromRoute($entity->label(),'entity.wisski_individual.canonical',array('wisski_bundle'=>$this->bundle->id(),'wisski_individual'=>$entity->id()));
    $row += parent::buildRow($entity);
    foreach($row['operations']['data']['#links'] as &$link) {
      $link['url']->setRouteParameter('wisski_bundle',$this->bundle->id());
    }
    return $row;
  } 

  private function getOperationLinks($entity_id) {
  
    //we have these hard-coded since there seems to be no possibility to generate fully qualified Route-URLs from
    //link templates without having the entity itself at hand, which we want to avoid here
    //add routes here to enhance the OPs list
    $operations = array(
      'view' => array('entity.wisski_individual.canonical',$this->t('View')),
      'edit' => array('entity.wisski_individual.edit_form',$this->t('Edit')),
      'delete' => array('entity.wisski_individual.delete_form',$this->t('Delete')),
    );
    $i = 0;
    $links = array();
    foreach ($operations as $key => list($route,$label)) {
      $links[$key] = array(
        'url' => Url::fromRoute($route,array('wisski_individual' => $entity_id,'wisski_bundle' => $this->bundle->id())),
        'weight' => $i++,
        'title' => $label,
      );
    }
    return $links;
  }
  
  /**
   * re-written buildRow since we don't need to load the entity just to make its title
   */
  public function buildRowForId($entity_id) {
    
    #dpm($this);
    #dpm($entity);
    //    dpm($entity->tellMe('id','bundle'));
    //    echo "Hello ".$id;
    //dpm($entity);
    //dpm($entity->get('preview_image'));
    $row_preview_image = $this->t('No preview available');
    
    $prev = $this->getStorage()->getPreviewImage($entity_id,$this->bundle->id());
    if ($prev) {
      $prev_id = $prev->target_id;
      $prev_file = \Drupal::entityManager()->getStorage('file')->load($prev_id);
      $prev_uri = $prev_file->getFileUri();
      $prev_mime = $prev_file->getMimeType();
      if (explode('/',$prev_mime)[0] === 'image') {
        $row_preview_image = array('data'=>array(
          '#theme' => 'image',
          '#uri' => $prev_uri,
          '#alt' => 'preview '.$entity->label(),
          '#title' => $entity->label(),
        ));
      }
    }
    $row['preview_image'] = $row_preview_image;
    $entity_label = $this->bundle->generateEntityTitle($entity_id,$entity_id);
    $row['title'] = Link::createFromRoute($entity_label,'entity.wisski_individual.canonical',array('wisski_bundle'=>$this->bundle->id(),'wisski_individual'=>$entity_id));
    $row['operations']['data'] = array(
      '#type' => 'operations',
      '#links' => $this->getOperationLinks($entity_id),
    );
    return $row;
  } 
}
