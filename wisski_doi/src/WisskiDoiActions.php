<?php

namespace Drupal\wisski_doi;

use Drupal\user\Entity\User;
use Drupal\wisski_core\WisskiEntityInterface;

/**
 * Controller for DOI actions.
 */
class WisskiDoiActions {

  /**
   * Assembles metadata from WissKI individual.
   *
   * @param \Drupal\wisski_core\WisskiEntityInterface $wisskiIndividual
   *   The WissKI Individual.
   *
   * @return array
   *   The metadata of the WissKI individual.
   */
  public function getWisskiIndividualMetadata(WisskiEntityInterface $wisskiIndividual) {
    $revisionUser = $wisskiIndividual->getRevisionUser();
    if (!empty($revisionUser)) {
      $author = $revisionUser->getDisplayName();
    }
    else {
      $uid = $wisskiIndividual->get('uid')->getValue()[0]['target_id'];
      $author = User::load($uid)->getDisplayName();
    }
    return [
      "bundleId" => $wisskiIndividual->bundle(),
      'entityId' => $wisskiIndividual->id(),
      'author' => $author,
      'title' => $wisskiIndividual->label(),
      'creationDate' => date('d.m.Y H:i:s', $wisskiIndividual->getRevisionCreationTime()),
      'language' => $wisskiIndividual->language()->getId(),
    ];
  }

}
