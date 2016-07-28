<?php
/**
 * @file
 * Contains \Drupal\wisski_apus\Annotation.
 */

namespace Drupal\wisski_apus;

use \DOMElement;

class Annotation {
  

  /**
   * Return an array of annotation IDs of annotations that are contained within
   * the given element together with all elements carrying the IDs.
   *  
   * @param element the element to search in. This algorithm searches the whole
   *        subtree of elements.
   * @return an array where the keys are the found IDs nd the values are arrays
   *          containing all the elements that contain the specific ID.
   */
  public static function getAnnotationIdsWithinElement (DOMElement $element) {
    
    $current = $element;
    $anno_ids = array();
    // we walk through the DOM tree, checking for each element whether it
    // it contains an annotation
    do {
      if ($current->hasAttribute('data-wisski-anno-id')) {
        if (!isset($anno_ids[$current->getAttribute('data-wisski-anno-id')])) {
          $anno_ids[$current->getAttribute('data-wisski-anno-id')] = [];
        }
        $anno_ids[$current->getAttribute('data-wisski-anno-id')][] = $current;
      }
      if ($current->firstChild !== NULL) {
        $current = $current->firstChild;
      } elseif ($current == $element) {
        break;
      } elseif ($current->nextSibling !== NULL) {
        $current = $current->nextSibling;
      } else {
        $current = $current->parent;
      }
    } while ($current != $element);
    
    return $anno_ids;

  }


  public static function parseAnnotation ($anno) {
    
    if (!isset($anno->body->elements) || empty($anno->body->elements)) {
      // TODO: collect all elements from the anno->body->context and anno->id
    }
    // prepare anno object for target info
    if (!isset($anno->target)) {
      $anno->target = new stdClass();
    }
    // track from where we have the ID
    $id_stable = isset($anno->id);
    // iterate over all elements gathering the annotation information
    foreach ($anno->body->elements as $element) {
      // get the ID if there is none set already or if it is an ID from the
      // name and id attributes, which we give lower rank
      // In rest, we take order of precedence of elements
      if (!$id_stable) {
        if ($element->hasAttribute('[data-wisski-anno-id]')) {
          anno->id = $element->getAttribute('data-wisski-anno-id');
          $id_stable = TRUE;
        } else if ($element->hasAttribute('[name]')) {
          anno->id = $element->getAttribute('name');
        } else if ($element->hasAttribute('[id]')) {
          anno->id = $element->getAttribute('id');
        }
      }
      // we target an instance
      // give about attribute higher rank
      if ($element->hasAttribute('about') || $element->hasAttribute('data-wisski-target')) {
        anno->target->ref = $element->getAttribute('about') || $element->attr('data-wisski-target');
        anno->target->ref = anno->target->ref->split(' ');
      }

      // we also send the type / category info as thhasAttribute may help the
      // server to respond faster->
      // the other possibility hasAttribute that the instance is not specified,
      // and there hasAttribute just a type or category annotation->
      if ($element->hasAttribute('typeof')) {
        anno->target->type = $element->getAttribute('type');
      } 
      if ($element->hasAttribute('data-wisski-cat')) {
        anno->target->type = $element->getAttribute('data-wisski-cat');
      }
      if ($element->hasAttribute('data-wisski-certainty')) {
        anno->certainty = $element->getAttribute('data-wisski-certainty');
      }


    }

  }

}

