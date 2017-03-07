<?php

namespace Drupal\wisski_adapter_sparql11_pb\Query;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\ConditionInterface;
use Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB;
use Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity;
use Drupal\wisski_salz\AdapterHelper;
use Drupal\wisski_salz\Query\ConditionAggregate;
use Drupal\wisski_salz\Query\WisskiQueryBase;

class Query extends WisskiQueryBase {
  
  /**
   * Holds the pathbuilders that this query object is responsible for.
   * This variable should not be accessed directly, use $this->getPbs() 
   * instead.
   */
  private $pathbuilders = NULL;
  
  /**
   * A counter used for naming variables in multi-path sparql queries
   *
   * @var integer
   */
  protected $varCounter = 0;

  /**
   * {@inheritdoc}
   */
  public function execute() {
    
    // NOTE: this is not thread-safe... shouldn't bother!
    $this->varCounter = 0;

#dpm($this->condition,__METHOD__);
    // compile the condition clauses into
    // sparql graph patterns and
    // a list of entity ids that the pattern should be restricted to
    list($where_clause, $entity_ids) = $this->makeQueryConditions($this->condition);
    
    if (empty($where_clause) && empty($entity_ids)) {
      $return = $this->count ? 0 : array();
    }
    elseif (empty($where_clause)) {
      list($limit, $offset) = $this->getPager();
      if ($limit !== NULL) {
        $entity_ids = array_slice($entity_ids, $offset, $limit, TRUE);
      }
      $return = $this->count ? count($entity_ids) : array_keys($entity_ids);
    }
    elseif (empty($entity_ids)) {
      list($limit, $offset) = $this->getPager();
      $return = $this->buildAndExecSparql($where_clause, NULL, $this->count, $limit, $offset);
      if (!$this->count) {
        $return = array_keys($return);
      }
    }
    else {
      // there are conditions left and found entities.
      // this can only occur if the conjunction of $this->condition is OR
      list($limit, $offset) = $this->getPager();
      // we must not use count directly (3rd param, see above)
      $entity_ids_too = $this->buildAndExecSparql($where_clause, NULL, FALSE, $limit, $offset);
      // combine the resulting entities with the ones already found.
      // we have to OR them: an AND conjunction would have been resolved in 
      // makeQueryConditions().
      $entity_ids = $this->join('OR', $entity_ids, $entity_ids_too);
      // now we again have to apply the pager
      if ($limit !== NULL) {
        $entity_ids = array_slice($entity_ids, $offset, $limit, TRUE);
      }
      $return = $this->count ? count($entity_ids) : array_keys($entity_ids);
    }
#dpm([$limit, $offset], 'pager');

    #\Drupal::logger('query adapter ' . $this->getEngine()->adapterId())->debug('query result is {result}', array('result' => serialize($return)));
    return $return;

  }

  
  public function getPager() {
    $limit = $offset = NULL;
    if (!empty($this->pager) || !empty($this->range)) {
      $limit = $this->range['length'] ? : NULL;
      $offset = $this->range['start'] ? : 0;
    }
    return array($limit, $offset);
  }

  
  /** Gets all the pathbuilders that this query is responsible for.
   *
   * @return an array of pathbuilder objects keyed by their ID
   */
  protected function getPbs() {
    
    // As the pbs won't change during query execution, we cache them
    if ($this->pathbuilders === NULL) {
      // get the engine
      $engine = $this->getEngine();
      if (empty($engine))
        return array();
      // get the adapter id
      $adapterid = $engine->adapterId();
      if (empty($adapterid))
        return array();
      // get all pbs
      $this->pathbuilders = array();
      // collect all pbs that this engine is responsible for
      foreach (WisskiPathbuilderEntity::loadMultiple() as $pb) {
        if (!empty($pb->getAdapterId()) && $pb->getAdapterId() == $adapterid) {
          $this->pathbuilders[$pb->id()] = $pb;
        }
      }
    }
    return $this->pathbuilders;

  }
    
  
  /** Descends the conjunction field until it finds an AND/OR string 
   * If none is found, returns the $default.
   *
   * We need this function as the conditions' conjunction field may itself
   * contain a condition.
   */
  protected function getConjunction($condition, $default = 'AND') {
    $conj = $condition->getConjunction();
    if (is_object($conj) && $conj instanceof ConditionInterface) {
      return $this->getConjunction($conj, $default);
    }
    elseif (is_string($conj)) {
      $conj = strtoupper($conj);
      if ($conj == 'AND' || $conj == 'OR') {
        return $conj;
      }
    }
    return $default;
  }

  
  /** helper function to join two arrays of entity id => uri pairs according
   * to the query conjunction
   */
  protected function join($conjunction, $array1, $array2) {
    // update the result set only if we really have executed a condition
    if ($array1 === NULL) {
      return $array2;
    }
    elseif ($array2 === NULL) {
      return $array1;
    }
    elseif ($conjunction == 'AND') {
      return array_intersect_key($array1, $array2);
    }
    else {
      // OR
      return array_merge($array1, $array2);
    }

  }

  
  /** recursively go through $condition tree and match entities against it.
   */
  protected function makeQueryConditions(ConditionInterface $condition) {
    
    // these fields cannot be queried with this adapter
    $skip_field_ids = array(
      'langcode',
      'name',
      'preview_image',
      'status',
      'uuid',
      'uid',
      'vid',
    );

    // get the conjunction (AND/OR)
    $conjunction = $this->getConjunction($condition);
    
    // here we collect entity ids
    $entity_ids = NULL;
    // ... and query parts
    $query_parts = array();

    // $condition is actually a tree of checks that can be OR'ed or AND'ed.
    // We walk the tree and build up sparql conditions / a where clause in
    // $query_parts.
    //
    // We must handle the special case of an entity id condition, which is not
    // executed against the triple store but the RDB. We keep track of these
    // entities in $entity_ids and perform sparql subqueries in case the ids and
    // the clauses have to be mixed (holds for ANDs).

    foreach ($condition->conditions() as $ij => $cond) {
      
      $field = $cond['field'];
      $value = $cond['value'];
      $operator = $cond['operator'];
#\Drupal::logger('query path cond')->debug("$ij::$field::$value::$operator::$conjunction");     

      // we dispatch over the field

      if ($field instanceof ConditionInterface) {
        // this is a nested condition so we have to recurse

        list($qp, $eids) = $this->makeQueryConditions($field);
        $entity_ids = $this->join($conjunction, $entity_ids, $eids);
        if ($entity_ids !== NULL && count($entity_ids) == 0 && $conjunction == 'AND') {
          // the condition evaluated to an empty set of entities 
          // and we have to AND; so the result set will be empty.
          // The rest of the conditions can be skipped 
          return array('', array());
        }
        $query_parts[] = $qp;

      }
      elseif ($field == "eid") {
        // directly ask Drupal's entity id.

        $eids = $this->executeEntityIdCondition($operator, $value);
        $entity_ids = $this->join($conjunction, $entity_ids, $eids);
        if ($entity_ids !== NULL && count($entity_ids) == 0 && $conjunction == 'AND') {
          // the condition evaluated to an empty set of entities 
          // and we have to AND; so the result set will be empty.
          // The rest of the conditions can be skipped 
          return array('', array());
        }

      }
      elseif ($field == "bundle") {
        // the bundle is being mapped to pb groups

        $query_parts[] = $this->makeBundleCondition($operator, $value);

      }
      elseif ($field == "title") {
        // directly ask Drupal's entity id.

        $eids = $this->executeEntityTitleCondition($operator, $value);
        $entity_ids = $this->join($conjunction, $entity_ids, $eids);
        if ($entity_ids !== NULL && count($entity_ids) == 0 && $conjunction == 'AND') {
          // the condition evaluated to an empty set of entities 
          // and we have to AND; so the result set will be empty.
          // The rest of the conditions can be skipped 
          return array('', array());
        }

      }
      elseif (in_array($field, $skip_field_ids)) {
        // these fields are not supported on purpose

        $this->missingImplMsg("Field $field intentionally not queryable in entity query", array('condition' => $condition));
      
      } 
      // for the rest of the fields we need to distinguish between field and path
      // query mode 
      //
      // TODO: we should not need to distinguish between both modes as we can
      // tell them apart by the dot. This would make query more flexible and
      // allow for queries that contain both path and field conditions.
      elseif ($this->isPathQuery() || strpos($field, '.') !== FALSE) {
        // the field is actually a path so we can query it directly

        // the search field id encodes the pathbuilder id and the path id:
        // decode them!
        // TODO: we could omit the pb and search all pbs the contain the path
        $pb_and_path = explode(".", $field);
        if (count($pb_and_path) != 2) {
          // bad encoding! can't handle
          drupal_set_message($this->t('Bad pathbuilder and path id "%id" in entity query condition', ['%id' => $field]));
          continue; // with next condition
        }
        $pbid = $pb_and_path[0];
        $pbs = $this->getPbs();
        if (!isset($pbs[$pbid])) {
          // we cannot handle this path as its pb belongs to another engine's
          // pathbuilder
          continue; // with next condition
        }
        $pb = $pbs[$pbid];
        // get the path
        $path_id = $pb_and_path[1];
        $path = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load($path_id);
        if(empty($path)) {
          drupal_set_message($this->t('Bad path id "%id" in entity query', ['%id' => $path_id]));
          continue; // with next condition
        }
        $query_parts[] = $this->makePathCondition($pb, $path, $operator, $value);
      } else {
        // the field must be mapped to noe or many paths which are then queried

        $query_parts[] = $this->makeFieldCondition($field, $operator, $value);
      }
    }
    
    // flatten query parts array
    if (empty($query_parts)) {
      $query_parts = '';
    } 
    elseif (count($query_parts) == 1) {
      $query_parts = $query_parts[0];
    } 
    elseif ($conjunction == 'AND') {
      $query_parts = join(' ', $query_parts);
    }
    else {
      // OR
      $query_parts = ' {{ ' . join(' } UNION { ', $query_parts) . ' }} ';
    }
    
    // 
    if ($entity_ids === NULL) {
      return array($query_parts, $entity_ids);
    }
    else {
      if (count($entity_ids) == 0) {
        // implies OR conjunction; AND is handled above inline.
        // no entities selected so far, treat as if there was no such condition
        return array($query_parts, NULL);
      } elseif (empty($query_parts)) {
        // we can just pass on the entity ids
        return array('', $entity_ids);
      }
      elseif ($conjunction == 'AND') {
        // we have clauses and entity ids which we combine for AND as we
        // don't know if the parent condition is OR in which case
        // the clauses and ids would produce a cross product.
        // this subquery is (hopefully) much faster.
        $entity_ids = $this->buildAndExecSparql($query_parts, $entity_ids);
        return array('', $entity_ids);
      }
      else {
        // OR
        // we just can pass both on
        return array($query_parts, $entity_ids);
      }
    } 

  }

  
  /** Builds a Sparql SELECT query from the given parameter and sends it to the
   * query's adapter for execution.
   *
   * @param $query_parts the where clause of the query. The query always asks
   *        about ?x0 so query_parts must contain this variable.
   * @param $entity_ids an assoc array of entity id => uri pairs that the 
   *        resulting array is restricted to.
   * @param $count whether this is a count query
   * @param $limit max number of returned entities / the pager limit
   * @param $offset the offset in combination with $limit
   *
   * @return an assoc array of matched entities in the form of entity_id => uri
   *         or an integer $count is TRUE.
   */
  protected function buildAndExecSparql($query_parts, $entity_ids, $count = FALSE, $limit = 0, $offset = 0) {
    
    if ($count) {
      $select = 'SELECT (COUNT(DISTINCT ?x0) as ?cnt) WHERE { ';
    }
    else {
      $select = 'SELECT DISTINCT ?x0 WHERE { ';
    }
    
    // we restrict the result set to the entities in $entity_ids by adding a
    // VALUES statement in front of the rest of the where clause
    if (!empty($entity_ids)) {
      // entity_ids is an assoc array where the keys are the ids and the values
      // are the corresp URIs
      $select .= 'VALUES ?x0 { <' . join('> <', $entity_ids) . '> } ';
    }

    $select .= $query_parts . ' }';
    
    if ($limit) {
      $select .= " LIMIT $limit OFFSET $offset";
    }
    
#    dpm($select, "select");

    $result = $engine = $this->getEngine()->directQuery($select);
#    drupal_set_message(serialize($select));
    $adapter_id = $this->getEngine()->adapterId();
    if (WISSKI_DEVEL) \Drupal::logger("query adapter $adapter_id")->debug('(sub)query {query} yielded {result}', array('query' => $select, 'result' => $result));
    if ($result->numRows() == 0) {
      $return = $count ? 0 : array();
    }
    elseif ($count) {
      $return = $result[0]->cnt->getValue();
    }
    else {
      // make the assoc array from the results
      $return = array();
      foreach ($result as $row) {
        if (!empty($row) && !empty($row->x0)) {
          $uri = $row->x0->getUri();
          if (!empty($uri)) {
            $entity_id = AdapterHelper::getDrupalIdForUri($uri, TRUE, $adapter_id);
            if (!empty($entity_id)) {
              $return[$entity_id] = $uri;
            }
          }
        }
      }
    }
#    drupal_set_message(serialize($return));
    return $return;

  }
  

