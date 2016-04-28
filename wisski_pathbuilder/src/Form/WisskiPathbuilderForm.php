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

    // Change page title for the edit operation
    if($this->operation == 'edit') {
      $form['#title'] = $this->t('Edit Pathbuilder: @id', array('@id' => $pathbuilder->id()));
    }
    
    // only show this in create mode
    if($this->operation == 'add') {
      $form['name'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#default_value' => $pathbuilder->getName(),
        '#description' => $this->t("Name of the Pathbuilder-Tree."),
        '#required' => true,
      );
    
      // we need an id
      $form['id'] = array(
        '#type' => 'machine_name',
        '#default_value' => $pathbuilder->id(),
        '#disabled' => !$pathbuilder->isNew(),
        '#machine_name' => [
          'source' => array('name'),
          'exists' => '\Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::load',
        ],
      );
    }

    // load all adapters    
    $adapters = \Drupal\wisski_salz\Entity\Adapter::loadMultiple();
    
    $adapterlist = array();

    // generate a list of all adapters
    foreach($adapters as $adapter) {
      $adapterlist[$adapter->id()] = $adapter->label();#      drupal_set_message(serialize($adapters));
    }
    
    // if we are in edit mode, the options are below so the table
    // is set more directly at the top. Furthermore in the create mode
    // the table is unnecessary.
    if($this->operation == 'edit') { 	   
      $header = array("title", "Path", array('data' => $this->t("Enabled"), 'class' => array('checkbox')), "Weight", array('data' => $this->t('Operations'), 'colspan' => 3));
     
      $form['pathbuilder_table'] = array(
        '#type' => 'table',
        '#theme' => 'table__menu_overview',
        '#header' => $header,
#      '#rows' => $rows,
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

      $pathforms = array();

      // get all paths belonging to the respective pathbuilder
      foreach($pathbuilder->getPathTree() as $grouparray) {
        $pathforms = array_merge($pathforms, $this->recursive_render_tree($grouparray));
      }
    
      // iterate through all the pathforms and bring the forms in a tree together
      foreach($pathforms as $pathform) {    
    
        $path = $pathform['#item'];
        
        $form['pathbuilder_table'][$path->id()]['#item'] = $pathform['#item'];
      
        // TableDrag: Mark the table row as draggable.
        $form['pathbuilder_table'][$path->id()]['#attributes'] = $pathform['#attributes'];
        $form['pathbuilder_table'][$path->id()]['#attributes']['class'][] = 'draggable';


        // TableDrag: Sort the table row according to its existing/configured weight.
        $form['pathbuilder_table'][$path->id()]['#weight'] = $path->weight;

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
                     ->setRouteParameters(array('wisski_pathbuilder'=>$pathbuilder->id(), 'wisski_path' => $path->id())),
        );
      
        $links['fieldconfig'] = array(
          'title' => $this->t('Configure Field'),
         # 'url' => $path->urlInfo('edit-form', array('wisski_pathbuilder'=>$pathbuilder->id())),
          'url' => \Drupal\Core\Url::fromRoute('entity.wisski_pathbuilder.configure_field_form')
                     ->setRouteParameters(array('wisski_pathbuilder'=>$pathbuilder->id(), 'wisski_path' => $path->id())),
        );

        $links['delete'] = array(
          'title' => $this->t('Delete'),
          'url' => \Drupal\Core\Url::fromRoute('entity.wisski_path.delete_form')
                   ->setRouteParameters(array('wisski_pathbuilder'=>$pathbuilder->id(), 'wisski_path' => $path->id())),
        );  
                                                             
        // Operations (dropbutton) column.
      #  $operations = parent::getDefaultOperations($pathbuilder);
        $operations = array(
          '#type' => 'operations',
          '#links' => $links,
        );

        $form['pathbuilder_table'][$path->id()]['operations'] = $operations;

        $form['pathbuilder_table'][$path->id()]['id'] = $pathform['id'];
        $form['pathbuilder_table'][$path->id()]['parent'] = $pathform['parent'];
      
        $form['pathbuilder_table'][$path->id()]['bundle'] = $pathform['bundle'];
        $form['pathbuilder_table'][$path->id()]['field'] = $pathform['field'];
#      drupal_set_message(serialize($form['pathbuilder_table'][$path->id()]));
      }
    }
    
    // additional information stored in a field set below       
    $form['additional'] = array(
      '#type' => 'fieldset',
      '#tree' => FALSE,
      '#title' => $this->t('Additional Settings'),
    );
    
    // only show this in edit mode
    if($this->operation == 'edit') {
      $form['additional']['name'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#default_value' => $pathbuilder->getName(),
        '#description' => $this->t("Name of the Pathbuilder-Tree."),
        '#required' => true,
      );
    
      // we need an id
      $form['additional']['id'] = array(
        '#type' => 'machine_name',
        '#default_value' => $pathbuilder->id(),
        '#disabled' => !$pathbuilder->isNew(),
        '#machine_name' => [
          'source' => array('additional', 'name'),
          'exists' => '\Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity::load',
        ],
      );
    }
    
    // change the adapter this pb belongs to?    
    $form['additional']['adapter'] = array(
      '#type' => 'select',
      '#description' => $this->t('Which adapter does this Pathbuilder belong to?'),
      '#default_value' => $pathbuilder->getAdapterId(),
      '#options' => $adapterlist, #array(0 => "Pathbuilder"),
    );

    // what is the create mode?    
    $form['additional']['create_mode'] = array(
      '#type' => 'select',
      '#description' => $this->t('What should be generated on save?'),
      '#default_value' => $pathbuilder->getCreateMode(),
      '#options' => array('field_collection' => 'field_collection', 'wisski_bundle' => 'wisski_bundle'),
    );
    
    $form['additional']['import'] = array(
      '#type' => 'fieldset',
      '#tree' => FALSE,
      '#title' => $this->t('Import Templates'),
    );
    
    $form['additional']['import']['import'] = array(
      '#type' => 'textfield',
      '#title' => 'Pathbuilder Definition Import',
      '#description' => $this->t('Path to a pathbuilder definition file.'),
#      '#default_value' => $pathbuilder->getCreateMode(),
#      '#options' => array('field_collection' => 'field_collection', 'wisski_bundle' => 'wisski_bundle'),
    );
    
    $form['additional']['import']['importbutton'] = array(
      '#type' => 'submit',
      '#value' => 'Import',
      '#submit' => array('::import'),
#      '#description' => $this->t('Path to a pathbuilder definition file.'),
#      '#default_value' => $pathbuilder->getCreateMode(),
#      '#options' => array('field_collection' => 'field_collection', 'wisski_bundle' => 'wisski_bundle'),
    );
    
    
    
    return $form;
  }
  
  public function import(array &$form, FormStateInterface $form_state) {
    
    $importfile = $form_state->getValue('import');

    $xmldoc = new \Symfony\Component\DependencyInjection\SimpleXMLElement($importfile, 0, TRUE);
    
    $pb = $this->entity;
    
    foreach($xmldoc->path as $path) {
      $parentid = html_entity_decode((int)$path->group_id);
      
#      if($parentid != 0)
#        $parentid = wisski_pathbuilder_check_parent($parentid, $xmldoc);
      
      $uuid = html_entity_decode((string)$path->uuid);
      
      #if(empty($uuid))
      
      // check if path already exists
      $path_in_wisski = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::load((int)$path->id);
      
      // it exists, skip this...
      if(!empty($path_in_wisski)) {
        drupal_set_message("Path with id " . $uuid . " was already existing - skipping.");
        
        $pb->addPathToPathTree($path_in_wisski->id());

        continue;
      }
      
      $path_array = array();
      $count = 0;
      foreach ($path->path_array->children() as $n) {
        $path_array[$count] = html_entity_decode((string) $n);
        $count++;
      }
      
      // it does not exist, create one!
      $pathdata = array(
        'id' => html_entity_decode((string)$path->id),
        'name' => html_entity_decode((string)$path->name),
        'path_array' => $path_array,
        'datatype_property' => html_entity_decode((string)$path->datatype_property),
        'short_name' => html_entity_decode((string)$path->short_name),
        'length' => html_entity_decode((string)$path->length),
        'disamb' => html_entity_decode((string)$path->disamb),
        'description' => html_entity_decode((string)$path->description),
        'type' => (((int)$path->is_group) === 1) ? 'Group' : 'Path', 
      );
      
      $path_in_wisski = \Drupal\wisski_pathbuilder\Entity\WisskiPathEntity::create($pathdata);
      
      $path_in_wisski->save();
      
      $pb->addPathToPathTree($path_in_wisski->id(), (int)$path->group_id);
      
    }
    
    $pb->save();
    
  }
  
  private function recursive_render_tree($grouparray, $parent = 0, $delta = 0, $depth = 0) {
    // first we have to get any additional fields because we just got the tree-part
    // and not the real data-fields
    $pbpath = $this->entity->getPbPath($grouparray['id']);
        
    // if we did not get something, stop.
    if(empty($pbpath))
      return array();

    // merge it into the grouparray    
    $grouparray = array_merge($grouparray, $pbpath);
    
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
    
    return $pathform;
  }
    
  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    // get the pathbuilder    
    $pathbuilder = $this->entity;
 
    // fetch the paths
    $paths = $form_state->getValue('pathbuilder_table');
    
    $pathtree = array();
    $map = array();
    
    foreach($paths as $key => $path) {
#      $pathtree = array_merge($pathtree, $this->recursive_build_tree(array($key => $path)));

      
      if(!empty($path['parent'])) { // it has parents... we have to add it somewhere
        $map[$path['parent']]['children'][$path['id']] = array('id' => $path['id'], 'children' => array());
        $map[$path['id']] = &$map[$path['parent']]['children'][$path['id']];
      } else { // it has no parent - so it is a main thing
        $pathtree[$path['id']] = array('id' => $path['id'], 'children' => array());
        $map[$path['id']] = &$pathtree[$path['id']];
      }
            
      // regardless of what it is - we have to save it properly to the pbpaths
      $pbpaths = $pathbuilder->getPbPaths();
      $pbpaths[$path['id']] = $path; #array('id' => $path['id'], 'weight' => $path['weight'], 'enabled' => $path['enabled'], 'children' => array(), 'bundle' => $path['bundle'], 'field' => $path['field']);
      // save the path
      $pbpaths = $pathbuilder->setPbPaths($pbpaths);

    }
    
    // for now it is equal which create mode is called.
#    if($form_state->getValue('create_mode') == "0") {

      $allgroupsandpaths = $pathbuilder->getAllGroupsAndPaths();

      foreach($allgroupsandpaths as $path) {
        if($path->isGroup()) {
          $pathbuilder->generateBundleForGroup($path->id());
                    
          if(!in_array($path->id(), array_keys($pathbuilder->getMainGroups())))
            $pathbuilder->generateFieldForSubGroup($path->id(), $path->getName());  
        } else
          $pathbuilder->generateFieldForPath($path->id(), $path->getName());
      }
      
#    }

    // save the tree
    $pathbuilder->setPathTree($pathtree);

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
    
 