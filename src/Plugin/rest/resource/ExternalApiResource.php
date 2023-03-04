<?php

namespace Drupal\custom_module\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\custom_module\Services\MyCustomService;

/**
 * Provides a resource to perform external api operations.
 *
 * @RestResource(
 *   id = "external_api_resource",
 *   label = @Translation("External API Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/external-data"
 *   }
 * )
 */
class ExternalApiResource extends ResourceBase {

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
   * Method to get external api data.
   */
  public function get() {
    try {
      $data = $this->myCustomService->getExternalApiData();
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

}