  protected function executeEntityIdCondition($operator, $value) {
    $entity_ids = NULL;
    if (empty($value)) {
      // if no value is given, then condition is always true.
      // this may be the case when a field's mere existence is checked;
      // as the eid always exists, this is true for every entity
      // => do nothing
    }
    else {
      // we directly access the entity table.
      // TODO: this is a hack but faster than talking with the AdapterHelper
      if ($operator == 'IN' || $operator == "=") {
        $values = (array) $value;
        $query = \Drupal::database()->select('wisski_salz_id2uri', 't')
          ->distinct()
          ->fields('t', array('eid', 'uri'))
          ->condition('adapter_id', $this->getEngine()->adapterId())
          ->condition('eid', $values, 'IN');
        $entity_ids = $query->execute()->fetchAllKeyed();
      }
      elseif ($operator == 'BETWEEN') {
        $values = (array) $value;
        $query = \Drupal::database()->select('wisski_salz_id2uri', 't')
          ->distinct()
          ->fields('t', array('eid', 'uri'))
          ->condition('adapter_id', $this->getEngine()->adapterId())
          ->condition('eid', $values, 'BETWEEN');
        $entity_ids = $query->execute()->fetchAllKeyed();
      }
      else {
        $this->missingImplMsg("Operator $operator in eid field query", array('condition' => $condition));
      }
    }
    return $entity_ids;
  }


