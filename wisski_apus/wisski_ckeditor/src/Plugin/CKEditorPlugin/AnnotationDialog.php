<?php

namespace Drupal\wisski_ckeditor\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\ckeditor\CKEditorPluginConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\Entity\Editor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the "wisski_ckeditor" plugin.
 *
 * @CKEditorPlugin(
 *   id = "wisski_annotation_dialog",
 *   label = @Translation("Annotation Sidebar"),
 *   module = "wisski_ckeditor"
 * )
 */
class AnnotationDialog extends CKEditorPluginBase implements CKEditorPluginConfigurableInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
        $configuration,
        $plugin_id,
        $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return drupal_get_path('module', 'wisski_ckeditor') . '/js/plugins/annotationDialog/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    $my_settings = $editor->getSettings()['plugins']['wisski_annotation_dialog'];
    return [
      'wisski_annotation_dialog' => [
        'active_on_load' => $my_settings['active_on_load'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [
      'wisski_apus/annotation.dialog',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    // Return array();
    return [
      'ToggleWisskiAnnotationDialog' => [
        'label' => t('Annotation Sidebar'),
        'image' => drupal_get_path('module', 'wisski_ckeditor') . '/js/plugins/annotationDialog/annotationDialog.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, Editor $editor) {
    $my_settings = $editor->getSettings()['plugins']['wisski_annotation_dialog'];

    $form['active_on_load'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active on load'),
      '#default_value' => isset($my_settings['active_on_load']) ? $my_settings['active_on_load'] : '',
      '#description' => $this->t('Whether the sidebar should be visible by default or not.'),
    ];

    return $form;
  }

}
