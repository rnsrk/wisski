<?php
/**
 * @file
 *
 * Contains drupal\wisski_pathbuilder\WisskiPathbuilderListBuilder
 */
 
namespace Drupal\wisski_pathbuilder;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

class WisskiPathbuilderListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('id');
   
    return $header + parent::buildHeader();
  }
 
  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
 
    // id
    $row['id'] = $this->getLabel($entity);
   
    return $row + parent::buildRow($entity);
  }
  
  /**
   * {@inheritdoc}
   */
  public function render() {
    
    $build = parent::render();
    
    $build['#empty'] = $this->t('There are no Pathbuilders defined.');
    return $build;
  }
  
}
