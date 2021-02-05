<?php

/**
 * contains \Drupal\wisski_salz\QueryPlanner
 */
namespace Drupal\wisski_salz\Query;

class QueryPlanner {
    /**
     * Creates a new QueryPlanner object.
     * 
     * Because of dynamic conditions this might involve evaluating parts of the plan already.
     * To do this the dynamic_evaluator function must be provided. 
     * It takes as argument a single FILTER and should return a list of bundle ids involved.
     */
    public function __construct(callback $dynamic_evaluator) {
        $this->dynamic_evaluator = $dynamic_evaluator;
        $this->pb_man = \Drupal::service('wisski_pathbuilder.manager');
        $this->adapter_man = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->loadMultiple();
    }

    private static function debug($message) {
        if(WISSKI_DEVEL) \Drupal::logger('wisski_query_planner')->debug($message);
        // dpm($message); // TODO: Remove me!
    }

    const TYPE_EMPTY_PLAN = 'empty';

    const TYPE_SINGLE_SINGLE_PLAN = 'single_single';
    const TYPE_SINGLE_FEDERATION_PLAN = 'single_federation';
    const TYPE_SINGLE_PARTITION_PLAN = 'single_partition';

    const TYPE_MULTI_FEDERATION_PLAN = 'multi_federation';
    const TYPE_MULTI_PARTITION_PLAN = 'multi_partition';
    
    /**
     * Plan makes a plan for the provided ast. 
     */
    public function plan(array $ast) {
        
        $plan = $this->make_plan($ast, $dynamic_evaluator);
        if ($plan['type'] == self::TYPE_EMPTY_PLAN) {
            self::debug("Query Planner returned an empty plan!");
            return NULL;
        }
        return $plan;
    }

    private function make_plan(array $ast, callback $dynamic_evaluator) {

        /*
            An AST of a Query is represented as follows:   
        
            PLAN = SINGLE_PLAN | MULTI_PLAN
            AST = NODE (see ASTHelper class)

            // empty plan is a plan that introduces a new conditionn on an existing plan. 
            // it is not a valid top-level plan. 
            EMPTY_PLAN = {
                "type": "empty",
                "ast": AST,
            }

            // single plans are plans that occur in the leaves of the 'plan' tree.
            // they originate in the leaves of the AST, but may be merged to create new single plans. 
            // These always involve *exactly* one query (which may touch more than one adapter)
            SINGLE_PLAN = SINGLE_SINGLE_PLAN | SINGLE_FEDERATION_PLAN | SINGLE_PARTITION_PLAN

            // a single plan is a plan that involves exactly one adapter.
            SINGLE_SINGLE_PLAN = {
                "type": "single_single",
                "ast": AST,
                "reason": "dynamic" | "static"
                "adapter": ....
            }

            // a single query that involves fetching data from multiple adapters. 
            // and they're all federatable.
            SINGLE_FEDERATION_PLAN = {
                "type": "single_federation"
                "ast": AST
                "reason": "dynamic" | "static"
                "adapters": ...
            }

            // a single query that involes fetching data from multiple adapters
            // but these are not federatable.
            SINGLE_PARTITION_PLAN = {
                "type": "single_partition"
                "ast": AST
                "reason": "dynamic" | "static"
                "adapters": ...
            }
        
            // multi plans are plans that involve more than one subquery.
            MULTI_PLAN = MULTI_PLAN_FEDERATION | MULTI_PLAN_PARTITION

            MULTI_PLAN_FEDERATION = {
                "type": "multi_federation"
                "ast": ??? // todo: annotated AST
                "adapters": ...
            }

            MULTI_PLAN_PARTITION = {
                type: "multi_partition",
                "ast": AST
                "plans": PLAN +
            }

            A NULL plan indicates that there is nothing to be done. 
            
            */

        if ($ast == NULL) {
            self::debug("Query Planner encounted NULL");
            return array(
                'type' => self::TYPE_EMPTY_PLAN,
                'ast' => NULL,
            );
        }

        if ($ast['type'] == ASTHelper::TYPE_FILTER) {
            return $this->make_filter_plan($ast);
        }

        return $this->make_logical_aggregate_plan($ast);
    }

    /** merges plans of the children of the aggregate */
    protected function make_logical_aggregate_plan(array $ast) {

        // generate plans for each of the children
        $childPlans = array();
        foreach ($ast['children'] as $child) {
            $child = $this->make_plan($child);
            array_push($childPlans, $child);
        }
        
        // TODO: Do the actual merging!
        // TODO: CONTINUE IMPLEMENTATION HERE
        // for now we just return the child plans

        self::debug("unimplemented merge case!");
        
        return array(
            'type' => 'UNIMPLEMENTED',
            'pivot' => self::plans_get_pivot($childPlans),
            'ast' => $ast,
            'children' => $childPlans,
        );
    }

    /**
     * Return the "pivot" plan we can use to merge semantically compatible plans. 
     * When the plans are not compatible, return NULL.
     */
    protected static function plans_get_pivot(array $plans) {
        // iterate over the plans and check that each pair of plans is identical
        $left = NULL;
        $pivot = NULL;
        foreach ($plans as $right) {
            if ($left != NULL) {
                if (!self::has_compatible_semantics($left, $right)) {
                    return NULL;
                }
            }

            // pick the first non-empty plan as the pivot
            // in the absence, use the "last" empty plan. 
            if ($pivot == NULL || $pivot['type'] == self::TYPE_EMPTY_PLAN) {
                $pivot = $right;
            }
            
            $left = $right; // check the next plan
        }
        return $pivot;
    }

