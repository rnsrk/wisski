<?php

namespace Drupal\wisski_core\Plugin\Linkit\Matcher;

use Drupal\linkit\Plugin\Linkit\Matcher\EntityMatcher;
use Drupal\linkit\Suggestion\SuggestionCollection;

/**
 * Provides default link matcher for wisski_individuals 
 * sets the Uuid to something that's not null to allow 
 * the js to function
 *
 * @Matcher(
 *   id = "entity:wisski_individual",
 *   label = @Translation("Wisski Entity"),
 *   target_entity = "wisski_individual",
 *   provider = "wisski_core",
 * )
 */
class WisskiEntityMatcher extends EntityMatcher {

  /**
   * {@inheritdoc}
   */
  public function execute($string) {
    $suggestions = parent::execute($string);
    $newSuggestions = new SuggestionCollection();

    foreach($suggestions->getSuggestions() as $suggestion){
      // set the Uuid in the suggestion 
      // to the URI or something that's not null
      // otherwise the linkit js doesn't work
      $exploded = explode("=",$suggestion->getPath(), 2);
      $uri = "Uri";
      if(count($exploded) == 2){
        $uri = $exploded[1];
      }
      $newSuggestion = $suggestion->setEntityUuid($uri);
      $newSuggestions->addSuggestion($newSuggestion);
    }

    return $newSuggestions;
  }
}
