<?php

namespace Drupal\wisski_autocomplete\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\WidgetInterface;
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
class WissKIAutocompleteWidget extends WidgetBase implements WidgetInterface {

  /**
   * The config storage of the field widget.
   *
   * @var Drupal/Core/Config/Config
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'fieldId' => NULL,
      'size' => 60,
      'placeholder' => '',
      'autocompleteLimit' => 10,
      'useTitlePattern' => TRUE,
    ] + parent::defaultSettings();
  }


  /**
   * Saves single key in settings array in field widget config.
   *
   * @param string $key
   *   Array key of the settings array.
   *
   * @param mixed $value
   *   Array value of the settings array.
   *
   * @return void
   */
  public function setSetting($key, $value){
    parent::setSetting($key, $value);
    $fieldId = $this->getSetting('fieldId');
    if($fieldId){
      // TODO: move $this->config into the constructor.
      $this->config = $this->config ?? \Drupal::service('config.factory')->getEditable("wisski.autocomplete");
      $this->config->set($fieldId, $this->settings)->save();
    }
  }

  /**
   * Saves setting array in field widget config.
   *
   * @param array $settings
   * @return void
   */
  public function setSettings(array $settings)
  {
    parent::setSettings($settings);
    $fieldId = $this->getSetting('fieldId');
    if($fieldId){
      // TODO: move $this->config into the constructor.
      $this->config = $this->config ?? \Drupal::service('config.factory')->getEditable("wisski.autocomplete");
      $this->config->set($fieldId, $this->settings)->save();
    }
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
    $element['autocompleteLimit'] = [
      '#type' => 'number',
      '#title' => t('Limit of autocomplete suggestions shown'),
      '#default_value' => $this->getSetting('autocompleteLimit'),
      '#description' => t('Limits the suggestions from the query results.'),
      '#min' => 5,
    ];

    $element['useTitlePattern'] = [
      '#type' => 'checkbox',
      '#title' => t('Use title pattern?'),
      '#default_value' => $this->getSetting('useTitlePattern'),
      '#description' => t('If checked, use title pattern, instead of field value.'),
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
    $autocompletelimit = $this->getSetting('autocompleteLimit');
    $useTitlePattern = $this->getSetting('useTitlePattern');
    if (!empty($autocompletelimit)) {
      $summary[] = t('Autocomplete limit: @autocompletelimit', ['@autocompletelimit' => $autocompletelimit]);
    }
    if (!empty($placeholder)) {
      $summary[] = t('Placeholder: @placeholder', ['@placeholder' => $placeholder]);
    }
    if (!empty($useTitlePattern)) {
      $summary[] = t('Show title pattern.');
    } else {
      $summary[] = t('Show field value.');
    }

    return $summary;
  }


  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $fieldId = $items[$delta]->getFieldDefinition()->get('field_name');

    $this->setSetting('fieldId', $fieldId);

    $element['value'] = $element + [
      '#type' => 'textfield',
      '#default_value' => $items[$delta]->value ?? NULL,
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder'),
      '#maxlength' => $this->getFieldSetting('max_length'),
      '#attributes' => ['class' => ['js-text-full', 'text-full']],
      '#autocomplete_route_name' => 'wisski.wisski_autocomplete.autocomplete',
      '#autocomplete_route_parameters' => [
        'fieldId' => $fieldId,
      ],
    ];

    // We need to set the paramenter within some settings?
    return $element;
  }

}
