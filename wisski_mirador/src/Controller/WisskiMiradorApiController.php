<?php

namespace Drupal\wisski_mirador\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;



class WisskiMiradorApiController extends ControllerBase {
  
  /**
   * The context of the mirador instance.
   * 
   * Contains:
   *  - currentEid
   *    the current ID of the entity.
   * 
   *  @var array
   */
  public $context;

  /**
   * The entity manager.
   * 
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The HTTP client.
   * 
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The loggerFactory.
   * 
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The messenger.
   * 
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;
  
  /**
   * The mirador options.
   * 
   * Contains the (field) settings from the view.
   * contains:
   * - enable_annotations
   * - entity_type_for_annotation
   * - bundle_for_annotation
   * - field_for_annotation_id
   * - field_for_annotation_text
   * - field_for_annotation_svg
   * - field_for_annotation_json
   * - field_for_annotation_entity
   * - field_for_annotation_reference
   * - field_for_image_ids
   * - field_for_label
   * - field_for_uri
   * - window_settings
   * 
   * @var array
   */
  public $miradorOptions;

  /**
   * The request object.
   * 
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
    public function __construct(EntityTypeManagerInterface $entity_type_manager, ClientInterface $http_client, LoggerChannelFactoryInterface $loggerFactory, MessengerInterface $messenger, RequestStack $requestStack) {
      $this->entityTypeManager = $entity_type_manager;
      $this->httpClient = $http_client;
      $this->loggerFactory = $loggerFactory;
      $this->messenger = $messenger;
      $this->request = $requestStack->getCurrentRequest();
    }
  
  /**
   * Get the local variables from the session.
   */
  public function getLocalvarsFromSession() {
    $session = $this->request->getSession();
    $this->miradorOptions = $session->get('mirador')['options'];

    // Check if all fields are mapped.
    $optionalFields = ['grouping', 'field_for_annotation_reference', 'field_for_label', 'field_for_uri', 'uses_fields'];
    foreach ($this->miradorOptions as $key => $value) {
    
      if (!in_array($key, $optionalFields) && empty($value)) {
        $this->messenger->addError('Field ' . $key . ' has not mapping. Go to view settings and map the fields.');
      }
    }

    $this->context = $session->get('mirador')['context'];
  }

  /**
   * Creates valid SVG XML from the given string.
   * 
   * @param string $text
   *  The text to convert to SVG.
   * @param int $width
   * The width of the SVG.
   * @param int $height
   * 
   * @return string
   */
  public function createSvg($text, $width, $height) {
    $svg = str_replace('\"', '"', $text);
    
    $pos = strpos($svg, '<svg');
    if ($pos !== false) {
        $closeTagPos = strpos($svg, '>', $pos);
        if ($closeTagPos !== false) {
            $svg = substr_replace($svg, " width=\"$width\" height=\"$height\"", $closeTagPos, 0);
        }
    }
    
    return $svg;
  }

