<?php

namespace Drupal\wisski_core\Query;

use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryFactoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;

class QueryFactory implements QueryFactoryInterface {

  /**
   * The namespace of this class, the parent class etc.
   *
   * @var array
   */
  protected $namespaces;

  /**
   * Constructs a QueryFactory object.
   */
  public function __construct() {
    $this->namespaces = QueryBase::getNamespaces($this);
  }

  /**
   * {@inheritdoc}
   */
  public function get(EntityTypeInterface $entity_type, $conjunction) {
    
//    dpm(func_get_args(),__METHOD__);
    $adapters = entity_load_multiple('wisski_salz_adapter');
    //
    // WATCH OUT - nasty assumption of yaml adapter being main store
    // @TODO change that
    //
    $adapter = $adapters['wisski_yaml_adapter'];
    $query = $adapter->getQueryObject($entity_type,$conjunction,$this->namespaces);
//    dpm($query);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getAggregate(EntityTypeInterface $entity_type, $conjunction) {
    //
    // WATCH OUT - nasty assumption of first one being main store
    // @TODO change that
    //
    $adapter = current(entity_load_multiple('wisski_salz_adapter'));
    return $adapter->getQueryObject($entity_type,$conjunction,$this->namespaces);  
  }

}

