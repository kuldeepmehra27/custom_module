<?php

namespace Drupal\custom_module\Event;

use Symfony\Component\EventDispatcher\Event;
use Drupal\node\NodeInterface;

/**
 * Event class for content save event.
 */
class ContentOperationEvent extends Event {

  /**
   * Name of the event when content is updated while already published.
   *
   * @var string
   */
  const CONTENT_UPDATED = 'node_updated';

  /**
   * The node object.
   *
   * @var \Drupal\node\NodeInterface
   */
  private $node;

  /**
   * Constructor for the Event Class.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node Entity.
   *
   * @codeCoverageIgnore
   */
  public function __construct(NodeInterface $node) {
    $this->node = $node;
  }

  /**
   * Get the inserted node.
   *
   * @return \Drupal\node\NodeInterface
   *   Node Entity.
   *
   * @codeCoverageIgnore
   */
  public function getNode() {
    return $this->node;
  }

}