  /** 
  * Called typically called when writing.
  */
  public function common() {
    
    // Retrieve the local vars from the session.
    $this->getLocalvarsFromSession();
    
    // Get the annotation informations.
    // @todo: Do this over a annotation json?
    $cont = $this->request->getContent();
    $cont = json_decode($cont, TRUE); 
    
    // Store need infos in variables.
    $canvas = $cont['annotation']['canvas'];
    $annotation_id = $cont['annotation']['uuid'];
    $annotationJson = $cont['annotation']['data'];

    // Fetch the canvas data.
    try {
      $response = $this->httpClient->request('get', $canvas);
      $canvasData = json_decode($response->getBody()->getContents(), TRUE);
    } catch (\Exception $e) {
      $this->loggerFactory->get('wisski_mirador')->error('Could not fetch the canvas data: ' . $e->getMessage());
      $this->messenger->addError('Could not fetch the canvas data.');
      return new JsonResponse(['error' => 'Could not fetch the canvas data.']);
    }

    $annotationData = json_decode($annotationJson, TRUE);
    try  {
      $annotationText = strip_tags($annotationData['body']['value']);
    } catch (\Exception $e) {
      $this->loggerFactory->get('wisski_mirador')->error('Could not get the annotation text: ' . $e->getMessage());
      $this->messenger->addError('Could get the annotation text. Do you provide a content?');
      return new JsonResponse(['error' => 'Could not strip the tags.']);
    }
    try {
      $annotationSvgText = $this->createSvg($annotationData['target']['selector'][1]['value'], $canvasData['width'], $canvasData['height']);
    } catch (\Exception $e) {
      $this->loggerFactory->get('wisski_mirador')->error('Could not create the SVG: ' . $e->getMessage());
      $this->messenger->addError('Could not create the SVG. Do you forget to add a layer?');
      return new JsonResponse(['error' => 'Could not create the SVG.']);
    }
    /**
     * Check if the annotation already exists.
     */
    $entity_ids = $this->entityTypeManager
      ->getStorage($this->miradorOptions['entity_type_for_annotation'])
      ->getQuery()
      ->condition('bundle', [$this->miradorOptions['bundle_for_annotation'] => $this->miradorOptions['bundle_for_annotation']])
      ->condition($this->miradorOptions['field_for_annotation_id'], $annotation_id)
      ->execute();
    
    // just take the first, there should not be more than this    
    $entity_id = current($entity_ids);
    
    // If the entity does not exist, create it.
    if(empty($entity_id)) {
      
      // build the values and create the entity
      
      // Delete the /normal.json part of the canvas (i .e. http://devel.local/wisski/sequence/normal/300px-Acanthocardia_aculeata_1.jpg/normal.json )
      $canvas = substr($canvas, 0, strpos($canvas, '/normal.json'));
      
      // Fill out the entity properties.
      $values = [
        "bundle" => $this->miradorOptions['bundle_for_annotation'], 
        $this->miradorOptions['field_for_annotation_entity'] => urldecode($canvasData['images'][0]['resource']['filepath']), 
        $this->miradorOptions['field_for_annotation_id'] => $annotation_id, 
        $this->miradorOptions['field_for_annotation_json'] => $annotationJson,
        $this->miradorOptions['field_for_annotation_text'] => $annotationText,
        $this->miradorOptions['field_for_annotation_svg'] => $annotationSvgText,
        ];
      
      // Create the entity.
      try {
        $entity = $this->entityTypeManager
        ->getStorage($this->miradorOptions['entity_type_for_annotation'])
        ->create($values);
      } catch (\Exception $e) {
        $this->loggerFactory->get('wisski_mirador')->error('Could not create the annotation entity: ' . $e->getMessage());
        $this->messenger->addError('Could not create the annotation entity. See logs for more information.');
        return new JsonResponse(['error' => 'Could not create the annotation entity.']);
      }
      
    } else {
      // Load the entity and change the dynamic properties.
      $entity = $this->entityTypeManager
      ->getStorage($this->miradorOptions['entity_type_for_annotation'])
      ->load($entity_id);
      
      $field_json = $this->miradorOptions['field_for_annotation_json'];
      $entity->$field_json->value = $annotationJson;

      $field_text = $this->miradorOptions['field_for_annotation_text'];
      $entity->$field_text->value = $annotationText;
      
    }
    
    // finally do a save
    try {
      $entity->save();
    } catch (\Exception $e) {
      $this->loggerFactory->get('wisski_mirador')->error('Could not save the annotation entity: ' . $e->getMessage());
      $this->messenger->addError('Could not save the annotation entity. See logs for more information.');
      return new JsonResponse(['error' => 'Could not save the annotation entity.']);
    }
    
    return new JsonResponse($annotationJson);
  }
  
