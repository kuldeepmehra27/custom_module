<?php

namespace Drupal\custom_module\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\custom_module\Event\ContentOperationEvent;
use Drupal\custom_module\Services\MyCustomService;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Subscriber for ContentOperationEvent.
 */
class ContentOperationSubscriber implements EventSubscriberInterface {

  /**
   * My custom service.
   *
   * @var \Drupal\custom_module\Services\MyCustomService
   */
  protected $myCustomService;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  public $messenger;

  /**
   * ContentOperationSubscriber constructor.
   *
   * @param \Drupal\custom_module\Services\MyCustomService $my_custom_service
   *   My custom service object.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service object.
   *
   * @codeCoverageIgnore
   */
  public function __construct(MyCustomService $my_custom_service, MessengerInterface $messenger) {
    $this->myCustomService = $my_custom_service;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The event name to listen for and methods that should be executed.
   *
   * @codeCoverageIgnore
   */
  public static function getSubscribedEvents() {
    if (class_exists(ContentOperationEvent::class)) {
      return [
        ContentOperationEvent::CONTENT_UPDATED => 'contentPublished',
      ];
    }
  }

  /**
   * Executes on update event.
   *
   * @param \Drupal\custom_module\Event\ContentOperationEvent $event
   *   Content save event object.
   *
   * @codeCoverageIgnore
   */
  public function contentPublished(ContentOperationEvent $event) {
    $node = $event->getNode();
    $title = $node->getTitle();
    $this->messenger->addStatus(__CLASS__ . ' Title:- ' . $title);
  }

}
