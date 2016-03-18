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

  /**
   * {@inheritdoc}
   *
   * We override ::render() so that we can add our own content above the table.
   * parent::render() is where EntityListBuilder creates the table using our
   * buildHeader() and buildRow() implementations.
   */
  public function render() {
    $build['description'] = array(
      '#markup' => $this->t('Let\'s talk about test entities'),
    );
    $build['table'] = parent::render();
    return $build;
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
    $header['id'] = $this->t('ID');
    $header['name'] = $this->t('Name');
    $header['bundle'] = $this->t('Bundle');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {

    dpm($entity->tellMe('id','bundle'));
    $row['id'] = $id = $entity->id();
    echo "Hello ".$id;
    $row['name'] = Link::createFromRoute($entity->name->value,'entity.wisski_individual.view',array('wisski_individual'=>$id));
    $row['bundle'] = $entity->bundle();
    
    return $row + parent::buildRow($entity);
  }

}
