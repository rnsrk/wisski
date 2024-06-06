<?php

namespace Drupal\wisski_core\Form;

use Drupal;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;



class WisskiBundlesDeleteForm extends ConfirmFormBase {
    
    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'wisski_bundles_delete_form';
    }

    /**
     * {@inheritdoc}
     */
    public function getQuestion() {
        return $this->t('Are you sure you want to delete the Wisski Bundles?');
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
    public function getDescription()
    {
        return $this->t('This deletes all WissKI Bundles with there configurations, the WissKI Entities will remain, but are not accessible without a bundle. Recreate the bundles via the pathbuilder immediately after deleting the bundles.');
    }

    /**
     * {@inheritdoc}
     */
    public function getCancelUrl() {
        return new Url('entity.wisski_bundle.list');
    }
    
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        /**
         * Load and delete all Wisski Bundles. Write notice to log and messenger.
         */
        
        /** @var EntityTypeManager $entity_type_manager */
        $entity_type_manager = \Drupal::service('entity_type.manager');
        
        $bundles = $entity_type_manager->getStorage('wisski_bundle')->loadMultiple();
        
        /** @var Drupal\wisski_core\Entity\WisskiBundle $bundle */
        foreach ($bundles as $bundle) {
            $bundle->delete();
        }

        $redirect_url = Url::fromRoute('entity.wisski_bundle.list');
        $form_state->setRedirectUrl($redirect_url);
        Drupal::messenger()->addMessage('All Wisski Bundles deleted.');
        Drupal::logger('wisski_core')->notice('All Wisski Bundles deleted.');
    }
    
}