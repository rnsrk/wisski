<?php

namespace Drupal\wisski_core\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\wisski_salz\AdapterHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\Core\Url;
use Drupal\Core\Database\Database;

use Drupal\wisski_salz\RdfSparqlUtil;

/**
 *
 */
class WisskiConfigForm extends FormBase {

  /**
   *
   */
  public function getFormId() {

    return 'wisski_core_display_settings_form';
  }

  /**
   *
   */
  public function sparqlEntityStringlifier(object $sparqlEntity) {
    // If it is a ressource bracket it in angle brackets.
    if (get_class($sparqlEntity) == "EasyRdf\Resource") {
      return "<" . $sparqlEntity->getUri() . ">";
    }
    else {
      // Quote it and add language flag.
      $literal = $sparqlEntity->getValue();
      $escapedLiteral = (new RdfSparqlUtil)->escapeSparqlLiteral($literal);
      return '"' . $escapedLiteral . '"' . (empty($sparqlEntity->getLang()) ? '' : "@" . $sparqlEntity->getLang());
    }
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $request_query = \Drupal::request()->query;
    // dpm($request_query,'HTTP GET');.
    if ($request_query->get('q') === 'flush_salz') {
      // db_truncate('wisski_salz_id2uri');.
      $options['target'] = 'default';

      // First check if the table exists and if so rename it with the date in front to
      // have a backup!
      if (Database::getConnection($options['target'])->schema()->tableExists("wisski_salz_id2uri")) {
        Database::getConnection($options['target'])->schema()->renameTable("wisski_salz_id2uri", date("Y-m-d") . "wisski_salz_id2uri");
      }

      // Recreate the table from schema!
      $schema = drupal_get_module_schema("wisski_salz");

      Database::getConnection($options['target'])->schema()->createTable("wisski_salz_id2uri", $schema['wisski_salz_id2uri']);

      // Now we need to empty
      //      Database::getConnection($options['target'])->truncate('wisski_salz_id2uri', $options)->execute();
      $this->messenger()->addStatus('Flushed the ID cache');
    }

    if ($request_query->get('q') === 'flush_originates') {

      $pref_local = AdapterHelper::getPreferredLocalStore();

      $export_root = 'public://wisski_triple_backups/';

      $preparedExportDirectory = \Drupal::service('file_system')
        ->prepareDirectory($export_root, FileSystemInterface::CREATE_DIRECTORY);
      // If folder is not writable, escape.
      if (!$preparedExportDirectory) {
        \Drupal::service('messenger')
          ->addError($this->t('Could not create archive at %relativeExportDirectory. Do you have the right permissions?', ['%relativeExportDirectory' => $export_root]));
        // Return FALSE;.
      }
      else {
        $engine = $pref_local->getEngine();

        $query = "SELECT ?s ?p ?o ?g WHERE { GRAPH ?g { ?s ?p ?o } . FILTER(?g = <" . $engine->getDefaultDataGraphUri() . "originatesFrom" . ">) }";

        $results = $engine->directQuery($query);

        foreach ($results as $nquad) {
          // Parse stdClass to php array.
          $sparqlEntityArray = [];
          foreach ($nquad as $sparqlEntity) {
            $sparqlEntityArray[] = $sparqlEntity;
          }
          // Convert SPARQL entities to statement string and add to array.
          $quadStatement[] = implode(" ", array_map('self::sparqlEntityStringlifier', $sparqlEntityArray));
        }

        // Parse statement array to string.
        $nQuads = implode(" . \n", $quadStatement) . ' .';

        $export_path = $export_root . date("Y-m-d") . "originatesFrom.nq";

        \Drupal::service('file.repository')->writeData($nQuads, $export_path, FileSystemInterface::EXISTS_REPLACE);

        $query = "DROP GRAPH <" . $engine->getDefaultDataGraphUri() . "originatesFrom>";

        $results = $engine->directUpdate($query);

      }

      $this->messenger()->addStatus('Flushed the OriginatesFrom');
    }

    $settings = $this->configFactory()->getEditable('wisski_core.settings');

    $form['#wisski_settings'] = $settings;

    $form['bundles'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('WissKI Bundles'),
    ];
    $form['bundles']['disclaimer'] = [
      '#type' => 'item',
      '#markup' => $this->t('This links to Drupal\'s standard configuration pages.'),
    ];
    $form['bundles']['form_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Go to WissKI bundle settings page'),
      '#url' => Url::fromRoute('entity.wisski_bundle.list'),
    ];

