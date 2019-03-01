<?php

namespace Drupal\wisski_textanly;

/**
 *
 */
trait AnnotationTrait {

  /**
   * Determine overlap of two ranges/intervals.
   *
   * The ranges boundaries must be signed integers.
   * Boundary values are interpreted to belong including for starting value
   * and excluding for ending value (character/string ranges).
   *
   * It is the caller's responsibility to make sure that s1 <= e1 and s2 <= e2.
   * No checks are done!
   *
   * @param s1 starting point of first range
   * @param e1 ending point of first range
   * @param s2 starting point of second range
   * @param e2 ending point of second range
   *
   * @return an array with following information:
   *   0 => allen relation: < > = s si f fi o oi d di m mi
   *         1 => # of overlap (absolute overlap)
   *         2 => % of overlap (relative relative)
   *
   * @author Martin Scholz
   */
  public function calculateOverlap($s1, $e1, $s2, $e2) {

    if ($s1 > $e2) {
      return ['>', 0, 0];
    }
    if ($s1 == $e2) {
      return ['mi', 0, 0];
    }
    if ($s2 == $e1) {
      return ['m', 0, 0];
    }
    if ($s2 > $e1) {
      return ['<', 0, 0];
    }
    $all_chars = 0.5 * (($e1 - $s1) + ($e2 - $s2));
    if ($s1 == $s2) {
      if ($e1 == $e2) {
        return ['=', $e1 - $s1, 100];
      }
      if ($e1 < $e2) {
        return ['s', $e1 - $s1, ($e1 - $s1) / $all_chars];
      }
      if ($e1 > $e2) {
        return ['si', $e1 - $s1, ($e2 - $s2) / $all_chars];
      }
    }
    if ($s1 < $s2) {
      if ($e1 == $e2) {
        return ['fi', $e2 - $s2, ($e2 - $s2) / $all_chars];
      }
      if ($e1 < $e2) {
        return ['o', $e1 - $s2, ($e1 - $s2) / $all_chars];
      }
      if ($e1 > $e2) {
        return ['di', $e2 - $s2, ($e2 - $s2) / $all_chars];
      }
    }
    if ($s1 > $s2) {
      if ($e1 == $e2) {
        return ['f', $e1 - $s1, ($e1 - $s1) / $all_chars];
      }
      if ($e1 < $e2) {
        return ['d', $e1 - $s1, ($e1 - $s1) / $all_chars];
      }
      if ($e1 > $e2) {
        return ['oi', $e2 - $s1, ($e2 - $s1) / $all_chars];
      }
    }

  }

  /**
   * Compares 2 annotations.
   *
   * Order:
   * 1) Approved before non-approved
   * 2) rank: higher before lower
   * 3) range: longer before shorter.
   *
   * @author Martin Scholz
   */
  public function compareAnnotations($a, $b) {
    // Approved anno always beats non-approved anno.
    if (isset($a->approved) && $a->approved) {
      if (!isset($b->approved) || !$b->approved) {
        return 1;
      }
    }
    elseif (isset($b->approved) && $b->approved) {
      return -1;
    }
    // If both are approved or not approved, the rank decides.
    if (isset($a->rank) && isset($b->rank)) {
      // The higher ranked anno wins.
      $c = $a->rank - $b->rank;
      if ($c) {
        return $c;
      }
    }
    elseif (isset($a->rank)) {
      // Annotation without rank is considered higher, because:
      // no rank generally means that the annotation comes from outside/user.
      return 1;
    }
    elseif (isset($a->rank)) {
      return -1;
    }
    // If rank is equal or not defined, the longer anno wins.
    $l = ($a->range[1] - $a->range[0]) - ($b->range[1] - $b->range[0]);
    return $l;
  }

  /**
   *
   *
   */
  public function getTokensForAnno($text_struct, $anno) {

    if (!isset($text_struct->tokens) || empty($text_struct->tokens)) {
      return NULL;
    }

    if (is_int($anno)) {
      if (!isset($text_struct->annos) || empty($text_struct->annos)) {
        return NULL;
      }
      $range = $text_struct->annos[$anno]->range;
    }
    elseif (is_array($anno) && isset($anno->range)) {
      $range = $anno->range;
    }
    elseif (is_array($anno) && count($anno) == 2) {
      $range = $anno;
    }
    else {
      return NULL;
    }

    $l = count($text_struct->tokens);

    $s = $e = NULL;
    for ($j = 0; $j < $l; $j++) {
      if ($textstruct->tokens[$j][2] > $range[0]) {
        $s = $j;
        break;
      }
    }
    for (++$j; $j < $l; $j++) {
      if ($textstruct->tokens[$j][2] >= $range[1]) {
        $e = $j;
        break;
      }
    }

    if ($s === NULL || $e === NULL) {
      return NULL;
    }
    return [$s, $e];

  }

}
