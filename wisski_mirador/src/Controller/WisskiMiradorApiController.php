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
use DOMDocument;



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
   * - field_for_annotation_uuid
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
    $optionalFields = ['grouping', 'field_for_label', 'field_for_image_ids', 'field_for_uri', 'uses_fields'];
    foreach ($this->miradorOptions as $key => $value) {
    
      if (!in_array($key, $optionalFields) && empty($value)) {
        $this->messenger->addError('Field ' . $key . ' has not mapping. Go to view settings and map the fields.');
      }
    }

    $this->context = $session->get('mirador')['context'];
  }

  /** 
  * Called typically called when writing.
  *
  * @param string $annotationUuid
  *  The annotation ID.
  *
  * @return JsonResponse
  */
  public function common($annotationUuid = NULL) {
    
    // Retrieve the local vars from the session.
    $this->getLocalvarsFromSession();
    
    // Get the annotation informations.
    $cont = $this->request->getContent();
    $cont = json_decode($cont, TRUE); 
    
    // Store need infos in variables.
    $annotationUuid = $cont['annotation']['uuid'];
    $annotationJson = $cont['annotation']['data'];
    $annotationData = json_decode($annotationJson, TRUE);
    $annotationSource = $annotationUuid ? $annotationData['target']['source'] : $cont['annotation']['canvas'];

    // Fetch the canvas data.
    try {
      $response = $this->httpClient->request('get', $annotationSource);
      $canvasData = json_decode($response->getBody()->getContents(), TRUE);
    } catch (\Exception $e) {
      $this->loggerFactory->get('wisski_mirador')->error('Could not fetch the canvas data: ' . $e->getMessage());
      $this->messenger->addError('Could not fetch the canvas data. See logs for more information.');
      return new JsonResponse(['error' => 'Could not fetch the canvas data.']);
    }

    
    // Fetch the annotation text.
    try  {
      $annotationText = strip_tags($annotationData['body']['value']);
    } catch (\Exception $e) {
      $this->loggerFactory->get('wisski_mirador')->error('Could not get the annotation text: ' . $e->getMessage());
      $this->messenger->addError('Could get the annotation text. Do you provide a content?');
      return new JsonResponse(['error' => 'Could not strip the tags.']);
    }

    // Fetch the SVG text.
    try {
      $annotationSvgText = $annotationData['target']['selector'][1]['value'];
    } catch (\Exception $e) {
      $this->loggerFactory->get('wisski_mirador')->error('Could not create the SVG: ' . $e->getMessage());
      $this->messenger->addError('Could not create the SVG. Do you forget to add a layer?');
      return new JsonResponse(['error' => 'Could not create the SVG.']);
    }

    // Fetch the fragment selector.
    try {
      $fragmentSelector = $annotationData['target']['selector'][0]['value'];
    } catch (\Exception $e) {
      $this->loggerFactory->get('wisski_mirador')->error('Could not get the fragment selector: ' . $e->getMessage());
      $this->messenger->addError('Could not get the fragment selector. Do you forget to add a layer?');
      return new JsonResponse(['error' => 'Could not get the fragment selector.']);
    }

    /**
     * Check if the annotation already exists.
     */
    $entity_ids = $this->entityTypeManager
      ->getStorage($this->miradorOptions['entity_type_for_annotation'])
      ->getQuery()
      ->condition('bundle', [$this->miradorOptions['bundle_for_annotation'] => $this->miradorOptions['bundle_for_annotation']])
      ->condition($this->miradorOptions['field_for_annotation_uuid'], $annotationUuid)
      ->execute();
    
    // Just take the first, there should not be more than this.
    $entity_id = current($entity_ids);

    // Map fields to variables.
    $fieldAnnotationEntity = $this->miradorOptions['field_for_annotation_entity'];
    $fieldSourceReference = $this->miradorOptions['field_for_annotation_source_reference'];
    $fielUuid = $this->miradorOptions['field_for_annotation_uuid'];
    $fieldJson = $this->miradorOptions['field_for_annotation_json'];
    $fieldSvg = $this->miradorOptions['field_for_annotation_svg'];
    $fieldText = $this->miradorOptions['field_for_annotation_text'];
    $fieldFragmentSelector = $this->miradorOptions['field_for_annotation_fragment_selector'];
    
    // If the entity does not exist, create it.
    if(empty($entity_id)) {
      
      // build the values and create the entity
      
      // Fill out the entity properties.
      $values = [
        "bundle" => $this->miradorOptions['bundle_for_annotation'], 
        $fieldAnnotationEntity => urldecode($canvasData['images'][0]['resource']['filepath']), 
        $fieldSourceReference => $annotationSource, 
        $fielUuid => $annotationUuid, 
        $fieldJson => $annotationJson,
        $fieldText => $annotationText,
        $fieldSvg => $annotationSvgText,
        $fieldFragmentSelector => $fragmentSelector,
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
      
      $entity->$fieldSourceReference->value = $annotationSource;
      $entity->$fieldJson->value = $annotationJson;
      $entity->$fieldSvg->value = $annotationSvgText;
      $entity->$fieldText->value = $annotationText;
      $entity->$fieldFragmentSelector->value = $fragmentSelector;
      
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
  * 
  * @return JsonResponse
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
    $annotationUuids = $this->entityTypeManager->getStorage($this->miradorOptions['entity_type_for_annotation'])
      ->getQuery()
      ->condition('bundle', $this->miradorOptions['bundle_for_annotation'])
      ->condition($canvasManifest, $uri)
      ->execute();
    
    $annotations = [];
  
    $annotationTemplate = [
      "body" => [
        "type" => "TextualBody",
        "value" => "",
      ],
      "id" => "",
      "motivation" => "commenting",
      "target" => [
        "source" => "",
        "selector" => [
          [
            "type" => "FragmentSelector",
            "value" => "",
          ],
          [
            "type" => "SvgSelector",
            "value" => "",
          ],
        ],
      ],
      "type" => "Annotation",
    ];

    // Loop through the annotations and build the array.
    foreach($annotationUuids as $count => $annotationUuid) {
      /**
       * Load the annotation entity.
       *  @var $one_annotation Drupal\wisski_core\Entity\WisskiEntity */
      $singleAnnotation = $this->entityTypeManager
      ->getStorage($this->miradorOptions['entity_type_for_annotation'])
      ->load($annotationUuid);

      // Fill the annotation array.
      $annotationUuid = $singleAnnotation->get($this->miradorOptions['field_for_annotation_uuid'])->value;
      $annotationText = '<p>' . $singleAnnotation->get($this->miradorOptions['field_for_annotation_text'])->value . '</p>';
      $annotationSvgText = $singleAnnotation->get($this->miradorOptions['field_for_annotation_svg'])->value;
      $sourceReference = $singleAnnotation->get($this->miradorOptions['field_for_annotation_source_reference'])->value;
      $fragmentSelector = $singleAnnotation->get($this->miradorOptions['field_for_annotation_fragment_selector'])->value;

      $annotations[$count] = $annotationTemplate;
      $annotations[$count]['body']['value'] = $annotationText;
      $annotations[$count]['id'] = $annotationUuid;
      $annotations[$count]['target']['source'] = $sourceReference;
      $annotations[$count]['target']['selector'][0]['value'] = $fragmentSelector;
      $annotations[$count]['target']['selector'][1]['value'] = $annotationSvgText;
      
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
   * @param string $annotationUuid
   *  The annotation ID.
   * 
   * @return JsonResponse
   */
  public function edit_annotation($annotationUuid) {
    return $this->common($annotationUuid);
  }
  
  /**
   * Called when deleting an annotation.
   * 
   * @param string $annotationUuid
   */
  public function delete_annotation($annotationUuid) {
    
    // Retrieve the local vars from the session
    $this->getLocalvarsFromSession();
    
    // See if we already have this annotation.
    $entity_ids = $this->entityTypeManager->getStorage($this->miradorOptions['entity_type_for_annotation'])
    ->getQuery()
    ->condition('bundle', [
      $this->miradorOptions['bundle_for_annotation'] => $this->miradorOptions['bundle_for_annotation']
      ])
    ->condition($this->miradorOptions['field_for_annotation_uuid'], $annotationUuid)
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
    
    return new JsonResponse(['success' => TRUE, 'message' => 'Annotation deleted.']);
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