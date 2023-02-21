<?php

namespace Drupal\custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\custom_module\Services\MyCustomService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * My controller class.
 */
class MyController extends ControllerBase {

  /**
   * My custom service.
   *
   * @var \Drupal\custom_module\Services\MyCustomService
   */
  protected $myCustomService;

  /**
   * My controller constructor.
   *
   * @param \Drupal\custom_module\Services\MyCustomService $my_custom_service
   *   My custom service object.
   */
  public function __construct(MyCustomService $my_custom_service) {
    $this->myCustomService = $my_custom_service;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('custom_module.data_service')
    );
  }

  /**
   * My data method.
   *
   * @return string
   *   Custom service message.
   */
  public function myDataMethod() {
    // Call the doSomething method on the injected service.
    return $this->myCustomService->doSomething();
  }

}
