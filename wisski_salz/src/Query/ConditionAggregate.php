<?php

namespace Drupal\wisski_salz\Query;

use Drupal\Core\Entity\Query\ConditionAggregateBase;

class ConditionAggregate extends ConditionAggregateBase
{

    /**
     * {@inheritdoc}
     */
    public function compile($conditionContainer) 
    {

    }

    /**
     * {@inheritdoc}
     */
    public function exists($field, $function, $langcode = null) 
    {
        return $this->condition($field, $function, null, 'IS NOT NULL', $langcode);
    }

    /**
     * {@inheritdoc}
     */
    public function notExists($field, $function, $langcode = null) 
    {
        return $this->condition($field, $function, null, 'IS NULL', $langcode);
    }
}