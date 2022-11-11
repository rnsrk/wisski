<?php

namespace Drupal\wisski_autocomplete\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'wisski_autocomplete' widget.
 *
 * @FieldWidget(
 *   id = "wisski_autocomplete_widget",
 *   label = @Translation("WissKI autocomplete widget"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class WissKIAutocompleteWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'size' => 60,
        'placeholder' => '',
        'autocompletelimit' => 10,
        'autocompletesorted' => FALSE,
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['size'] = [
        '#type' => 'number',
        '#title' => t('Size of textfield'),
        '#default_value' => $this->getSetting('size'),
        '#required' => TRUE,
        '#min' => 1,
      ];
      $element['placeholder'] = [
        '#type' => 'textfield',
        '#title' => t('Placeholder'),
        '#default_value' => $this->getSetting('placeholder'),
        '#description' => t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
      ];
      $element['autocompletelimit'] = [
        '#type' => 'number',
        '#title' => t('Limit of autocomplete suggestions shown'),
        '#default_value' => $this->getSetting('autocompletelimit'),
        '#min' => 10,
      ];
      $element['autocompletesorted'] = [
        '#type' => 'checkbox',
        '#title' => t('Sort autocomplete suggestions?'),
        '#default_value' => $this->getSetting('autocompletesorted'),
        '#description' => t('If expected value doesn\'t show up in autocomplete values sorting the results may be helpful.'),
      ];
      return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = t('Textfield size: @size', ['@size' => $this->getSetting('size')]);
    $placeholder = $this->getSetting('placeholder');
    if (!empty($placeholder)) {
      $summary[] = t('Placeholder: @placeholder', ['@placeholder' => $placeholder]);
    }
    $autocompletelimit = $this->getSetting('autocompletelimit');
    if (!empty($autocompletelimit)) {
      $summary[] = t('Autocomplete limit: @autocompletelimit', ['@autocompletelimit' => $autocompletelimit]);
    }
    $autocompletesorted = $this->getSetting('autocompletesorted');
    if (!empty($autocompletesorted)) {
      $summary[] = t('Sort autocomplete suggestions?: @autocompletesorted', ['@autocompletesorted' => $autocompletesorted]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['value'] = $element + [
        '#type' => 'textfield',
        '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : NULL,
        '#size' => $this->getSetting('size'),
        '#autocompletelimit' => $this->getSetting('autocompletelimit'),
        '#autocompletesorted' => $this->getSetting('autocompletesorted'),
        '#maxlength' => $this->getFieldSetting('max_length'),
        '#attributes' => ['class' => ['js-text-full', 'text-full']],
        '#autocomplete_route_name' => 'wisski.wisski_autocomplete.autocomplete'
      ];

      // we need to set the paramenter within some settings?
  
      return $element;
  }

}
