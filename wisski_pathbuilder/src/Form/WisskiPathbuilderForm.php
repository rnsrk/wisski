<?php
/**
 * @file
 * Contains \Drupal\wisski_pathbuilder\Form\WisskiPathbuilderForm
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
 
class WisskiPathbuilderForm extends EntityForm {

   /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
  
    $form = parent::form($form, $form_state);
    
    // what entity do we work on?
    $pathbuilder = $this->entity;


#    drupal_set_message(serialize($pathbuilder->getMainGroups()));    
    // Change page title for the edit operation
    if($this->operation == 'edit') {
      $form['#title'] = $this->t('Edit Pathbuilder: @id', array('@id' => $pathbuilder->id()));
    }
    
    $form['name'] = array(
      '#type' => 'textfield',
#      '#maxlength' => 255,
      '#title' => $this->t('Name'),
      '#default_value' => $pathbuilder->getName(),
      '#description' => $this->t("Name of the Pathbuilder-Tree."),
      '#required' => true,
    );
    
    // we need an id
    $form['id'] = array(
      '#type' => 'machine_name',
#      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#default_value' => $pathbuilder->id(),
      '#disabled' => !$pathbuilder->isNew(),
      '#machine_name' => [
        'source' => array('name'),
        'exists' => ['\Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity.', 'load']
#        'source' => array('name'),
#        'exists' => 'wisski_pathbuilder_load',
      ],
    );

#    $adapters = entity_load_multiple('wisski_salz_adapter');
    
    $adapters = \Drupal\wisski_salz\Entity\Adapter::loadMultiple();
    
    $adapterlist = array();

    foreach($adapters as $adapter) {
      $adapterlist[$adapter->id()] = $adapter->label();#      drupal_set_message(serialize($adapters));
    }
        
    $form['adapter'] = array(
      '#type' => 'select',
      '#description' => $this->t('Which adapter does this Pathbuilder belong to?'),
#      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#default_value' => $pathbuilder->getAdapter(),
      '#options' => $adapterlist, #array(0 => "Pathbuilder"),
    );

    
  // Ensure that menu_overview_form_submit() knows the parents of this form
  // section.
#  if (!$form_state->has('pathbuilder_overview_form_parents')) {
#   $form_state->set('pathbuilder_overview_form_parents', []);
#  }

    // load the pathbuilder entity that is used - given by the parameter
    // in the url.                        
#    $pathbuilder_entity = entity_load('wisski_pathbuilder', $wisski_pathbuilder);
    
    // load all paths - here we should load just the ones of this pathbuilder
#    $path_entities = entity_load_multiple('wisski_path');
    #drupal_set_message('wisski pathbuilder id: ' . serialize($wisski_pathbuilder));    

    
    $header = array("title", "Path", array('data' => $this->t("Enabled"), 'class' => array('checkbox')), "Weight", array('data' => $this->t('Operations'), 'colspan' => 3));
     
    $form['pathbuilder_table'] = array(
      '#type' => 'table',
      '#theme' => 'table__menu_overview',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => array(
        'id' => 'my-module-table',
      ),
      '#tabledrag' => array(
        array(
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'menu-parent',
          'subgroup' => 'menu-parent',
          'source' => 'menu-id',
          'hidden' => TRUE,
          'limit' => 9,
        ),
        array(
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'menu-weight',
        ),
      ),
    );

#    drupal_set_message(serialize($this));

    $pathforms = array();
#    while(false) {
    // get all paths belonging to the respective pathbuilder
    foreach($pathbuilder->getPathTree() as $grouparray) {
      // $grouparray has structure: (ID, weight, enabled, children)
     # drupal_set_message('GROUP: ' . serialize($grouparray)); 
      $pathforms = array_merge($pathforms, $this->recursive_render_tree($grouparray));
    }
    
    #drupal_set_message('PATHFORMS: ' . serialize($pathforms));
    
#    return $form;

    foreach($pathforms as $pathform) {    
    
#      $pathform = $this->pb_render_path($path);

      $path = $pathform['#item'];

      $form['pathbuilder_table'][$path->id()]['#item'] = $pathform['#item'];
      
      // TableDrag: Mark the table row as draggable.
      $form['pathbuilder_table'][$path->id()]['#attributes'] = $pathform['#attributes'];
      $form['pathbuilder_table'][$path->id()]['#attributes']['class'][] = 'draggable';


      // TableDrag: Sort the table row according to its existing/configured weight.
#      $form['pathbuilder_table'][$path->id()]['#weight'] = $path->getWeight();

      // Add special classes to be used for tabledrag.js.
      $pathform['parent']['#attributes']['class'] = array('menu-parent');
      $pathform['weight']['#attributes']['class'] = array('menu-weight');
      $pathform['id']['#attributes']['class'] = array('menu-id');

      $form['pathbuilder_table'][$path->id()]['title'] = array(
          array(
            '#theme' => 'indentation',
            '#size' => $pathform['#item']->depth,
          ),
          $pathform['title'],
      );
      
      #$form['pathbuilder_table'][$path->id()]['path'] = array('#type' => 'label', '#title' => 'Mu -> ha -> ha');
      $form['pathbuilder_table'][$path->id()]['path'] = $pathform['path'];
      $form['pathbuilder_table'][$path->id()]['enabled'] = $pathform['enabled'];
      $form['pathbuilder_table'][$path->id()]['enabled']['#wrapper_attributes']['class'] = array('checkbox', 'menu-enabled');

      $form['pathbuilder_table'][$path->id()]['weight'] = $pathform['weight'];
      
      // an array of links that can be selected in the dropdown operations list
      $links = array();
      $links['edit'] = array(
        'title' => $this->t('Edit'),
       # 'url' => $path->urlInfo('edit-form', array('wisski_pathbuilder'=>$pathbuilder->getID())),
        'url' => \Drupal\Core\Url::fromRoute('entity.wisski_path.edit_form')
                   ->setRouteParameters(array('wisski_pathbuilder'=>$pathbuilder->getID(), 'wisski_path' => $path->getID())),
      );
      
      $links['fieldconfig'] = array(
        'title' => $this->t('Configure Field'),
       # 'url' => $path->urlInfo('edit-form', array('wisski_pathbuilder'=>$pathbuilder->getID())),
        'url' => \Drupal\Core\Url::fromRoute('entity.wisski_pathbuilder.configure_field_form')
                   ->setRouteParameters(array('wisski_pathbuilder'=>$pathbuilder->getID(), 'wisski_path' => $path->getID())),
      );

      $links['delete'] = array(
        'title' => $this->t('Delete'),
        'url' => \Drupal\Core\Url::fromRoute('entity.wisski_path.delete_form')
                   ->setRouteParameters(array('wisski_pathbuilder'=>$pathbuilder->getID(), 'wisski_path' => $path->getID())),
      );  
                                                             
      // Operations (dropbutton) column.
    #  $operations = parent::getDefaultOperations($pathbuilder);
      $operations = array(
        '#type' => 'operations',
        '#links' => $links,
      );
     # drupal_set_message('PATH ID: ' . $path->getID());
#      drupal_set_message('OPS: ' . serialize($operations));
     # drupal_set_message('ITEM: ' . serialize($pathform['#item']));
     # drupal_set_message('Link: ' . serialize($links['edit']));
     # $form['pathbuilder_table'][$path->id()]['operations'] = $pathform['operations'];       
      $form['pathbuilder_table'][$path->id()]['operations'] = $operations;

      $form['pathbuilder_table'][$path->id()]['id'] = $pathform['id'];
      $form['pathbuilder_table'][$path->id()]['parent'] = $pathform['parent'];
      
      $form['pathbuilder_table'][$path->id()]['bundle'] = $pathform['bundle'];
      $form['pathbuilder_table'][$path->id()]['field'] = $pathform['field'];
#      drupal_set_message(serialize($form['pathbuilder_table'][$path->id()]));
    }
    
    return $form;
  }
  
  private function recursive_render_tree($grouparray, $parent = 0, $delta = 0, $depth = 0) {
    $pathform[$grouparray['id']] = $this->pb_render_path($grouparray['id'], $grouparray['enabled'], $grouparray['weight'], $depth, $parent, $grouparray['bundle'], $grouparray['field']);
    
    if(is_null($pathform[$grouparray['id']])) {
      unset($pathform[$grouparray['id']]);
      return array();
    }
    
    foreach($grouparray['children'] as $childpath) {
      $subform = $this->recursive_render_tree($childpath, $grouparray['id'], $delta, $depth +1);
      $pathform = array_merge($pathform, $subform);
    }
    
    return $pathform;    
    
  }
  
  private function pb_render_path($pathid, $enabled, $weight, $depth, $parent, $bundle, $field) {
    $path = entity_load('wisski_path', $pathid);

    if(is_null($path))
      return NULL;
    
    $pathform = array();

    $item = array();
    
#    $item['#title'] = $path->getName();
    
    $path->depth = $depth;
    
    $pathform['#item'] = $path;
    
    
    $pathform['#attributes'] = $enabled ? array('class' => array('menu-enabled')) : array('class' => array('menu-disabled')); 
      
  #  $pathform['title'] = '<a href="/dev/contact" data-drupal-selector="edit-links-menu-plugin-idcontactsite-page-title-1" id="edit-links-menu-plugin-idcontactsite-page-title-1" class="menu-item__link">Contact</a>';
    #$path->name;
    $pathform['title'] = array('#type' => 'label', '#title' =>  $path->getName());   

    if (!$enabled) {
      $pathform['title']['#suffix'] = ' (' . $this->t('disabled') . ')';
    }
    
    $pathform['path'] = array(
      '#type' => 'item',
      '#markup' => $path->printPath(),
     );
     
     // if it is a group, mark it as such.
     if($path->isGroup()) {
       $pathform['path']['#markup']  = 'Group [' . $pathform['path']['#markup'];
       $pathform['path']['#markup'] .= ']';
     }
      
    $pathform['enabled'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enable @title path', array('@title' => $path->getName())),
      '#title_display' => 'invisible',
      '#default_value' => $enabled
    );

    $pathform['weight'] = array(
#      '#type' => 'weight',
      '#type' => 'textfield',
#      '#delta' => 100, # Do something more cute here $delta,
      '#default_value' => $weight,
      '#title' => $this->t('Weight for @title', array('@title' => $path->getName())),
      '#title_display' => 'invisible',
    );

    $pathform['id'] = array(
      '#type' => 'hidden',
      '#value' => $path->id(),
    );
    
    $pathform['parent'] = array(
      '#type' => 'hidden',
      '#value' => $parent,
    );
    
    $pathform['bundle'] = array(
      '#type' => 'hidden',
      '#value' => $bundle,
    );

    $pathform['field'] = array(
      '#type' => 'hidden',
      '#value' => $field,
    );
    
 #   $pathform['path'] = array(
  #    '#type' => 'label',
   #   '#title' => $path->getPathArray(),
      #'#default_value' => $path->getEnabled(),
   # );

    return $pathform;
  }
  
#  private function recursive_build_tree($pathdata) {
#    $pathtree = array();
#    foreach($pathdata as $path) {
##      drupal_set_message(serialize($path));
#      $pathtree[] = array('id' => $path['id'], 'weight' => $path['weight'], 'children' => array());
#    }
#    
#    return $pathtree;
#  }
  
  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    
    $pathbuilder = $this->entity;
 
#    drupal_set_message(serialize($form_state));

    $paths = $form_state->getValue('pathbuilder_table');

#    $pathtree = $pathbuilder->getPathTree();

#    drupal_set_message(serialize($pathtree));
    
    #drupal_set_message(serialize($paths));
    
    $pathtree = array();
    $map = array();
    
    foreach($paths as $key => $path) {
#      $pathtree = array_merge($pathtree, $this->recursive_build_tree(array($key => $path)));

      
      if(!empty($path['parent'])) { // it has parents... we have to add it somewhere
        $map[$path['parent']]['children'][$path['id']] = array('id' => $path['id'], 'weight' => $path['weight'], 'enabled' => $path['enabled'], 'children' => array(), 'bundle' => $path['bundle'], 'field' => $path['field']);
        $map[$path['id']] = &$map[$path['parent']]['children'][$path['id']];
      } else { // it has no parent - so it is a main thing
        $pathtree[$path['id']] = array('id' => $path['id'], 'weight' => $path['weight'], 'enabled' => $path['enabled'], 'children' => array(), 'bundle' => $path['bundle'], 'field' => $path['field']);
        $map[$path['id']] = &$pathtree[$path['id']];
      }

    }

#    drupal_set_message(serialize($pathtree));    
    $pathbuilder->setPathTree($pathtree);

#    drupal_set_message(serialize($pathbuilder));
    
    $status = $pathbuilder->save();
    
    if($status) {
      // Setting the success message.
      drupal_set_message($this->t('Saved the pathbuilder: @id.', array(
        '@id' => $pathbuilder->id(),
      )));
    } else {
      drupal_set_message($this->t('The Pathbuilder @id could not be saved.', array(
        '@id' => $pathbuilder->id(),
      )));
    }
    
    $form_state->setRedirect('entity.wisski_pathbuilder.collection');
 }
}
    
 