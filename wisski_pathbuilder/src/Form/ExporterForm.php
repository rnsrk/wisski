<?php

namespace Drupal\wisski_pathbuilder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;


use Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity;

/**
 *
 */
class ExporterForm extends FormBase {

  protected $pbs = [];

  protected $pbsCN = [];

  protected $configManager = NULL;

  protected $config_types = [
    'entity_form_display',
    'entity_view_display',
    'field_config',
    'field_storage_config',
    'wisski_pathbuilder',
    'wisski_path',
    'wisski_bundle',
  ];

  protected $ignored_types = [];

  protected $duplicate_info = [];

  /**
   *
   */
  public function getFormId() {
    return 'wisski_pathbuilder_exporter_form';
  }

  /**
   *
   */
  protected function initPbs($id) {
    $this->pbs = WisskiPathbuilderEntity::loadMultiple(empty($id) ? NULL : [$id]);
    foreach ($this->pbs as $pb) {
      $this->pbsCN[$pb->getConfigDependencyName()] = $pb;
    }
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state, $pbid = NULL) {
    $this->configManager = \Drupal::service('config.manager');

    $config_form = [
      '#type' => 'details',
      '#title' => $this->t('Available configuration entities'),
    ];
    // At top of tree are always the pathbuilders
    // if a $pbid is given, only this pb will be used
    // otherwise we show all pbs.
    $this->initPbs($pbid);
    foreach ($this->pbsCN as $config_name => $pb) {
      $config_form = $this->buildConfigList($config_form, [], $config_name);
    }

    $form += [
      '#tree' => TRUE,
      'config' => $config_form,
      'ignored_types' => [
        '#type' => 'details',
        '#title' => $this->t('Ignored'),
        'listing' => [
          '#markup' => join(', ', $this->ignored_types),
        ],
      ],
      'actions' => [
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Export'),
        ],
      ],
    ];
    return $form;

  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config_names = $this->selectedConfigNames($form_state);
    ksort($config_names);

    $config_assemblage = [];
    $this->configManager = \Drupal::service('config.manager');
    foreach ($config_names as $config_name => $bla) {
      $config = $this->configManager->loadConfigEntityByName($config_name);
      if (!empty($config)) {
        $config_assemblage[$config_name] = $config->toArray();
      }
    }

    $yaml = Yaml::encode($config_assemblage);

    // We must not use yml extension as it is blocked by htaccess.
    $file = file_save_data($yaml, "public://pb_bundle_export.yaml");
    $uri = $file->url();
    // $link = \Drupal\Core\Link::fromTextAndUrl($uri, \Drupal\Core\Url::fromUri($uri));
    drupal_set_message(t("You can download it here: <a href='$uri'>$uri</a>"));

  }

  /**
   *
   */
  protected function buildConfigList($form, $parents, $config_name) {
    // First we check if we care about this config entity type.
    $config_type = $this->configManager->getEntityTypeIdByName($config_name);
    if (!in_array($config_type, $this->config_types)) {
      $this->ignored_types[$config_type] = $config_type;
      return $form;
    }
    // Then we check if the entity exists and load it.
    $config = $this->configManager->loadConfigEntityByName($config_name);
    if (empty($config)) {
      return $form;
    }

    // We create an indented checkbox field.
    $field = [
      '#type' => 'checkbox',
      '#title' => $this->t('@l (@n)', ['@l' => $config->label(), '@n' => $config_name]),
    // For indenting.
      '#field_prefix' => str_repeat('&nbsp; ', count($parents)),
    ];
    if (isset($this->duplicate_info[$config_name])) {
      // This config name already appeared up in the tree. we only leave
      // a hint where the functional checkbox can be found.
      // generate a unique id for this duplicate field.
      $field += [
        '#disabled' => TRUE,
        '#value' => 0,
        '#description' => t('Duplicate of @d', ['@d' => $this->duplicate_info[$config_name]]),
      ];
      $field_id = $config_name . md5(join("", $parents));
      $form[$field_id] = $field;
      // We do not go further as this is only a duplicate info.
      return $form;
    }
    else {
      // First time that we meet this config name
      // and it also is a valid name (we could load the entity).
      // we create a functional checkbox.
      $form[$config_name] = $field;
    }

    // NOTE: if we came here, this is not a duplicate!
    // The config entity is loaded into $config.
    // We register where to find the checkbox in case we later encounter
    // duplicates.
    $config = $this->configManager->loadConfigEntityByName($config_name);
    $this->duplicate_info[$config_name] = join(' -> ', $parents);

    // Depending on the config entity type we recurse on config entities...
    // first we collect them in the $child_configs array and then we recurse.
    $child_configs = [];
    if ($config_type == 'wisski_pathbuilder') {
      // A pathbuilder
      // first we set the checkbox as selected if it is the only pb, ie. if it
      // was chosen in the url.
      if (count($this->pbs) == 1) {
        $form[$config_name]['#default_value'] = 1;
      }
      // We add as children all paths and groups in the pb, not respecting the
      // pb's tree structure.
      $pb = $this->pbsCN[$config_name];
      foreach ($pb->getPbPaths() as $pid => $path_info) {
        $child_configs[] = "wisski_pathbuilder.wisski_path.$pid";
      }
    }
    elseif ($config_type == 'wisski_path') {
      // A path or group
      // first we set the checkbox as selected if there is only one pb.
      if (count($this->pbs) == 1) {
        $form[$config_name]['#default_value'] = 1;
      }
      // We add the field and the bundle
      // we need to get the pb of the field which should be one up in the
      // parents list.
      $pb_config_name = $parents[count($parents) - 1];
      if (isset($this->pbsCN[$pb_config_name])) {
        $pb = $this->pbsCN[$pb_config_name];
        $pid = $config->getID();
        $path_info = $pb->getPbPath($pid);
        if (!empty($path_info)) {
          $bundle = $path_info['bundle'];
          $field = $path_info['field'];
          if ($field != WisskiPathbuilderEntity::CONNECT_NO_FIELD && $field != WisskiPathbuilderEntity::GENERATE_NEW_FIELD) {
            $child_configs[] = "wisski_core.wisski_bundle.$bundle";
            if ($path_info['parent'] != 0) {
              $field_bundle = $bundle;
              if ($config->isGroup()) {
                // Paths do mention directly their containing bundle while
                // subgroups do not (it's the target bundle) and we have to fetch it.
                $field_bundle = $pb->getPbPath($path_info['parent'])['bundle'];
              }
              $child_configs[] = "field.field.wisski_individual.$field_bundle.$field";
            }
          }
        }
      }
    }
    else {
      // For all other entity types we just search the dependencies.
      foreach ($this->configManager->findConfigEntityDependents('config', [$config_name]) as $dep_name => $bla) {
        $child_configs[] = $dep_name;
      }
      $deps = $config->getDependencies();
      if (isset($deps['config'])) {
        foreach ($deps['config'] as $dep_name) {
          $child_configs[] = $dep_name;
        }
      }

    }

    // Update the parents array and do the recursion.
    $new_parents = $parents;
    $new_parents[] = $config_name;
    foreach (array_unique($child_configs) as $child_config_name) {
      $form = $this->buildConfigList($form, $new_parents, $child_config_name);
    }

    return $form;

  }

  /**
   *
   */
  protected function selectedConfigNames(FormStateInterface $form_state) {
    $config_values = $form_state->getValue('config');
    if (empty($config_values)) {
      return [];
    }
    $selected = array_filter($config_values);
    return $selected;
  }

}
