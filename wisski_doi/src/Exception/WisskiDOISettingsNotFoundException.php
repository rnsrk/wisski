<?php

namespace Drupal\wisski_doi\Exception;

use Exception;

/**
 * Exception for incomplete settings
 */

class WisskiDOISettingsNotFoundException extends Exception{

  public function checkDOISetting($doiSettings) {
    foreach ($doiSettings as $setting => $value) {
      if (empty($value)) {
        throw new WisskiDOISettingsNotFoundException("'$setting' not set, please go to Configure->[WISSKI]->WissKI DOI settings and do so.");
      }
    }
  }

}