    /* check if two plans are "the same" */
    protected static function has_compatible_semantics(array $left, array $right) {
        // two plans are considered as having compatible semantics if
        // they can merged by only merging the ASTs.


        $leftType = $left['type'];
        $rightType = $right['type'];

        // special case: empty plans are compatible with every other plan. 
        if ($leftType == self::TYPE_EMPTY_PLAN || $rightType == self::TYPE_EMPTY_PLAN ) {
            return TRUE;
        }

        // normal case: check type-specific equality
        // both only on the adapter(s), not the AST.
        
        if ($leftType != $rightType) {
            return FALSE;
        }

        // TYPE_SINGLE_SINGLE_PLAN requires the same adapter
        if ($leftType == self::TYPE_SINGLE_SINGLE_PLAN) {
            return $left['adapter'] == $right['adapter'];
        }


        // TYPE_MULTI_PARTITION_PLAN can never be compatible with anything.
        if ($leftType == self::TYPE_MULTI_PARTITION_PLAN) {
            return FALSE;
        }

        // for all other types we check that the adapters are equal.

        $leftAdapters = self::adapter_to_normstring($left['adapters']);
        $rightAdapters = self::adapter_to_normstring($right['adapters']);
        
        return $leftAdapters == $rightAdapters;

    }

    /** turn an adapter array into a normalized string */
    protected static function adapter_to_normstring(array $adapters) {
        $adapters = array_unique($adapters);
        sort($adapters);
        return implode("\n", $adapters);
    }

    /** creates a new plan from a leaf ast */
    protected function make_filter_plan(array $ast) {
        // Because we are at the bottom of the AST we will *always* return a SINGLE_PLAN or EMPTY_PLAN.
   
        // first determine the adapters that are involved in this filter node.
        // also keep track of a reason for these adapters. 
        [$adapters, $reason] = $this->find_filter_adapters($ast);

        // based on the involved adapters, decide which single plan is needed.
        // i.e. do we need an EMPTY_PLAN | SINGLE_SINGLE_PLAN | SINGLE_FEDERATION_PLAN | SINGLE_PARTITION_PLAN
        return $this->decide_single_plan($ast, $adapters, $reason);
    }

    const TYPE_REASON_STATIC = 'static';
    const TYPE_REASON_DYNAMIC = 'dynamic';

    private function find_filter_adapters(array $ast) {
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
            return [array(), NULL];
        }

        // these fields have hard-coded bundle ids and introduce specific adapters linked to those bundle ids. 
        // we don't need to pass this to the '$dynamic_evaluator'
        if (
            $field == 'bundle'
            // $field == 'bundles'
        ) {
            $bundles = array_values($ast['value']);
            $adapters = $this->mergeInvolvedAdapters($bundles);
            return [$adapters, self::TYPE_REASON_STATIC];
        }

        // all the other plans are of a dynamic type and need to be evaluated.
        // for this we can use the dynamic_evaluator/

        $bundles = $this->evaluate_dynamic($ast);
        $adapters = $this->mergeInvolvedAdapters($bundles);
        return [$adapters, self::TYPE_REASON_DYNAMIC];
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
        return array("this_adapter_doesnt_exist"); // TODO: this doesn't do anything sensible at the moment. 
    }
        

    private function decide_single_plan(array $ast, array $adapters, ?string $reason) {
        // now figure out which of the plans we need by counting the number of adapters.
        $count = count($adapters);

        // no adapters => use TYPE_EMPTY_PLAN
        if ($count == 0) {
            return array(
                "type" => self::TYPE_EMPTY_PLAN,
                "ast" => $ast,
            );
        }

        // if we have exactly one adapter, use TYPE_SINGLE_SINGLE_PLAN.
        if ($count == 1) {
            return array(
                "type" => self::TYPE_SINGLE_SINGLE_PLAN,
                "reason" => $reason,
                "adapter" => $adapters[0],
                "ast" => $ast
            );
        }

        // we have more than 1 adapter, so now check if they are all federatable.
        $all_are_federatable = TRUE;
        foreach ($adapters as $adapter) {
            if (!$this->isAdapterFederatable($adapter, $ast)) {
                $all_are_federatable = FALSE;
                break;
            }
        }

        // all adapters are federatable => use a TYPE_SINGLE_FEDERATION_PLAN
        if ($all_are_federatable) {
            return array(
                "type" => self::TYPE_SINGLE_FEDERATION_PLAN,
                "ast" => $ast,
                "reason" => $reason, 
                "adapters" => $adapters,
            );
        }

        // at least one of the adapters is not federatable.
        // so use TYPE_SINGLE_PARTITION_PLAN

        return array(
            "type" => self::TYPE_SINGLE_PARTITION_PLAN,
            "ast" => $ast,
            "reason" => $reason,
            "adapters" => $adapters,
        );
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

    // contains the engine entity manager to lookup adapter from
    private $adapter_man = NULL;

    // contains a mapping from adapter_id => adapter instance
    private $adapter_cache = array();

    private function isAdapterFederatable(string $adapterID, array $ast = NULL) {

        // if the adapter isn't in the cache, fetch it from the manager.
        // TODO: Check that ->load() works
        if (!array_key_exists($adapterID, $this->adapter_cache)) {
            $this->adapter_cache[$adapterID] = $this->adapter_man->load($adapterID);
        }
    
        // check if the adapter actually supports federation
        // TODO: Optionally pass AST here?
        $adapter = $this->adapter_cache[$adapterID];
        return $adapter->getEngine()->supportsFederation();
    }
}