<?php

namespace Drupal\wisski_core\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

use Drupal\image\Entity\ImageStyle;

use Drupal\wisski_core\WisskiCacheHelper;

/**
 * Provides a list controller for wisski_core entity.
 *
 */
class WisskiEntityListBuilder extends EntityListBuilder {

  private $bundle;
  
  private $num_entities;
  private $image_height;
  private $page;
  
  private $adapter;
  
  /**
   * {@inheritdoc}
   *
   * We override ::render() so that we can change the view type of the list
   * we avoid ::buildHeader() since we do not necessarily have one.
   * We also do not use buildRow() but instead introduce buildRowForId() to be able to load info without
   * having to load all the entities
   */
  public function render($bundle = '',$entity=NULL) {
    
    //if (!isset($this->limit))
    $this->limit = \Drupal::config('wisski_core.settings')->get('wisski_max_entities_per_page');
    $this->bundle = \Drupal::entityManager()->getStorage('wisski_bundle')->load($bundle);

    $build['#title'] = isset($this->bundle) ? $this->bundle->label() : $this->t('WissKI Entities');
    
    $pref_local = \Drupal\wisski_salz\AdapterHelper::getPreferredLocalStore();
    if (!$pref_local) {
      $build['error'] = array(
        '#type' => 'markup',
        '#markup' => $this->t('There is no preferred local store'),
      );
    } else $this->adapter = $pref_local;
    
    $request_query = \Drupal::request()->query;
    //dpm($request_query,'HTTP GET');
    $grid_type = $request_query->get('type') ? : 'grid';
    $grid_width = $request_query->get('width') ? : 3;
    $this->page = $request_query->get('page') ? : 0;
    //dpm($grid_type.' '.$grid_width);
    if ($grid_type === 'table') {
      $header = array('preview_image'=>$this->t('Entity'),'title'=>'','operations'=>$this->t('Operations'));
    }
    if ($grid_type === 'grid') {
      $header = NULL;
    }
    
    $build['table'] = array(
      '#type' => 'table',
      '#header' => $header,
      '#title' => $this->getTitle(),
      '#rows' => array(),
      '#empty' => $this->t('There is no @label yet.', array('@label' => $this->entityType->getLabel())),
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    );
    $entities = $this->getEntityIds();
    
    WisskiCacheHelper::preparePreviewImages($entities);
    
    if ($grid_type === 'table') {
      foreach ($entities as $entity_id) {
        
        if ($input_row = $this->buildRowForId($entity_id)) {
          $build['table']['#rows'][$entity_id] = array(
            'preview_image' => array(
              'data' => array(
                '#markup' => isset($input_row['preview_image']) 
                  ? '<a href='.$input_row['url']->toString().'>'.$input_row['preview_image'].'</a>'
                  : '<a href='.$input_row['url']->toString().'>'.$this->t('No preview available').'</a>'
                  ,
              ),
            ),
            'title' => array('data' => array(
              '#type' => 'link',
              '#title' => $input_row['label'],
              '#url' => $input_row['url'],
            )),
            'operations' => array(
              'data' => array(
                '#type' => 'operations',
                '#links' => $input_row['operations'],
              ),
            ),
          );
        }
      }
    }
    if ($grid_type === 'grid') {
      $row_num = 0;
      $cell_num = 0;
      $row = array();
#      dpm($ents,'list');
      
      foreach ($entities as $entity_id) {
        if ($input_cell = $this->buildRowForId($entity_id)) {
          $cell_data = array(
            '#type' => 'container',
          );
          if (isset($input_cell['preview_image'])) {
            $cell_data['preview_image'] = array(
              '#type' => 'link',
              '#title' => $input_cell['preview_image'],
              '#url' => $input_cell['url'],
              '#suffix' => '<br/>',
            );
          }
          $cell_data['title'] = array(
            '#type' => 'link',
            '#title' => $input_cell['label'],
            '#url' => $input_cell['url'],
          );
          $row[$cell_num] = array('data' => $cell_data);
          $cell_num++;
          if ($cell_num == $grid_width) {
            $build['table']['#rows']['row'.$row_num] = $row;
            $row_num++;
            $row = array();
            $cell_num = 0;
          }
        }  
      }  
      //add the last row
      if ($cell_num > 0) $build['table']['#rows']['row'.$row_num] = $row;
    }
    
    $build['grid_type'] = $this->getGridTypeBlock();
    
    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $build['pager'] = array(
        '#type' => 'pager',
      );
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   * We only load entities form the specified bundle
   */
  protected function getEntityIds() {
#   dpm($this); 

    $storage = $this->getStorage();
    $query = $storage->getQuery()
      ->sort($this->entityType->getKey('id'));

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
      $query->range($this->page*$this->limit,$this->limit);
    }

    if (!empty($this->bundle)) {
      if ($pattern = $this->bundle->getTitlePattern()) {
        foreach ($pattern as $key => $attributes) {
          if ($attributes['type'] === 'field' && !$attributes['optional']) {
            $query->condition($attributes['name']);
          }
        }
      }
      $query->condition('bundle',$this->bundle->id());

      $entity_ids = $query->execute();

      foreach ($entity_ids as $eid) {
        $storage->writeToCache($eid,$this->bundle->id());
      }
      $this->num_entities = count($entity_ids);

      return $entity_ids;
    } else return $query->execute();    
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
  
  private function getGridTypeBlock() {
    
    $block = array(
      '#title' => $this->t('Show as ...'),
      '#type' => 'details',
      '#open' => FALSE,
    );
    $block['table'] = array(
      '#type' => 'fieldset',
      'link' => array(
        '#type' => 'link',
        '#url' => Url::fromRoute('<current>',array('type'=>'table')),
        '#title' => $this->t('Table with operation links'),
      ),
      '#title' => $this->t('Table'),
    );
    $block['grid'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Grid of width'),
    );
    foreach (range(2,10) as $width) {
      $ops[] = array(
        'url' => Url::fromRoute('<current>',array('type'=>'grid','width'=>$width)),
        'title' => $width,
      );
    }
    $block['grid']['links'] = array(
      '#type' => 'operations',
      '#links' => $ops,
    );
    return $block;
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

    $entity_label = $this->bundle->generateEntityTitle($entity_id,$entity_id);

    $entity_url = Url::fromRoute('entity.wisski_individual.canonical',array('wisski_bundle'=>$this->bundle->id(),'wisski_individual'=>$entity_id));

    $row = array(
      'label' => $entity_label,
      'url' => $entity_url,
    );
    
    $prev_uri = $this->getPreviewImageUri($entity_id,$this->bundle->id());

    if ($prev_uri) {
      $array = array(
        '#theme' => 'image',
        '#uri' => $prev_uri,
        '#alt' => 'preview '.$entity_label,
        '#title' => $entity_label,
      );
      \Drupal::service('renderer')->renderPlain($array);
      $row['preview_image'] = $array['#markup'];
    }
    
    $row['operations'] = $this->getOperationLinks($entity_id);

    return $row;
  } 
  
  public function getPreviewImageUri($entity_id,$bundle_id) {
    
    $preview = WisskiCacheHelper::getPreviewImageUri($entity_id);
    //dpm($preview,__FUNCTION__.' '.$entity_id);
    if ($preview) {
      //do not log anything here, it is a performance sink
      //\Drupal::logger('wisski_preview_image')->debug('From Cache '.$preview);
      if ($preview === 'none') return NULL;
      return $preview;
    }

    if (!isset($this->adapter)) return NULL;
    
    if (empty(\Drupal\wisski_salz\AdapterHelper::getUrisForDrupalId($entity_id,$this->adapter->id()))) {
      \Drupal::logger('wisski_preview_image')->debug($this->adapter->id().' does not know the entity '.$entity_id);
      WisskiCacheHelper::putPreviewImageUri($entity_id,'none');
      return NULL;
    }

    $images = $this->adapter->getEngine()->getImagesForEntityId($entity_id,$bundle_id);
    if (empty($images)) {
      \Drupal::logger('wisski_preview_image')->debug('No preview images available from adapter '.$this->adapter->id());
      WisskiCacheHelper::putPreviewImageUri($entity_id,'none');
      return NULL;
    }

    \Drupal::logger('wisski_preview_image')->debug('Images from dapter: '.serialize($images));
    $input_uri = current($images);
    $output_uri = '';
    //get a correct image uri in $output_uri, by saving a file there
    $this->storage->getFileId($input_uri,$output_uri);
    $image_style = $this->getPreviewStyle();
    $preview_uri = $image_style->buildUri($output_uri);
    //dpm(array('output_uri'=>$output_uri,'preview_uri'=>$preview_uri));
    if ($image_style->createDerivative($output_uri,$preview_uri)) {
      //drupal_set_message('Style did it - uri is ' . $preview_uri);
      WisskiCacheHelper::putPreviewImageUri($entity_id,$preview_uri);

      return $preview_uri;
    } else {
      drupal_set_message("Could not create a preview image for $input_uri. Probably its MIME-Type is wrong or the type is not allowed by your Imge Toolkit","error");
      WisskiCacheHelper::putPreviewImageUri($entity_id,NULL);

      return NULL;
    }
  }
  
  private $image_style;
  
  private function getPreviewStyle() {
    
    if (isset($this->image_style)) return $this->image_style;
    $image_style_name = 'wisski_preview';

    $image_style = ImageStyle::load($image_style_name);
    if (is_null($image_style)) {
      $values = array('name'=>$image_style_name,'label'=>'Wisski Preview Image Style');
      $image_style = ImageStyle::create($values);
      $settings = \Drupal::config('wisski_core.settings');
      $w = $settings->get('wisski_preview_image_max_width_pixel');
      $h = $settings->get('wisski_preview_image_max_height_pixel');
      $config = array(
        'id' => 'image_scale',
        'data' => array(
          'width' => isset($w) ? $w : 100,
          'height' => isset($h) ? $h : 100,
          'upscale' => FALSE,
        ),
      );
      $image_style->addImageEffect($config);
      $config = array(
        'id' => 'image_convert',
        'data' => array(
          'extension' => 'jpeg',
        ),
      );
      $image_style->addImageEffect($config);
      $image_style->save();
    }
    $this->image_style = $image_style;
    return $image_style;
  }

}
