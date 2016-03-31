<?php

namespace Drupal\wisski_core\Form;

//use \Drupal\Core\Entity\ContentEntityForm;
use \Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\wisski_core;

class WisskiEntityForm extends ContentEntityForm {

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form,$form_state);
    //@TODO extend form
    //dpm($this->getEntity());
    return $form;
  }

  public function save(array $form, FormStateInterface $form_state) {
    
    $entity = $this->getEntity();
    //dpm($entity);
    $this->copyFormValuesToEntity($entity,$form,$form_state);
    //dpm($entity);
    $entity->save();
    $bundle = $entity->get('bundle');
    dpm($bundle,__METHOD__);
    $form_state->setRedirect('<front>');
  }
  
}