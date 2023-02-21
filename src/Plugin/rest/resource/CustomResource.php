<?php

namespace Drupal\custom_module\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Drupal\node\NodeInterface;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\custom_module\Services\MyCustomService;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a resource to Perform CRUD operations on node.
 *
 * @RestResource(
 *   id = "custom_resource",
 *   label = @Translation("Custom Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/operations/{article}",
 *     "create" = "/api/create-content"
 *   }
 * )
 */
class CustomResource extends ResourceBase {

  /**
   * A request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * My custom service.
   *
   * @var \Drupal\custom_module\Services\MyCustomService
   */
  protected $myCustomService;

  /**
   * Constructs a new custom rest resource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Instance of request to fetch request details.
   * @param \Drupal\custom_module\Services\MyCustomService $my_custom_service
   *   My custom service object.
   *
   * @codeCoverageIgnore
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    Request $request,
    MyCustomService $my_custom_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->request = $request;
    $this->myCustomService = $my_custom_service;
  }

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('custom_module'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('custom_module.data_service')
    );
  }

  /**
   * Method to post/create node.
   */
  public function post() {
    $request = $this->request;
    // Only POST method will be entertained here.
    try {
      $data = $this->myCustomService->post($request);
      // 200 Created responses return the newly created entity in the response
      return new ModifiedResourceResponse($data, 200);
    }
    catch (InvalidArgumentException $e) {
      return new ResourceResponse(['error' => $e->getMessage()], 400);
    }
    catch (EntityStorageException $e) {
      return new ResourceResponse(['error' => $e->getMessage()], 500);
    }
    catch (\Throwable $e) {
      return new ResourceResponse(['error' => $e->getMessage()], 500);
    }
  }

  /**
   * Method to get node data.
   */
  public function get(NodeInterface $article) {
    try {
      $data = $this->myCustomService->getNodeData($article);
      // 200  responses return the  entity in the response
      // body. We are not adding Caching, since these are for Studio, and
      // we want to render latest data always for authoring.
      return new ModifiedResourceResponse($data, 200);
    }
    catch (AccessDeniedHttpException $e) {
      return new ResourceResponse(['error' => $e->getMessage()], 403);
    }
    catch (\Throwable $e) {
      return new ResourceResponse(['error' => $e->getMessage()], 500);
    }
  }

  /**
   * Method to put/update node data.
   */
  public function put(NodeInterface $article) {
    try {
      $response = $this->myCustomService->update($article, Json::decode($this->request->getContent(), TRUE));
      return new ModifiedResourceResponse($response, 200);
    }
    catch (AccessDeniedHttpException $e) {
      return new ResourceResponse(['error' => $e->getMessage()], 403);
    }
    catch (InvalidArgumentException $e) {
      return new ModifiedResourceResponse(['error' => $e->getMessage()], 400);
    }
    catch (\Throwable $e) {
      return new ModifiedResourceResponse(['error' => $e->getMessage()], 500);
    }
  }

  /**
   * Method to delete node.
   */
  public function delete(NodeInterface $article) {
    try {
      $response = $this->myCustomService->delete($article);
      return new JsonResponse($response, 200);
    }
    catch (InvalidArgumentException $e) {
      return new ModifiedResourceResponse(['error' => $e->getMessage()], 400);
    }
    catch (\Throwable $e) {
      return new ModifiedResourceResponse(['error' => $e->getMessage()], 500);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  protected function getBaseRoute($canonical_path, $method) {
    $route = parent::getBaseRoute($canonical_path, $method);
    $route->setOption('parameters', [
      'article' => [
        'type' => 'entity:node',
      ],
    ]);

    return $route;
  }

}