    $form['default_title_pattern'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Change default entity title pattern'),
    ];
    $form['default_title_pattern']['disclaimer'] = [
      '#type' => 'item',
      '#markup' => $this->t('The default pattern as the default pattern for all bundles having no other one specified. It may also be used as a fallback for empty titles.'),
    ];
    $form['default_title_pattern']['form_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Go to title generation page'),
      '#url' => Url::fromRoute('wisski.default_title_pattern_form'),
    ];

    $form['flush'] = [
      '#type' => 'details',
      '#title' => $this->t('Flush WissKI caches'),
    ];
    $form['flush']['disclaimer'] = [
      '#type' => 'item',
      '#markup' => $this->t('This will flush the \'wisski_salz_id2uri\' database table but keep the local store info untouched. DANGER ZONE!!! Only do this if you know what you are doing!'),
    ];
    $form['flush']['id2uri'] = [
      '#type' => 'link',
      '#title' => $this->t('Flush EntityID - URI matching table<br/>'),
      '#url' => Url::fromRoute('<current>', ['q' => 'flush_salz']),
    ];
    $form['flush']['originates'] = [
      '#type' => 'link',
      '#title' => $this->t('Flush OriginatesFrom in TS<br/>'),
      '#url' => Url::fromRoute('<current>', ['q' => 'flush_originates']),
    ];

    $subform = [
      '#type' => 'fieldset',
      '#title' => $this->t('Miscellaneous Settings'),
    ];

    $subform['use_only_main_bundles'] = [
      '#type' => 'checkbox',
      '#default_value' => $settings->get('wisski_use_only_main_bundles'),
      '#title' => $this->t('Do you want to use only main bundles for display?'),
    ];

    $subform['use_views_for_navigate'] = [
      '#type' => 'checkbox',
      '#default_value' => $settings->get('wisski_use_views_for_navigate'),
      '#title' => $this->t('Do you want to use views for navigation?'),
    ];

    $subform['enable_published_status_everwhere'] = [
      '#type' => 'checkbox',
      '#default_value' => $settings->get('enable_published_status_everwhere'),
      '#title' => $this->t('Do you want to enable published status everywhere? You need to clear cache after enabling!'),
    ];

    $subform['use_get_canonical'] = [
      '#type' => 'checkbox',
      '#default_value' => $settings->get('use_get_canonical'),
      '#title' => $this->t('Use /wisski/get?uri= links instead of /wisski/navigate where possible. (E.g. preview images, titles, LinkIt suggestions)'),
    ];

    $subform['enableAutocompleteEverywhere'] = [
      '#type' => 'checkbox',
      '#default_value' => is_null($settings->get('wisski_enableAutocompleteEverywhere')) ? TRUE : $settings->get('wisski_enableAutocompleteEverywhere'),
      '#title' => $this->t('Use autocomplete for all input fields. For detailed configuration options use the <em>WissKI Autocomplete Widget</em> of the <em>WissKI Autocomplete Module</em>.)'),
      '#disabled' => TRUE,
    ];

    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('wisski_autocomplete')) {
      $subform['enableAutocompleteEverywhere']['#disabled'] = FALSE;
    }

    $subform['pager_max'] = [
      '#type' => 'number',
      '#default_value' => $settings->get('wisski_max_entities_per_page'),
      '#min' => 1,
      '#max' => 1000,
      '#step' => 1,
      '#title' => $this->t('Maximum number of entities displayed per list page'),
    ];
    $subform['pager_columns'] = [
      '#type' => 'number',
      '#default_value' => $settings->get('wisski_default_columns_per_page'),
      '#min' => 1,
      '#max' => 20,
      '#step' => 1,
      '#title' => $this->t('Maximum number of columns in navigate view'),
    ];

    $subform['preview_image'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Preview Image'),
      '#tree' => TRUE,
    ];
    $subform['preview_image']['adapters'] = [
      '#type' => 'select',
      '#title' => $this->t('Adapters'),
      '#options' => array_map(function ($a) {
        return $a->label();
        // entity_load_multiple('wisski_salz_adapter')),.
      }, \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->loadMultiple()),
      '#default_value' => $settings->get('preview_image_adapters'),
      '#multiple' => TRUE,
      '#description' => $this->t('The adapters that a preview image is search in.'),
    ];
    $subform['preview_image']['max_width'] = [
      '#type' => 'number',
      '#default_value' => $settings->get('wisski_preview_image_max_width_pixel'),
      '#min' => 10,
      '#step' => 1,
      '#title' => $this->t('Maximum width of entity list preview images in pixels'),
    ];
    $subform['preview_image']['max_height'] = [
      '#type' => 'number',
      '#default_value' => $settings->get('wisski_preview_image_max_height_pixel'),
      '#min' => 10,
      '#step' => 1,
      '#title' => $this->t('Maximum height of entity list preview images in pixels'),
    ];
    $form['subform'] = $subform;

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];
    return $form;
  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $settings = $form['#wisski_settings'];
    $new_vals = $form_state->getValues();
    $settings->set('wisski_use_only_main_bundles', $new_vals['use_only_main_bundles']);
    $settings->set('wisski_use_views_for_navigate', $new_vals['use_views_for_navigate']);
    $settings->set('wisski_enableAutocompleteEverywhere', $new_vals['enableAutocompleteEverywhere']);
    $settings->set('wisski_max_entities_per_page', $new_vals['pager_max']);
    $settings->set('wisski_default_columns_per_page', $new_vals['pager_columns']);
    $settings->set('wisski_preview_image_max_width_pixel', $new_vals['preview_image']['max_width']);
    $settings->set('wisski_preview_image_max_height_pixel', $new_vals['preview_image']['max_height']);
    $settings->set('preview_image_adapters', $new_vals['preview_image']['adapters']);
    $settings->set('enable_published_status_everwhere', $new_vals['enable_published_status_everwhere']);
    $settings->set('use_get_canonical', $new_vals['use_get_canonical']);
    $settings->save();
    $this->messenger()->addStatus($this->t('Changed global WissKI display settings'));
    $form_state->setRedirect('system.admin_config');
  }

}
