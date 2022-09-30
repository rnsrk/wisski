<?php

namespace Drupal\wisski_adapter_sparql11_pb\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'entity reference id' formatter.
 *
 * @FieldFormatter(
 *   id = "wisski_entity_reference_eid",
 *   label = @Translation("Entity ID (WissKI)"),
 *   description = @Translation("Display the Entity ID of the referenced entities for WissKI entities."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class WisskiEntityReferenceEntityIdFormatter extends EntityReferenceLabelFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'hidden' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements['hidden'] = [
      '#title' => t('hide values'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('hidden'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->getSetting('hidden') ? t('Hidden') : t('Non-hidden');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $elements = [];
    $hidden = $this->getSetting('hidden');

    foreach($items as $delta => $item) {
      if($hidden == TRUE)
        $elements[$delta] = ['#type' => "hidden", '#value' => $item->target_id];
      else
        $elements[$delta] = ['#plain_text' => $item->target_id];
    }


    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity) {
    return $entity->access('view label', NULL, TRUE);
  }

  /**
   * {@inheritdoc}
   *
   * Loads the entities referenced in that field across all the entities being
   * viewed.
   */
  public function prepareView(array $entities_items) {
  }

}