  /**
  * Ironically this is the main function - I don't exactly know why. 
  * However it paints all the magical thingies :)
  */
  public function pages() {
    
    // Retrieve the local vars from the session.
    $this->getLocalvarsFromSession();
    
    // Get the uri of the canvas.
    $uri = $this->request->query->get('uri');
    
    // Get the host and the call url
    $host = $this->request->getSchemeAndHttpHost();
    $call_url = $this->request->getRequestUri();
    
    // We have to append the /normal.json to the canvas uri again
    $canvasManifest = $this->miradorOptions['field_for_annotation_entity'] . "/normal.json";
    
    // Fetch the annotations for this file.
    // We assume that it is not used multiple times in several datasets!    
    $annotation_ids = $this->entityTypeManager->getStorage($this->miradorOptions['entity_type_for_annotation'])
      ->getQuery()
      ->condition('bundle', $this->miradorOptions['bundle_for_annotation'])
      ->condition($canvasManifest, $uri)
      ->execute();
    
    $annotations = [];
    
    // More easy mapping for better readable access.    
    $field_json = $this->miradorOptions['field_for_annotation_json'];
    
    // iterate the annotations    
    foreach($annotation_ids as $annotation_id) {
      /** @var $one_annotation Drupal\wisski_core\Entity\WisskiEntity */
      $one_annotation = $this->entityTypeManager
      ->getStorage($this->miradorOptions['entity_type_for_annotation'])
      ->load($annotation_id);
      $one_annotation = $one_annotation->getValues(TRUE);
      
      // I dont know why we have to do that here
      // @TODO handle translation somehow!
      $one_annotation = $one_annotation[0];
      
      // Get the id field of the annotation and the json code of the annotation.
      // This could be more elaborate here, e.g. we could extract the annotations
      // contents to a full semantic model etc.
      $main_property_for_json = $one_annotation[$field_json]["main_property"];
      
      // Fetch the contents.
      $my_json = $one_annotation[$field_json][0][$main_property_for_json];
      
      // And write it accordingly to the array.
      $annotations[] = json_decode($my_json, TRUE);
    }
    
    $data = [];
    
    // Only return something if there is at least one annotation
    // otherwise adding annotations won't work!
    if(!empty($annotations))
    $data = array (
      "@context" => "http://iiif.io/api/presentation/3/context.json",
      "id" => $host . $call_url,
      "type" => "AnnotationPage",
      "items" => $annotations,
    );
    
    return new JsonResponse($data);
  }
  
  /**
   * Called when editing an annotation.
   * 
   * @param string $annotation_id
   *  The annotation ID.
   */
  public function edit_annotation($annotation_id) {
    
    // Retrieve the local vars from the session.
    $this->getLocalvarsFromSession();
    
    // Get the annotation informations.
    $cont = $this->request->getContent();
    
    // Store everything to variables.
    $cont = json_decode($cont, TRUE);
    $annotationJson = $cont['annotation']['data'];
    $annotationData = json_decode($annotationJson, TRUE);
    $canvas = $annotationData['target']['source'];
   
    // Fetch the canvas data.
    try {
      $response = $this->httpClient->request('get', $canvas);
      $canvasData = json_decode($response->getBody()->getContents(), TRUE);
    } catch (\Exception $e) {
      $this->loggerFactory->get('wisski_mirador')->error('Could not fetch the canvas data: ' . $e->getMessage());
      $this->messenger->addError('Could not fetch the canvas data. See logs for more information.');
      return new JsonResponse(['error' => 'Could not fetch the canvas data.']);
    }

    
    $annotationText = strip_tags($annotationData['body']['value']);
    $annotationSvgText = $this->createSvg($annotationData['target']['selector'][1]['value'], $canvasData['width'], $canvasData['height']);
  
    
    // See if we already have this annotation.
    // If not we create it a new.
    // If yes, it is an update!
    $entity_ids = $this->entityTypeManager
    ->getStorage($this->miradorOptions['entity_type_for_annotation'])
    ->getQuery()
    ->condition('bundle', [
      $this->miradorOptions['bundle_for_annotation'] => $this->miradorOptions['bundle_for_annotation']
    ])
    ->condition($this->miradorOptions['field_for_annotation_id'],$annotation_id)
    ->execute();
    
    // Just take the first, there should not be more than this.   
    $entity_id = current($entity_ids);
    
    if(empty($entity_id)) {
      
      // Build the values and create the entity.
      $values = [
        "bundle" => $this->miradorOptions['bundle_for_annotation'],
        $this->miradorOptions['field_for_annotation_entity'] => $canvas,
        $this->miradorOptions['field_for_annotation_id'] => $annotation_id,
        $this->miradorOptions['field_for_annotation_json'] => $annotationJson,
        $this->miradorOptions['field_for_annotation_text'] => $annotationText,
        $this->miradorOptions['field_for_annotation_svg'] => $annotationSvgText,

      ];
      try {
        $entity = $this->entityTypeManager
        ->getStorage($this->miradorOptions['entity_type_for_annotation'])
        ->create($values);
      } catch (\Exception $e) {
        $this->loggerFactory->get('wisski_mirador')->error('Could not create the annotation entity: ' . $e->getMessage());
        $this->messenger->addError('Could not create the annotation entity. See logs for more information.');
        return new JsonResponse(['error' => 'Could not create the annotation entity.']);
      }
      
    } else {
      try {
        // Load the entity and change the json.
        $entity = $this->entityTypeManager
        ->getStorage($this->miradorOptions['entity_type_for_annotation'])
        ->load($entity_id);
        
        $field_json = $this->miradorOptions['field_for_annotation_json'];
        $field_for_annotation_text = $this->miradorOptions['field_for_annotation_text'];
        $field_for_annotation_svg = $this->miradorOptions['field_for_annotation_svg'];
        
        $entity->$field_json->value = $annotationJson;
        $entity->$field_for_annotation_text->value = $annotationText;
        $entity->$field_for_annotation_svg->value = $annotationSvgText;

        
      } catch (\Exception $e) {
        $this->loggerFactory->get('wisski_mirador')->error('Could not load the annotation entity: ' . $e->getMessage());
        $this->messenger->addError('Could not load the annotation entity. See logs for more information.');
        return new JsonResponse(['error' => 'Could not load the annotation entity.']);
      } 
    }
    
    // Finally do a save.
    try {
      $entity->save();
    } catch (\Exception $e) {
      $this->loggerFactory->get('wisski_mirador')->error('Could not save the annotation entity: ' . $e->getMessage());
      $this->messenger->addError('Could not save the annotation entity. See logs for more information.');
      return new JsonResponse(['error' => 'Could not save the annotation entity.']);
    }

    return new JsonResponse($annotationJson);
  }
  
