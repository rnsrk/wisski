<?php
/**
 * @file
 * Contains \Drupal\wisski_pathbuilder\Form\WisskiPathbuilderConfigureFieldForm
 */
 
namespace Drupal\wisski_pathbuilder\Form;

use Drupal\Core\Form\FormStateInterface; 
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Url;

/**
 * Class WisskiPathbuilderForm
 * 
 * Fom class for adding/editing WisskiPathbuilder config entities.
 */
 
class WisskiPathbuilderConfigureFieldForm extends EntityForm {

  
  protected $pathbuilder = NULL;
  protected $path = NULL;
  
  /**
   * @{inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $wisski_pathbuilder = NULL, $wisski_path = NULL) { 

    // the form() function will not accept additional args,
    // but this function does
    // so we have to override this one to get hold of the pb id
    $this->pathbuilder = $wisski_pathbuilder;
    drupal_set_message(serialize($wisski_path));
    $this->path = $wisski_path;
    return parent::buildForm($form, $form_state, $wisski_pathbuilder, $wisski_path);
  }
  
  private function recursive_find_element($pathtree, $element) {
    $found = NULL;
    foreach($pathtree as $path) {
      if($path['id'] == $element) {
        $found = $path;
        break;
      } else if(!empty($path['children'])) {
        $found = $this->recursive_find_element($path['children'], $element);
        if($found != NULL) 
          break;
      }
    }
    return $found;
  }

   /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
  
    $form = parent::form($form, $form_state);

    $form['pathbuilder'] = array(
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('Pathbuilder'),
      '#default_value' => empty($this->pathbuilder->getName()) ? $this->t('Name for the pathbuilder') : $this->pathbuilder->getName(),
      '#disabled' => true,
      '#description' => $this->t("Name of the pathbuilder."),
      '#required' => true,
    );
        
    $form['path'] = array(
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('Path'),
      '#default_value' => empty($this->path) ? $this->t('Name for the pathbuilder') : $this->path,
      '#disabled' => true,
      '#description' => $this->t("Name of the path."),
      '#required' => true,
    );

    
#    drupal_set_message(serialize($this->pathbuilder->getPathTree()));
    
    $tree = $this->pathbuilder->getPathTree();

    $element = $this->recursive_find_element($tree, $this->path);
    
    $form['bundle'] = array(
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('bundle'),
      '#default_value' => empty($element['bundle']) ? $this->t('Something') : $element['bundle'],
#      '#disabled' => true,
      '#description' => $this->t("Name of the bundle."),
      '#required' => true,
    );
    
    $form['field'] = array(
      '#type' => 'textfield',
      '#maxlength' => 255,
      '#title' => $this->t('Field'),
      '#default_value' => empty($element['field']) ? $this->t('Something2') : $element['field'],
#      '#disabled' => true,
      '#description' => $this->t("Name of the Field."),
      '#required' => true,
    );
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $field_name = $form_state->getValue('field');
    $bundle = $form_state->getValue('bundle');

    $field_storage_values = [
      'field_name' => $field_name,#$values['field_name'],
      'entity_type' => 'wisski_individual',
      'type' => 'text',//has to fit the field component type, see below
      'translatable' => TRUE,
    ];
    
    $field_values = [
      'field_name' => $field_name,
      'entity_type' => 'wisski_individual',
      'bundle' => $bundle,
      'label' => $field_name,
      // Field translatability should be explicitly enabled by the users.
      'translatable' => FALSE,
      'disabled' => FALSE,
    ];
    
#    $field_storage_values = array();

    // there this stands that in field_create_field was
 #   $field_values = array();

# drupal_set_message("bla: " . serialize($form_state->getValue('field')));

    $this->entityManager->getStorage('field_storage_config')->create($field_storage_values)->enable()->save();
    $this->entityManager->getStorage('field_config')->create($field_values)->save();
    entity_get_display('wisski_individual', $bundle, 'default')->setComponent($field_name, array(
        'type' => 'text_summary_or_trimmed',//has to fit the field type, see above
        'settings' => array('trim_length' => '200'),
        'weight' => 1,//@TODO specify a "real" weight
    ))->save();
    drupal_set_message(t('Created new field %field in bundle %bundle for this path',array('%field'=>$field_name,'%bundle'=>$bundle)));
    $form_state->setRedirect('entity.wisski_pathbuilder.edit_form',array('wisski_pathbuilder'=>$this->pathbuilder->id()));
  }

}
