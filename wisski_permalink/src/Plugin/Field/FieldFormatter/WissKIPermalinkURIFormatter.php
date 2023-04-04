<?php

namespace Drupal\wisski_permalink\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\Url;
use Drupal\Core\Link;

use Drupal\wisski_salz\AdapterHelper;

/**
 * Plugin implementation of the 'string' formatter.
 *
 * @FieldFormatter(
 *   id = "wisski_uri",
 *   label = @Translation("WissKI Permalink"),
 *   field_types = {
 *     "string",
 *     "uri",
 *   },
 *   quickedit = {
 *     "editor" = "plain_text"
 *   }
 * )
 */
class WissKIPermalinkURIFormatter extends FormatterBase implements ContainerFactoryPluginInterface
{

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a StringFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
      $plugin_id,
      $plugin_definition,
      FieldDefinitionInterface $field_definition,
      array $settings,
      $label,
      $view_mode,
      array $third_party_settings,
      EntityTypeManagerInterface $entity_type_manager,
      ) {
    parent::__construct(
        $plugin_id,
        $plugin_definition,
        $field_definition,
        $settings,
        $label,
        $view_mode,
        $third_party_settings
        );
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
      ContainerInterface $container,
      array $configuration,
      $plugin_id,
      $plugin_definition
      ) {
    // set the label for the URI field
    $configuration['field_definition']->setLabel("Permalink");
    $configuration['field_definition']->setLabel("");
    return new static(
        $plugin_id,
        $plugin_definition,
        $configuration['field_definition'],
        $configuration['settings'],
        $configuration['label'],
        $configuration['view_mode'],
        $configuration['third_party_settings'],
        $container->get('entity_type.manager')
        );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings()
  {
    $options = parent::defaultSettings();
    $options['permalink'] = true;
    $options['httpreplace'] = false;
    $options['resolverless'] = false;
    $options['display'] = "hide_sub";
    $options['resolver'] = "";
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state)
  {
    $form = parent::settingsForm($form, $form_state);
    $entity_type = $this->entityTypeManager->getDefinition($this->fieldDefinition->getTargetEntityTypeId());
    
    $form['permalink'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display permalink in URL bar'),
      '#default_value' => $this->getSetting('permalink')
    ];

    // This setting replaces http to https on demand. This is useful if 
    // you have http links in your database but nowadays the system is on
    // https and you have permanent rewriting to https. Usually you want to
    // maintain the http link as a permalink.
    // e.g. the object catalogue of the gnm has http links as
    // permalinks that resolve to https websites.
    $form['httpreplace'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Replace http with https in permalink (old compatibility mode)'),
      '#default_value' => $this->getSetting('httpreplace')
    ];

    // This setting is used if you have a correctly set rewriting to the resolver
    // via a fixed url. E.g. the object catalogue of the gnm rewrites all requests
    // to /object/ to the resolver. In this case I don't need another rewriting from
    // this module.
    $form['resolverless'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('The Permalink works resolverless'),
      '#default_value' => $this->getSetting('resolverless')
    ];


    $form['display'] = [
      '#type' => 'radios',
      '#title' => "Display options",
      '#options' => array(
        "display" => t("Always display"),
        "hide_sub" => t("Hide in sub-entity"),
        "hide" => t("Always hide")
        ),
      '#default_value' => $this->getSetting("display"),
    ];

    $form['resolver'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URI Resolver (Hostname or URL prefix)'),
      '#default_value' => $this->getSetting('resolver')
    ];


    return $form;                  
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary()
  {
    $summary = [];
    if($this->getSetting('permalink')){
      $summary[] = $this->t('Diplay permalink in URL bar');
    }
    
    if($this->getSetting('httpreplace')){
      $summary[] = $this->t('Replace http with https in permalink');
    }

    if($this->getSetting('resolverless')){
      $summary[] = $this->t('The Permalink works resolverless');
    }

    if($this->getSetting('display') == "hide"){
      $summary[] = $this->t('Hidden');
    }

    if($this->getSetting('display') == "hide_sub"){
      $summary[] = $this->t('Hidden in sub-entites');
    }
    if($this->getSetting('resolver')){
      $resolver = $this->getSetting('resolver');
      $summary[] = $this->t('Forward to @resolver', [
          '@resolver' => $resolver,
      ]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode)
  {
    $elements = [];

    // set field label
    $items->getFieldDefinition()->setLabel("Permalink");

    // get the entity id from the route
    // TODO: see if this works or if it is necessary to dig 
    // for the eid in the query parameters
    $ent = \Drupal::routeMatch()->getParameter('wisski_individual');
    $routeEid = null;
    if($ent){
      $routeEid = $ent->id();
    }

    foreach ($items as $delta => $item) {
      $currentEid = AdapterHelper::getDrupalIdForUri($item->value);

      $url = $this->buildUrl($item->value);
      $link = Link::fromTextAndUrl($url->toString(), $url);
      $elements[$delta] = $link->toRenderable();

      // hide field
      if($this->getSetting('display') == "hide" || ($this->getSetting('display') == "hide_sub" && $routeEid != $currentEid)){
        $elements = [];
        $items->getFieldDefinition()->setLabel("");
      }

      // current entity is requested entity
      if($this->getSetting('permalink') && $routeEid == $currentEid){
        // attach JS Library to write URL to browser URL bar
        // only attach if this is actually the requested entity
        $elements['#attached']['library'][] = 'wisski_permalink/permalink';
        $elements['#attached']['drupalSettings']['wisski_permalink']['permalink']['url'] = $url->toString();
      }
    }
    return $elements;
  }

  /**
   * Gets the URL for a URI.
   *
   * @param string $uri
   *   The URI.
   *
   * @return \Drupal\Core\Url
   *   The URL element for the entity.
   */
  protected function buildUrl(string $uri)
  {
    $prefix = $this->getSetting('resolver');
    
    // if we want to replace http to https - do so!
    if($this->getSetting('httpreplace')) {
      $uri = str_replace("http://", "https://", $uri);
    }
    
    // if the permalink redirects to the resolver already
    // don't do another redirect below.
    if($this->getSetting('resolverless')) {
      return Url::fromUri($uri);
    }
    
    // default to local resolver
    if (!$prefix) {
      return Url::fromRoute('entity.wisski_individual.lod_get', ['uri' => $uri]);
    }

    // handle hostnames
    if (!str_starts_with($prefix, 'http:') && !str_starts_with($prefix, 'https:')) {
      $prefix = "https://" . $prefix . "/wisski/get?uri=";
    }
    return Url::fromUri($prefix . $uri);
  }
}
