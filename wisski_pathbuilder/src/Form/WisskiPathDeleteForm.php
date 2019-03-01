<?php

namespace Drupal\wisski_pathbuilder\Form;

use Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityConfirmFormBase;

/**
 * Form that handles the removal of Wisski Path entities.
 */
class WisskiPathDeleteForm extends EntityConfirmFormBase {

  private $pb_id;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {

    $path = $this->entity;
    // $this->pb_id = \Drupal::routeMatch()->getParameter('wisski_pathbuilder');
    $this->pb_id = \Drupal::request()->query->get('wisski_pathbuilder');

    return $this->t('Are you sure you want to delete this path: @id?', ['@id' => $path->getID()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    // drupal_set_message(htmlentities(new Url('entity.wisski_pathbuilder.overview')));
    // return new Url('entity.wisski_pathbuilder.overview');
    // $pb_entities = entity_load_multiple('wisski_pathbuilder');
    // $pb = 'pb';.
    if (isset($this->pb_id)) {
      $url = Url::fromRoute('entity.wisski_pathbuilder.edit_form', ['wisski_pathbuilder' => $this->pb_id]);
    }
    else {
      $url = Url::fromRoute('entity.wisski_pathbuilder.collection');
    }
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $path = $this->entity;
    $pbpath = NULL;
    $path_id = $path->getID();

    if (isset($this->pb_id) && $pb = WisskiPathbuilderEntity::load($this->pb_id)) {
      if ($pb->hasPbPath($path_id)) {
        $pbpath = $pb->getPbPath($path_id);

        $pb->removePath($path_id);
        $pb->save();
      }
    }

    drupal_set_message($this->t('The path @id has been deleted.', ['@id' => $path_id]));
    // $form_state->setRedirectUrl($this->getCancelUrl());
    // drupal_set_message("pb: " . serialize($pbpath));
    if (!empty($pbpath)) {
      if (!$path->isGroup()) {
        $form_state->setRedirect('entity.wisski_path.delete_core', ['wisski_pathbuilder' => $this->pb_id, 'wisski_field_id' => $pbpath['field'], 'wisski_field_type' => 'field']);
        // It is some kind of group.
      }
      else {
        // It is a subgroup via entity-reference.
        if (!empty($pbpath['parent'])) {
          $form_state->setRedirect('entity.wisski_path.delete_core', ['wisski_pathbuilder' => $this->pb_id, 'wisski_field_id' => $pbpath['bundle'], 'wisski_field_type' => 'both']);
        }
        else {
          $form_state->setRedirect('entity.wisski_path.delete_core', ['wisski_pathbuilder' => $this->pb_id, 'wisski_field_id' => $pbpath['bundle'], 'wisski_field_type' => 'bundle']);
        }
      }
    }
    else {
      $form_state->setRedirectUrl($this->getCancelUrl());
    }
    $path->delete();
  }

}
