<?php

/**
 * @file
 * Contains \Drupal\wisski_apus\Plugin\Linkit\Matcher\WisskiEntityMatcher.
 */

namespace Drupal\wisski_apus\Plugin\Linkit\Matcher;

use Drupal\Core\Form\FormStateInterface;
use Drupal\linkit\Plugin\Linkit\Matcher\EntityMatcher;
use Drupal\wisski_core\WissKICacheHelper;
use Drupal\wisski_salz\AdapterHelper;

/**
 * @Matcher(
 *   id = "entity:wisski_individual",
 *   target_entity = "wisski_individual",
 *   label = @Translation("WissKI Content"),
 *   provider = "wisski_apus"
 * )
 */
class WisskiEntityMatcher extends EntityMatcher {
  
  protected $limit = 30;

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $summery = '';
    return $summery;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return parent::calculateDependencies() + [
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function getMatches($string) {

    \Drupal::logger("WissKI APUS")->debug("query: $string");
    
    $matches = array();
    if ($string) {
      
      $results = db_select('wisski_title_n_grams', 'm')->fields('m', array('ent_num', 'bundle', 'ngram'))->condition('ngram', '%' . db_like($string) . '%', 'LIKE')->execute();
      $entities = array();
      while ($result = $results->fetchObject()) {
        $entities[$result->ent_num][$result->bundle] = $result->ngram;
      }
      
      foreach ($entities as $entity_id => $bundled_title) {
        $uri = AdapterHelper::generateWisskiUriFromId($entity_id);
        if (empty($uri)) continue;

        $default_title = "";
        if (isset($bundled_title['default'])) {
          $default_title = $bundled_title['default'];
          unset($bundled_title['default']);
        }
        
        $entity_matched = FALSE;
        foreach ($bundled_title as $bundle_id => $title) {

          if (stripos($title,$string) !== FALSE) {
          
            $entity = entity_load('wisski_bundle', $bundle_id);
            
            if(empty($entity))
              continue;
          
            $matches[] = [
              'title' => $title,
              'description' => '',
              'path' => $uri,
              'group' => $entity->label(),
            ];
            $entity_matched = TRUE;
            if (count($matches) >= $this->limit) break 2;
          }
        }
        if (!$entity_matched && $default_title && stripos($default_title,$string) !== FALSE) {
            $matches[] = [
              'title' => $default_title,
              'description' => '',
              'path' => $uri,
              'group' => "",
            ];
            if (count($matches) >= $this->limit) break 1;
        }
      }
    }
\Drupal::logger("wisski matcher")->info("matches !{n1}!{n2}!", array("n1" => count($entities), "n2" => count($matches)));
  
    return $matches;

  }

}
