<?php

namespace Drupal\wisski_triplify;

use Drupal\wisski_salz\AdapterHelper;

/**
 *
 */
class TriplifyManager {

  protected $pbs = NULL;
  protected $adapters = [];

  /**
   *
   */
  public function triplify($entity) {
    $ts = microtime(TRUE);

    // We reload the entity to be sure to get the disamb info as property/value.
    // the entity object passed for save does not contain it by now
    // TODO: always add wisskiDisamb to properties/values.
    $entity = entity_load('wisski_individual', $entity->id());

    $fields_by_type = \Drupal::config('wisski_triplify.triplify_fields')->get('by_type');
    $fields_by_id = \Drupal::config('wisski_triplify.triplify_fields')->get('by_id');

    $uris = AdapterHelper::getOnlyOneUriPerAdapterForDrupalId($entity->id());
    // $uris = AdapterHelper::getUrisForDrupalId($entity->id());
    // dpm($uris, "uris!");.
    if (empty($uris)) {
      return [];
    }

    // Here go the triples from all fields, we do one big write.
    $triples = [];

    $definitions = $entity->getFieldDefinitions();
    $fields = $entity->getFields(FALSE);

    foreach ($definitions as $name => $field_def) {
      $config = NULL;
      if (isset($fields_by_id[$name])) {
        $config = $fields_by_id[$name];
      }
      elseif (isset($fields_by_type[$field_def->getType()])) {
        $config = $fields_by_type[$field_def->getType()];
      }
      if (!empty($config) && (!isset($config['disabled']) || !$config['disabled'])) {
        $field_item_list = $fields[$name];

        $lang = $field_item_list->getLangcode();

        foreach ($field_item_list as $weight => $item) {
          $properties = $item->getProperties();

          if (isset($config['constraints'])) {
            // The constraints value is an array of constraints that get OR'ed.
            // Each array element is either a single constraint or and array of
            // constraints that get AND'ed.
            $passed = FALSE;
            foreach ($config['constraints'] as $on_and => $constraint_and) {
              if (!is_array($constraint_and)) {
                // Make it an array withsinlge element to treat it the same.
                $constraint_and = [$on_and => $constraint_and];
              }
              foreach ($constraint_and as $on => $constraint) {
                if (substr($on, 0, 9) == 'property:') {
                  $on_prop = substr($on, 9);
                  if (!preg_match("/$constraint/u", $properties[$on_prop]->getValue())) {
                    // AND failed, next OR branch.
                    continue 2;
                  }
                }
              }
              $passed = TRUE;
              break;
            }
            if (!$passed) {
              // Field item did not fulfil constraints.
              continue;
            }
          }

          $pipe_id = isset($config['pipe']) ? $config['pipe'] : 'triplify_html_links';
          $ticket = 'triplify-' . \Drupal::service('uuid')->generate();
          $adapters = isset($config['adapters']) ? $config['adapters'] : NULL;
          if (empty($adapters)) {
            $adapters = [];
            $pbs = $this->getPbs();
            foreach ($pbs as $pbid => $pb) {
              $paths = $pb->getPathsForFieldId($name);
              if (!empty($paths)) {
                $adapters[] = $pb->getAdapterId();

              }
            }
          }

          $disamb_uri = NULL;
          $disamb_eid = NULL;
          $disamb_uris = [];
          $tmp = $item->getValue();
          if (isset($tmp['wisskiDisamb'])) {
            $disamb_uri = $tmp['wisskiDisamb'];
            $disamb_eid = AdapterHelper::getOnlyOneUriPerAdapterForDrupalId($disamb_uri);
            // $disamb_eid = AdapterHelper::getDrupalIdForUri($disamb_uri);
            // if (!empty($disamb_eid)) {
            // $disamb_uris = AdapterHelper::getUrisForDrupalId($disamb_eid);
            // }
          }

          // dpm($adapters, "found adap");.
          foreach ($adapters as $aid) {
            // dpm($uris[$aid], "uris!" . $aid);.
            if (isset($uris[$aid])) {
              $pref_disamb_uri = isset($disamb_uris[$aid]) ? $disamb_uris[$aid] : $disamb_uri;
              $data = [
                'entity_uri' => $uris[$aid],
                'entity' => $entity,
                'text' => $properties[$config['text']]->getValue(),
                'adapter_id' => $aid,
                'field_id' => $name,
                'disamb_uri' => $pref_disamb_uri,
              ];
              $pipe_result = \Drupal::service('wisski_pipe.pipe')->run($pipe_id, $data, $ticket, \Drupal::logger('triplify'));
              if (isset($pipe_result['triples']) && !empty($pipe_result['triples'])) {
                if (!isset($triples[$aid])) {
                  $triples[$aid] = $pipe_result['triples'];
                }
                else {
                  $triples[$aid] = array_merge($triples[$aid], $pipe_result['triples']);
                }
              }
            }
          }
        }

      }

    }

    // dpm($triples, "trip!");.
    foreach ($triples as $aid => $triples_array) {
      $doc_inst = $uris[$aid];
      $adapter = $this->getAdapter($aid);
      \Drupal::logger('wisski triplify')->info("Dropping text graph <{g}>", ['g' => $doc_inst]);
      $drop_graphs = ["DROP GRAPH <$doc_inst>"];
      $inserts = [];
      foreach ($triples_array as $ta) {
        $graph = isset($ta['graph']) ? $ta['graph'] : "<$doc_inst>";
        $triple_str = join("\n  ", $ta['triples']);
        $inserts[] = "INSERT DATA { GRAPH $graph {\n  $triple_str\n} }";
        \Drupal::logger('wisski triplify')->info("Inserting {c} triples into text graph {g} in adapter {a}: {t}", ['g' => $graph, 'c' => count($ta['triples']), 't' => $triple_str, 'a' => $aid]);
      }

      $update = join('; ', $drop_graphs) . '; ' . join('; ', $inserts);
      $adapter->getEngine()->directUpdate($update);
    }

    // dpm(microtime(TRUE) - $ts, 'time for ' . $entity->id());
  }

  /**
   *
   */
  protected function getAdapter($aid) {
    if (!isset($this->adapters[$aid])) {
      $this->adapters[$aid] = entity_load('wisski_salz_adapter', $aid);
    }
    return $this->adapters[$aid];
  }

  /**
   *
   */
  protected function getPbs() {
    if ($this->pbs === NULL) {
      $this->pbs = entity_load_multiple('wisski_pathbuilder');
    }
    return $this->pbs;
  }

}
