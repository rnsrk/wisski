<?php

namespace Drupal\wisski_core\Form;

//use \Drupal\Core\Entity\ContentEntityForm;
use \Drupal\Core\Form\FormStateInterface;
use \Drupal\Core\Entity\ContentEntityForm;
use \Drupal\wisski_core;
use \Drupal\wisski_salz\AdapterHelper;

class WisskiEntityForm extends ContentEntityForm {

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form,$form_state);
    $form['#title'] = $this->t('Edit').' '.$this->entity->label();
#    drupal_set_message("Form is built.");
    //@TODO extend form
    //dpm($this->getEntity());
#    dpm($form,__METHOD__);
    return $form;
  }

  public function save(array $form, FormStateInterface $form_state) {
    
    $entity = $this->getEntity();
    //dpm($entity);
    $this->copyFormValuesToEntity($entity,$form,$form_state);
    //dpm($entity);
    $entity->save();
    $bundle = $entity->get('bundle')->getValue()[0]['target_id'];
    $drupalid = $entity->id();
#    $drupalid = AdapterHelper::getDrupalIdForUri($entity->id());
#    dpm($bundle,__METHOD__);
    $form_state->setRedirect(
      'entity.wisski_individual.canonical', 
#      'entity.wisski_individual.view', 
      array(
        'wisski_bundle' => $bundle,
        'wisski_individual' => $drupalid,
      )
    );
  }
  
}
