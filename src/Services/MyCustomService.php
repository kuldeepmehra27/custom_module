<?php

namespace Drupal\custom_module\Services;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Component\Serialization\Json;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Drupal\node\NodeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * My custom service class.
 */
class MyCustomService {

  use StringTranslationTrait;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * GuzzleHttp\Client definition.
   *
   * @var GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The cache backend service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * Constructs a my custom service object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Database instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The http client service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend service.
   *
   * @codeCoverageIgnore
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager, ClientInterface $http_client, CacheBackendInterface $cache_backend) {
    $this->database = $database;
    $this->httpClient = $http_client;
    $this->entityTypeManager = $entity_type_manager;
    $this->cacheBackend = $cache_backend;
  }

  /**
   * Do something method.
   *
   * @return array
   *   Custom service message.
   */
  public function doSomething() {
    $build = [
      '#markup' => $this->t('This is from custom service'),
    ];
    return $build;
  }

  /**
   * Entity create method.
   *
   * @return array
   *   Custom service message.
   */
  public function post($data) {
    $response = [];
    $postData = Json::decode($data->getContent(), TRUE);

    /* // @codeCoverageIgnoreStart */
    if (!is_array($postData) || (empty($postData))) {
      return $response;
    }
    /* // @codeCoverageIgnoreEnd */
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $node = $nodeStorage->create(['type' => 'article']);
    foreach ($postData['data'] as $key => $value) {
      $value = (!empty($value['content']) && isset($value['content'])) ? $value['content'] : '';
      $node->set($key, $value);
    }
    $node->enforceIsNew();
    $node->save();
    $message = $this->t('@title has been created.', ['@title' => $node->getTitle()]);
    $response = [
      'id' => $node->id(),
      'message' => $message,
    ];
    return $response;
  }

  /**
   * Entity update method.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node.
   * @param array $data
   *   The data array.
   *
   * @return array
   *   Node response.
   */
  public function update($node, $data) {
    $response = [];
    if (!$node instanceof NodeInterface) {
      throw new InvalidArgumentException('Invalid node argument.');
    }
    if ($node->getType() != 'article') {
      throw new InvalidArgumentException('Only article content type is supported.');
    }
    foreach ($data['data'] as $key => $value) {
      $value = (!empty($value['content']) && isset($value['content'])) ? $value['content'] : '';
      $node->set($key, $value);
    }
    $node->save();
    if (!isset($message)) {
      $message = $this->t('@title has been updated.', ['@title' => $node->getTitle()]);
    }
    $response = [
      'id' => $node->id(),
      'message' => $message,
    ];
    return $response;
  }

  /**
   * Entity get node list.
   *
   * @return array
   *   Node list.
   */
  public function getNodeList() {
    $tags = $nodeData = $results = [];
    $cacheId = 'my_custom_tag';
    if (!empty($data = $this->cacheBackend->get($cacheId))) {
      $response = $data->data;
      return $response;
    }
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $entityQuery = $nodeStorage->getQuery();
    // Get published contents only.
    $entityQuery->condition('status', 1);
    // Sort data based on created.
    $entityQuery->sort('created', 'DESC');
    $nodeIds = $entityQuery->execute();
    // Return if no result found.
    if (empty($nodeIds)) {
      return $results;
    }
    $nodes = $nodeStorage->loadMultiple($nodeIds);
    foreach ($nodes as $node) {
      $nodeData = [
        'nid' => $node->id(),
        'title' => $node->getTitle(),
      ];
      $results[] = $nodeData;
    }
    // Set caching.
    $tags[] = 'node_list';
    $this->cacheBackend->set(
      $cacheId,
      $results,
      CacheBackendInterface::CACHE_PERMANENT,
      $tags
    );
    return $results;
  }

  /**
   * Entity get specific node data.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node.
   *
   * @return array
   *   Node response.
   */
  public function getNodeData($node) {
    $nodeData = [];
    $error = FALSE;
    if (!$node instanceof NodeInterface) {
      $message = $this->t('Article id is invalid.');
      $error = TRUE;
    }
    elseif ($node->getType() != 'article') {
      $message = $this->t('Please provide id of article only.');
      $error = TRUE;
    }
    if ($error) {
      return $message;
    }
    $cacheId = 'custom_module_node_data';
    if (!empty($data = $this->cacheBackend->get($cacheId))) {
      $results = $data->data;
      return $results;
    }
    $nodeData = [
      'nid' => $node->id(),
      'title' => $node->getTitle(),
      'body' => $node->get('body')->value,
    ];
    // Set caching.
    $tags[] = 'node_data';
    $this->cacheBackend->set(
      $cacheId,
      $nodeData,
      CacheBackendInterface::CACHE_PERMANENT,
      //$node->getCacheTags()
      $tags
    );
    return $nodeData;
  }

  /**
   * Entity delete method.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node.
   *
   * @return array
   *   Node response.
   */
  public function delete($node) {
    $response = [];
    if (!$node instanceof NodeInterface) {
      throw new InvalidArgumentException('Invalid node argument.');
    }
    if ($node->getType() != 'article') {
      throw new InvalidArgumentException('Only article content type is supported.');
    }
    $id = $node->id();
    $title = $node->getTitle();
    $node->delete();
    $message = $this->t('@title has been deleted.', ['@title' => $title]);
    $response = [
      'id' => $id,
      'message' => $message,
    ];
    return $response;
  }

  /**
   * Get all Nodes.
   *
   * @return array
   *   User response.
   */
  public function getAllNodes() {
    $results = [];
    $query = $this->database->select('node_field_data', 'nfd');
    $query->leftjoin('node_field_revision', 'nfr', 'nfd.vid = nfr.vid');
    $query->addField('nfd', 'vid', 'vid');
    $query->addField('nfd', 'title', 'title');
    $query->condition('nfd.status', 1);
    $query->range(0, 15);
    $data = $query->execute()->fetchAll();
    // Count total records.
    $query->range();
    $count = $query->countQuery()->execute()->fetchField();
    if ($count == 0) {
      $results['data'] = [];
      return $results;
    }
    // Get result array.
    foreach ($data as $value) {
      $result = [
        'nid' => $value->nid,
        'vid' => $value->vid,
        'title' => $value->title,
      ];
      $results['data'][] = $result;
    }
    // Get result count.
    $results['count'] = $count;
    return $results;
  }

}