  protected function executeEntityTitleCondition($operator, $value) {
    $entity_ids = NULL;
    if (empty($value)) {
      // if no value is given, then condition is always true.
      // this may be the case when a field's mere existence is checked;
      // as the title always exists, this is true for every entity
      // => do nothing
    }
    else {
      // we directly access the title cache table. this is the only way to
      // effeciently query the title. However, this may not always return 
      // all expected entity ids as 
      // - a title may not yet been written to the table.
      // NOTE: This query is not aware of bundle conditions that may sort out
      // titles that are associated with "wrong" bundles.
      // E.g: an entity X is of bundle A and B. A query on bundle A and title 
      // pattern xyz is issued. xyz matches entity title, but for bundle B.
      // The query will still deliver X as it matches both conditions
      // seperately, but not combined!

      // first fetch all entity ids that match the title pattern
      $select = \Drupal::service('database')
          ->select('wisski_title_n_grams','w')
          ->fields('w', array('ent_num'));
      if ($operator == '=' || $operator == "!=") {
        $select->condition('ngram', $value, $operator);
      }
      elseif ($operator == 'CONTAINS' || $operator == "STARTS_WITH") {
        $select->condition('ngram', ($operator == 'CONTAINS' ? "%" : "") . $select->escapeLike($value) . "%", 'LIKE');
      }
      else {
        $this->missingImplMsg("Operator $operator in title field query", array('condition' => $condition));
        return $entity_ids; // NULL
      }

      $rows = $select
          ->execute()
          ->fetchAll();
        
      foreach ($rows as $row) {
        $entity_ids[$row->ent_num] = $row->ent_num;
      }

      // now fetch the uris for the eids as we have to return both
      $query = \Drupal::database()->select('wisski_salz_id2uri', 't')
        ->distinct()
        ->fields('t', array('eid', 'uri'))
        ->condition('adapter_id', $this->getEngine()->adapterId())
        ->condition('eid', $entity_ids, 'IN');
      $entity_ids = $query->execute()->fetchAllKeyed();
    }
    return $entity_ids;
  }

  
  protected function makeBundleCondition($operator, $value) {
    
    $query_parts = array();

    if (empty($operator) || $operator == 'IN' || $operator == '=') {
      $bundle_ids = (array) $value;
      $engine = $this->getEngine();
      // we have to igo thru all the groups that belong to this bundle
      foreach ($this->getPbs() as $pb) {
        foreach ($bundle_ids as $bid) {
          $groups = $pb->getGroupsForBundle($bid);
          foreach ($groups as $group) {

            // build up an array for separating the variables of the sparql 
            // subqueries.
            // only the first var x0 get to be the same so that everything maps
            // to the same entity
            // NOTE: we set the first var to x0 although it's not x0
            $starting_position = $pb->getRelativeStartingPosition($group, FALSE);
#            drupal_set_message(serialize($group));
#            drupal_set_message(serialize($starting_position));
            $i = $this->varCounter++;
            $vars[$starting_position] = 'x0';
            for ($j = count($group->getPathArray()); $j > $starting_position; $j--) {
              $vars[$j] = "c${i}_x$j";
            }
            $vars['out'] = "c${i}_out";

            $query_parts[] = $engine->generateTriplesForPath($pb, $group, '', NULL, NULL, 0, $starting_position, FALSE, '=', 'field', TRUE, $vars);
           
          }
        }
      }
    }
    else {
      $this->missingImplMsg("Operator $operator in bundle fieldquery", array(func_get_args()));
    }

    if (empty($query_parts)) {
      return '';
    } 
    else {
      $query_parts = '{{ ' . join('} UNION {', $query_parts) . '}} ';  
      return $query_parts;
    }
  
  }
  