  /**
   * Called when deleting an annotation.
   * 
   * @param string $annotation_id
   */
  public function delete_annotation($annotation_id) {
    
    // Retrieve the local vars from the session
    $this->getLocalvarsFromSession();
    
    // Get the annotation informations.
    $cont = $this->request->getContent();
    $cont = json_decode($cont, TRUE);
    
    // Store everything to variables.
    $data = $cont['annotation']['data'];
    
    // See if we already have this annotation.
    $entity_ids = $this->entityTypeManager->getStorage($this->miradorOptions['entity_type_for_annotation'])
    ->getQuery()
    ->condition('bundle', [
      $this->miradorOptions['bundle_for_annotation'] => $this->miradorOptions['bundle_for_annotation']
      ])
    ->condition($this->miradorOptions['field_for_annotation_id'], $annotation_id)
    ->execute();
    
    // Just take the first, there should not be more than this.  
    $entity_id = current($entity_ids);
    
    try {

      if(!empty($entity_id)) {
        // Load the entity and delete it.
        $entity = $this->entityTypeManager()
        ->getStorage($this->miradorOptions['entity_type_for_annotation'])
        ->load($entity_id);
        
        $entity->delete();
        
      }
    } catch (\Exception $e) {
      $this->loggerFactory->get('wisski_mirador')->error('Could not delete the annotation entity: ' . $e->getMessage());
      $this->messenger->addError('Could not delete the annotation entity. See logs for more information.');
      return new JsonResponse(['error' => 'Could not delete the annotation entity.']);
    }
    
    return new JsonResponse($data);
  }
  
  /**
   * Called when list the annotation.
   */
  public function lists() {
    
    // Retrieve the local vars from the session.
    $this->getLocalvarsFromSession();
    
    
    $response = [];
    
    return new JsonResponse($response);
  }
  
  /**
  * Store data in session.
  * 
  * @param Request $request
  *   The request object. Filled from i.e. wisski_mirador.js.
  * 
  * @return JsonResponse
  *   The JSON response. 
  */
  public function sessionStore(Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    // Assuming you're storing a 'key' and its 'value'
    $key = $data['key'] ?? '';
    $value = $data['value'] ?? '';

    // @todo: Validate the key and value.
    // @todo: Check if there is no security issue.
    
    // Store in session
    $session = $this->request->getSession();
    $session->set($key, $value);
    
    return new JsonResponse(['success' => TRUE, 'message' => 'Data stored in session.']);
  }
}