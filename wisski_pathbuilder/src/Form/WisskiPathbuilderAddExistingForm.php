<?php

namespace Drupal\wisski_pathbuilder\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityForm;

/**
 * Class WisskiPathbuilderAddExistingForm.
 *
 * Fom class for adding/editing WisskiPath config entities.
 */
class WisskiPathbuilderAddExistingForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    // Load all paths that are available.
    $paths = entity_load_multiple('wisski_path');

    // Make an options array for the dropdown.
    $options = [];

    foreach ($paths as $path) {
      $options[$path->getID()] = $path->getName() . " (" . $path->getID() . ")";
    }

    $form['path'] = [
      '#type' => 'select',
      '#title' => $this->t('Available paths to add'),
      '#options' => $options,
    ];

    // Thats it.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    // Which one should be added?
    $value = $form_state->getValue('path');

    // Get the pb it should be added to.
    $pb = $this->entity;

    $path = entity_load('wisski_path', $value);

    // Do it if it is not already there.
    if (is_null($pb->getPbPath($value))) {
      $pb->addPathToPathTree($value, 0, $path->isGroup());
    }
    else {
      drupal_set_message("Path $value was already there... resetting his properties");
      $pb->addPathToPathTree($value, 0, $path->isGroup());
    }

    // Save it.
    $status = $pb->save();

    // $form_state->setRedirect('entity.wisski_pathbuilder.edit_form');.
    $redirect_url = Url::fromRoute('entity.wisski_pathbuilder.edit_form')
      ->setRouteParameters(['wisski_pathbuilder' => $pb->id()]);

    $form_state->setRedirectUrl($redirect_url);

  }

}