  protected function makeFieldCondition($field, $operator, $value) {
    
    $query_parts = array();
    
    $count = 0;
    foreach ($this->getPbs() as $pb) {
      $path = $pb->getPathForFid($field);
      if (!empty($path)) {
        $query_parts[] = $this->makePathCondition($pb, $path, $operator, $value);
        $count++;
      }
    }

    if ($count == 0) {
      return '';
    }
    elseif ($count == 1) {
      return $query_parts[0];
    }
    else {
      $query_parts = '{{ ' . join('} UNION {', $query_parts) . '}} ';  
      return $query_parts;
    }
    
  }


  protected function makePathCondition($pb, $path, $operator, $value) {
    
    // build up an array for separating the variables of the sparql 
    // subqueries.
    // only the first var x0 get to be the same so that everything maps
    // to the same entity
    $starting_position = $pb->getRelativeStartingPosition($path, TRUE);
#    dpm($path, "path");
#    dpm($starting_position, "start");
    $vars[$starting_position] = "x0";
    $i = $this->varCounter++;
    for ($j = count($path->getPathArray()); $j > $starting_position; $j--) {
      $vars[$j] = "c${i}_x$j";
    }
    $vars['out'] = "c${i}_out";
    
    // arg 11 ($relative) must be FALSE, otherwise fields of subgroups yield
    // the entities of the subgroup
    $query_part = $this->getEngine()->generateTriplesForPath($pb, $path, $value, NULL, NULL, 0, $starting_position, FALSE, $operator, 'field', FALSE, $vars);

#\Drupal::logger('query path cond')->debug("path ".$path->id() ." {v} qc {d} qp $query_part", array("d"=>join(";", $path->getPathArray()), "v"=>join("/", $vars)));
    return $query_part;
    
  }


  /**
   * {@inheritdoc}
   */
  public function existsAggregate($field, $function, $langcode = NULL) {
    return $this->conditionAggregate->exists($field, $function, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function notExistsAggregate($field, $function, $langcode = NULL) {
    return $this->conditionAggregate->notExists($field, $function, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function conditionAggregateGroupFactory($conjunction = 'AND') {
    return new ConditionAggregate($conjunction, $this);
  }

  
  /** Places a screen and log message for functionality that is not implemented (yet).
   * 
   */
  protected function missingImplMsg($msg, $data) {
    drupal_set_message("Missing entity query implementation: $msg. See log for details.", 'error');
    \Drupal::logger("wisski entity query")->warning("Missing entity query implementation: $msg. Data: {data}", array('data' => serialize($data)));
  }

}
