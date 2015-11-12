<?php
/**
 * @file
 * Contains \Drupal\hello_world\Controller\HelloController.
 */
   
 namespace Drupal\wisski_salz\Controller;
   
 use Drupal\Core\Controller\ControllerBase;
   
 class wisski_salzController extends ControllerBase {
   public function content() {
     return array(
         '#type' => 'markup',
         '#markup' => $this->t('Hello, World!'),
     );
   }
 }

?>