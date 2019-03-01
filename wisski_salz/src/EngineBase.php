<?php

namespace Drupal\wisski_salz;

use Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity;
use Query\Query;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Base class for external entity storage clients.
 */
abstract class EngineBase extends PluginBase implements EngineInterface {

  protected $is_writable;
  protected $is_preferred_local_store;
  protected $same_as_properties;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->pluginDefinition['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function adapterId() {
    return $this->configuration['adapterId'];
  }

  /**
   *
   */
  public function defaultConfiguration() {
    // Return parent::defaultConfiguration() +.
    return [
      'is_writable' => TRUE,
      'is_preferred_local_store' => FALSE,
      'same_as_properties' => $this->defaultSameAsProperties(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    // This does not exist
    // parent::setConfiguration($configuration);
    if (is_null($configuration)) {
      $configuration = [];
      drupal_set_message(__METHOD__ . ' $configuration === NULL', 'error');
    }
    $this->configuration = $configuration + $this->defaultConfiguration();

    $this->is_writable = $this->configuration['is_writable'];
    $this->is_preferred_local_store = $this->configuration['is_preferred_local_store'];
    $this->same_as_properties = $this->configuration['same_as_properties'];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {

    return [
      'id' => $this->getPluginId(),
      'is_writable' => $this->isWritable(),
      'is_preferred_local_store' => $this->isPreferredLocalStore(),
      'same_as_properties' => $this->getSameAsProperties(),
    ] + $this->configuration;
    // This does not exist
    // + parent::getConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['isWritable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Writable'),
      '#default_value' => $this->isWritable(),
      '#description' => $this->t('Is this Adapter writable?'),
    ];

    // $form['isReadable'] = [
    // '#type' => 'checkbox',
    // '#title' => $this->t('Readable'),
    // '#default_value' => $adapter->getEngine()->isReadable(),
    // '#description' => $this->t('Is this Adapter readable?'),
    // ];.
    $form['isPreferredLocalStore'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preferred Local Store'),
      '#default_value' => $this->isPreferredLocalStore(),
      '#description' => $this->t('Is this Adapter the preferred local store?'),
    ];

    $real_preferred = AdapterHelper::getPreferredLocalStore(FALSE, TRUE);
    if ($real_preferred instanceof AdapterInterface) {
      if ($this->adapterId() !== $real_preferred->id()) {
        $form['isPreferredLocalStore_disclaimer'] = [
          '#type' => 'fieldset',
          '#attributes' => ['class' => ['messages', 'messages--warning']],
          'item' => [
            '#type' => 'item',
            '#markup' => $this->t('The adapter "%adapter" is the preferred local store at the moment. This will be changed if you set this here as the preferred local', ['%adapter' => $real_preferred->link()]),
          ],
        ];
        $this->old_preferred_store = $real_preferred;
        $form_state->setStorage(['old_preferred_store' => $real_preferred]);
      }
    }

    $form['sameAsProperties'] = [
      '#type' => 'textarea',
      '#title' => $this->t('"Same As" properties'),
      '#description' => $this->t('The properties this store uses to mark two URIs as meaning the same (Drupal) entity. ALL of them will be used at the same time when saving a matching pair. Make sure these are symmetric.'),
      '#prefix' => '<div id=wisski-same-as>',
      '#suffix' => '</div>',
      '#default_value' => implode(",\n", $this->getSameAsProperties()),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {

    $is_preferred = $form_state->getValue('isPreferredLocalStore');
    if ($is_preferred) {
      $this->setPreferredLocalStore();
      if (isset($this->old_preferred_store)) {
        $pref = $this->old_preferred_store;
        $pref->getEngine()->unsetPreferredLocalStore();
        $pref->save();
      }
    }
    else {
      $this->unsetPreferredLocalStore();
    }

    $is_writable = $form_state->getValue('isWritable');
    if ($is_writable) {
      $this->setWritable();
    }
    else {
      $this->setReadOnly();
    }

    $same_as_properties = $form_state->getValue('sameAsProperties');
    $this->same_as_properties = preg_split('/[\s,]+/', $same_as_properties);

    // dpm($this,__FUNCTION__);.
    AdapterHelper::resetPreferredLocalStore();

    // Return FALSE;.
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   *
   */
  public function providesCacheMode() {
    return FALSE;
  }

  /**
   *
   */
  public function providesFastMode() {
    return FALSE;
  }

  /**
   *
   */
  public function getQueryObject(EntityTypeInterface $entity_type, $condition, array $namespaces) {
    return new Query($entity_type, $condition, $namespaces);
  }

  /**
   * @TODO overwrite
   */
  public function writeFieldValues($entity_id, array $field_values, $pathbuilder, $bundle = NULL, $original_values = [], $force_creation = FALSE, $initial_write = FALSE) {
    return EngineInterface::NULL_WRITE;
  }

  /**
   *
   */
  public function isWritable() {
    return $this->is_writable;
  }

  /**
   *
   */
  public function isReadOnly() {
    return !$this->is_writable;
  }

  /**
   *
   */
  public function isPreferredLocalStore() {
    return $this->is_preferred_local_store;
  }

  /**
   *
   */
  public function setReadOnly() {
    $this->is_writable = FALSE;
  }

  /**
   *
   */
  public function setWritable() {
    $this->is_writable = TRUE;
  }

  /**
   *
   */
  public function setPreferredLocalStore() {
    $this->is_preferred_local_store = TRUE;
    // dpm($this,$this->adapterId().' '.__FUNCTION__);.
  }

  /**
   *
   */
  public function unsetPreferredLocalStore() {

    $this->is_preferred_local_store = FALSE;
    // dpm($this,$this->adapterId().' '.__FUNCTION__);.
  }

  /**
   * {@inheritdoc}
   */
  public function getSameAsProperties() {
    return $this->same_as_properties;
  }

  /**
   * {@inheritdoc}
   */
  abstract public function defaultSameAsProperties();

  /**
   *
   */
  public function isValidUri($uri) {
    $short_uri = '[a-z]+\:[^\/]+';
    // See RFC3986, simplified.
    $urn_or_similar = '\<' . $short_uri . '\>';
    // This is too complicated because it does not accept something like
    // http://www.bla.de
    // $long_uri = '\<[a-z][a-z0-9\-\.\+]*\:(\/\/)?[^\/]+(\/[^\/]+)*(\/|#)[^\/]+\/?\>';.
    $long_uri = '\<[a-z][a-z0-9\-\.\+]*\:\/\/[^\/]+(\/[^\/]*)*(\/|#)*[^\/]*\/?\>';
    return preg_match("/^($short_uri|$urn_or_similar|$long_uri)$/", $uri);
  }

  /**
   * {@inheritdoc}
   */
  abstract public function checkUriExists($uri);

  /**
   * Gets the PB object for a given adapter id.
   *
   * @return a pb object
   */
  public function getPbForThis() {
    $pbs = WisskiPathbuilderEntity::loadMultiple();

    foreach ($pbs as $pb) {
      // If there is no adapter set for this pb.
      if ($adapter_id = $pb->getAdapterId()) {
        if ($this->adapterId() == $adapter_id) {
          return $pb;
        }
      }
    }
    return NULL;
  }

  /**
   *
   */
  public function getPbsForThis() {
    $pbs = WisskiPathbuilderEntity::loadMultiple();
    $pb_array = [];

    foreach ($pbs as $pb) {
      // If there is no adapter set for this pb.
      if ($adapter_id = $pb->getAdapterId()) {
        if ($this->adapterId() == $adapter_id) {
          $pb_array[] = $pb;
        }
      }
    }
    return $pb_array;
  }

  /**
   *
   */
  abstract public function getDrupalIdForUri($uri, $adapter_id = NULL);

  /**
   *
   */
  public function setDrupalId($uri, $eid) {

    $this->setSameUris([$this->adapterId() => $uri], $eid);
  }

  /**
   *
   */
  public function getUriForDrupalId($id, $create = TRUE) {

    return AdapterHelper::getUrisForDrupalId($id, $this->adapterId(), $create);
  }

  /**
   * Here we have to avoid a name clash. getUriForDrupalId was already there and is heavily used.
   * Thus the somewhat strange name for this function here
   * essentailly does the same like getUriForDrupalId but initiates an internal query in the preferred local store.
   */
  public function findUriForDrupalId($id, $adapter_id = NULL) {

    if (!isset($adapter_id)) {
      $adapter_id = $this->adapterId();
    }
    $uris = $this->getUrisForDrupalId($id);
    if (empty($uris) || !isset($uris[$adapter_id])) {
      // Throw new \Exception('No uri for '.$id.' in '.$adapter_id);.
      return NULL;
    }
    return $uris[$adapter_id];
  }

  /**
   *
   */
  abstract public function getUrisForDrupalId($id);

  /**
   * {@inheritdoc}
   */
  abstract public function getSameUris($uri);

  /**
   * {@inheritdoc}
   */
  abstract public function getSameUri($uri, $adapter_id);

  /**
   * {@inheritdoc}
   */
  abstract public function setSameUris($uris, $entity_id);

  /**
   * {@inheritdoc}
   */
  abstract public function generateFreshIndividualUri();

}
