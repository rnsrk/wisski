<?php

/**
 * contains \Drupal\wisski_salz\ASTAnnotator
 */
namespace Drupal\wisski_salz\Query;

use Drupal\Core\Config\Entity\Query\Condition as ConditionParent;

/**
 * Class ASTAnnotator annotates the AST with bundle and adapter ids.
 */
class ASTAnnotator {
     /**
     * Creates a new ASTAnnotator object.
     * 
     * Because of dynamic conditions this might involve evaluating parts of the plan already.
     * To do this the dynamic_evaluator function must be provided. 
     * It takes as argument a single FILTER and should return a list of bundle ids involved.
     */
    public function __construct(?callback $dynamic_evaluator) {
        $this->dynamic_evaluator = $dynamic_evaluator;
        $this->pb_man = \Drupal::service('wisski_pathbuilder.manager');
        $this->adapter_man = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->loadMultiple();
    }



    public function annotate(?array $ast) {
        if ($ast === NULL) {
            return NULL;
        }

        if($ast['type'] === ASTHelper::TYPE_FILTER) {
            return $this->annotateFilter($ast);
        }

        return $this->annotateAggregate($ast);
    }

    private function annotateFilter(array $ast) {
        $aast = array_map(function($element) { return $element; }, $ast); // make a copy of ast
        $aast['annotations'] = array('bundles' => array(), 'adapters' => array());
        
        // find the involved adapters based on the field
        $field = $ast['field'];
       
        // some fields may only be used with an 'AND' conditions and may never introduce a new bundle.
        // these do not have any involved adapters.
        if (
            $field == 'title' ||
            $field == 'preferred_uri' ||
            $field == 'status' ||
            $field == 'preview_image'
        ) {
            return $aast;
        }
        
        // certain fields contain bundle ids, whereas other ids only contain information on where to retrieve bundle ids from.
        // We call the former case 'ast bundle ids' and the latter 'subquery bundle ids'.
        if (
            $field == 'bundle'
            // $field == 'bundles'
        ) {
            $bundles = array_values($ast['value']); // ast bundle ids => just extract them
        } else {
            $bundles = $this->evaluate_dynamic($ast); // subquery bundle ids => call the dynamic evaluator
        }

        $aast['annotations']['bundles'] = $bundles;
        $aast['annotations']['adapters'] = $this->mergeInvolvedAdapters($bundles);

        return $aast;
    }

    private function annotateAggregate(array $ast) {
        $aast = array_map(function($element) { return $element; }, $ast); // make a copy of ast

        $bundles = array();
        $adapters = array();

        // recursively annotate all the children and collect the bundles and adapters involved! 
        $aast['children'] = array_map(function($child) use (&$bundles, &$adapters) {
            $childAast = $this->annotate($child);
            if ($childAast !== NULL) {
                $bundles = array_merge($bundles, $childAast['annotations']['bundles']);
                $adapters = array_merge($adapters, $childAast['annotations']['adapters']);
            }
            return $childAast;
        }, $ast['children']);

        $aast['annotations'] = array('bundles' => array_unique($bundles), 'adapters' => array_unique($adapters));
        return $aast;
    }
    
    private static function debug(string $message) {
        if(WISSKI_DEVEL) \Drupal::logger('wisski_query_planner')->debug($message);
        // dpm($message); // TODO: Remove me!
    }

    // the wisski_pathbuilder.manager that is used to query for
    // new bundle => adapter mappings. 
    private $pb_man = NULL;

    // contains a cached mapping from bundle_id to adapters
    // so that we don't need to query the pb_man again. 
    private $bundle_to_adapter_cache = array();

    /**
     * like getInvolvedAdapters(), but for multiple bundleIDs.
     */
    private function mergeInvolvedAdapters(array $bundleIDs) {
        $adapters = array();
        foreach ($bundleIDs as $bundleID) {
            $adapters = array_merge($adapters, $this->getInvolvedAdapters($bundleID) );
        }
        return array_unique($adapters);
    }

    /**
     * Given a bundle ID return the involved adapters. 
     */
    private function getInvolvedAdapters(string $bundleID) {

        // popupulate the cache for this bundle id when needed. 
        if (!array_key_exists($bundleID, $this->bundle_to_adapter_cache)) {

            // find all the pathbuilders that know about this bundle
            // and then pick the adapters from those!
            $pbIDs = array_values($this->pb_man->getPbsUsingBundle($bundleID));
            $adapterIDs = array_map(function($pb) { return $pb['adapter_id']; }, $pbIDs);
            
            $this->bundle_to_adapter_cache[$bundleID] = array_unique($adapterIDs);
        }

        // return it from the cache
        return $this->bundle_to_adapter_cache[$bundleID];
    }

    private $dynamic_evaluator = NULL;

    /** calls the dynamic evaluator */
    protected function evaluate_dynamic(array $filter_ast) {
        if ($this->dynamic_evaluator == NULL) {
            return $this->default_dynamic_evaluator($filter_ast);
        }

        // TODO: Default Dynamic Evaluator
        return $this->dynamic_evaluator($ast);
    }

    protected function default_dynamic_evaluator(array $filter_ast) {
        return array(); // TODO: Do something smarter!
    }
}