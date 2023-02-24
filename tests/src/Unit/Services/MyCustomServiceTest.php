<?php

namespace Drupal\Tests\custom_module\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Component\Serialization\Json;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Prophecy\Argument;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Drupal\custom_module\Services\MyCustomService;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationManager;

/**
 * Tests the my custom service.
 *
 * @coversDefaultClass \Drupal\custom_module\Services\MyCustomService
 */
class MyCustomServiceTest extends UnitTestCase {

  use StringTranslationTrait;

  protected $database;
  protected $entityTypeManager;
  protected $httpClient;
  protected $cacheBackend;

  /**
   * Function to setup basic requirements for test to run.
   *
   * Called before each test method and is used to set up the test environment.
   */
  public function setUp(): void {
    $this->database = $this->prophesize(Connection::class);
    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->cacheBackend = $this->prophesize(CacheBackendInterface::class);

    $data = [
      'My data',
    ];
    $this->mockHandler = new MockHandler([
      new Response(200, [], Json::encode($data)),
    ]);
    $handlerStack = HandlerStack::create($this->mockHandler);
    $this->httpClient = new Client(['handler' => $handlerStack]);

    $this->request = $this->prophesize(Request::class);

    $this->myServiceObject = new MyCustomService(
      $this->database->reveal(),
      $this->entityTypeManager->reveal(),
      $this->httpClient,
      $this->cacheBackend->reveal()
    );

    $this->translationManager = $this->prophesize(TranslationManager::class);

    // Building and managing dependency injection.
    $container = new ContainerBuilder();
    // Set dependent services date service depends on language manager.
    $container->set('string_translation', $this->translationManager->reveal());
    \Drupal::setContainer($container);
  }

  /**
   * Function to test do something method.
   *
   * @covers ::doSomething
   */
  public function testDoSomething() {
    $result = $this->myServiceObject->doSomething();
    $this->assertNotEmpty($result);
  }

  /**
   * Function to test post method.
   *
   * @covers ::post
   */
  public function testPost() {
    $data = '{
        "data": {
            "title": {
                "content": "New content"
            },
            "body": {
                "content": "New content body"
            }
        }
    }';
    $this->request->getContent()->willReturn($data);
    $nodeEntity = $this->prophesize(Node::class);
    $nodeEntity->enforceIsNew()->willReturn(TRUE);
    $nodeEntity->save()->willReturn(TRUE);
    $nodeEntity->id()->willReturn(rand());
    $nodeEntity->getTitle()->willReturn('title');
    $nodeEntity->getType()->willReturn('article');
    $nodeEntity->set(Argument::any(), Argument::any())->willReturn(TRUE);

    $entityStorage = $this->prophesize(EntityStorageInterface::class);
    $entityStorage->create(Argument::any())->willReturn($nodeEntity->reveal());
    $this->entityTypeManager->getStorage('node')->willReturn($entityStorage->reveal());

    // Not empty case.
    $result = $this->myServiceObject->post($this->request->reveal());
    $this->assertArrayHasKey('id', $result);

    // Empty case.
    $data = '';
    $this->request->getContent()->willReturn($data);
    $result = $this->myServiceObject->post($this->request->reveal());
    $this->assertEmpty($result);

  }

  /**
   * Function to test update method.
   *
   * @covers ::update
   */
  public function testUpdate() {
    $data = [
      "data" => [
        "title" => [
          "content" => "Update content",
        ],
        "body" => [
          "content" => "New content body",
        ],
      ],
    ];
    $nodeInterface = $this->prophesize(NodeInterface::class);
    $nodeInterface->id()->willReturn(123);
    $nodeInterface->save()->willReturn(TRUE);
    $nodeInterface->getTitle()->willReturn('title');
    $nodeInterface->getType()->willReturn('article');
    $nodeInterface->set(Argument::any(), Argument::any())->willReturn(TRUE);

    // Not empty case.
    $result = $this->myServiceObject->update($nodeInterface->reveal(), $data);
    $this->assertNotEmpty($result);

    // Not empty case.
    $result = $this->myServiceObject->update($nodeInterface->reveal(), $data);
    $this->assertNotEmpty($result);
  }

  /**
   * @covers ::update
   */
  public function testUpdateNodeException() {
    $data = [];
    $userInterface = $this->prophesize(UserInterface::class);
    $error = FALSE;
    try {
      $this->myServiceObject->update($userInterface->reveal(), $data);
    }
    catch (\InvalidArgumentException $e) {
      $error = TRUE;
    }
    $this->assertTrue($error);

    $nodeInterface = $this->prophesize(NodeInterface::class);
    $nodeInterface->getType()->willReturn('page');
    try {
      $this->myServiceObject->update($nodeInterface->reveal(), $data);
    }
    catch (\InvalidArgumentException $e) {
      $error = TRUE;
    }
    $this->assertTrue($error);
  }

  /**
   * Function to test get node list method.
   *
   * @covers ::getNodeList
   */
  public function testGetNodeList() {
    $node = $this->prophesize(Node::class);
    $node->id()->willReturn(123);
    $node->getTitle()->willReturn('title');
    $node->getType()->willReturn('article');
    $node->get('body')->willReturn((object) ['value' => 'body']);

    $this->entityTypeManager->getStorage('node')->willReturn(new class($node) {
      private $node;
      public function __construct($node) {
        $this->node = $node;
      }
      public function loadMultiple($ids) {
        return [$this->node->reveal()];
      }
      public function getQuery() {
        return new class {
          public function condition($condition, $value, $operator = '=') {
            return $this;
          }
          public function sort() {
            return $this;
          }
          public function execute() {
            return [123, 456, 789];
          }
        };
      }
    });

    $this->cacheBackend->get(Argument::any())->willReturn((object) ['data' => 'test']);
    $this->cacheBackend->set(Argument::any(), Argument::any(), Argument::any(), Argument::any())->willReturn(FALSE);

    // If caching data is available.
    $result = $this->myServiceObject->getNodeList($node->reveal());
    $this->assertNotEmpty($result);

    // If caching data is not available.
    $this->cacheBackend->get(Argument::any())->willReturn(FALSE);
    $result = $this->myServiceObject->getNodeList($node->reveal());
    $this->assertNotEmpty($result);

    // If node ids are not getting.
    $this->entityTypeManager->getStorage('node')->willReturn(new class($node) {
      private $node;
      public function __construct($node) {
        $this->node = $node;
      }
      public function loadMultiple($ids) {
        return [$this->node->reveal()];
      }
      public function getQuery() {
        return new class {
          public function condition($condition, $value, $operator = '=') {
            return $this;
          }
          public function sort() {
            return $this;
          }
          public function execute() {
            return [];
          }
        };
      }
    });

    $result = $this->myServiceObject->getNodeList($node->reveal());
    $this->assertEmpty($result);
  }

  /**
   * Function to test get all nodes.
   *
   * @covers ::getAllNodes
   */
  public function testGetAllNodes() {
    $this->database->select(Argument::any(), Argument::any())->willReturn(new class {
      /**
       * Anonymous class function.
       */
      public function fields($alias, $fields) {
        return $this;
      }

      /**
       * Anonymous class function.
       */
      public function addField($alias, $fields) {
        return $this;
      }

      /**
       * Anonymous class function.
       */
      public function leftJoin($alias, $fields) {
        return $this;
      }

      /**
       * Anonymous class function.
       */
      public function condition($condition, $value = NULL, $operator = '=') {
        return $this;
      }

      /**
       * Anonymous class function.
       */
      public function range() {
        return $this;
      }

      /**
       * Anonymous class function.
       */
      public function countQuery() {
        return new class {
          public function execute() {
            return new class {
              public function fetchField() {
                return 1;
              }
            };
          }
        };
      }

      /**
       * Anonymous class function.
       */
      public function execute() {
        return $this;
      }

      /**
       * Anonymous class function.
       */
      public function fetchAll() {
        return [
          (object) [
            'nid' => 111,
            'vid' => 123,
            'title' => 'Test title',
          ],
        ];
      }

    });

    // If results is not empty.
    $result = $this->myServiceObject->getAllNodes();
    $this->assertArrayHasKey('data', $result);

    $this->database->select(Argument::any(), Argument::any())->willReturn(new class {
      /**
       * Anonymous class function.
       */
      public function fields($alias, $fields) {
        return $this;
      }

      /**
       * Anonymous class function.
       */
      public function addField($alias, $fields) {
        return $this;
      }

      /**
       * Anonymous class function.
       */
      public function leftJoin($alias, $fields) {
        return $this;
      }

      /**
       * Anonymous class function.
       */
      public function condition($condition, $value = NULL, $operator = '=') {
        return $this;
      }

      /**
       * Anonymous class function.
       */
      public function range() {
        return $this;
      }

      /**
       * Anonymous class function.
       */
      public function countQuery() {
        return new class {
          public function execute() {
            return new class {
              public function fetchField() {
                return 0;
              }
            };
          }
        };
      }

      /**
       * Anonymous class function.
       */
      public function execute() {
        return $this;
      }

      /**
       * Anonymous class function.
       */
      public function fetchAll() {
        return [];
      }
    });

    // If results is empty.
    $result = $this->myServiceObject->getAllNodes();
    $this->assertArrayHasKey('data', $result);
  }
  /**
   * Function to get workflow action message.
   *
   * @dataProvider getContentDetailsProvider
   * @covers ::getContentDetails
   */
  public function testGetContentDetails($type) {
    $result = $this->myServiceObject->getContentDetails($type);
    $this->assertNotEmpty($result);
  }

  /**
   * Function to provide content type.
   */
  public function getContentDetailsProvider() {
    return [
      ['article'],
      ['page'],
      [''],
    ];
  }

  /**
   * Function for unset myServiceObject object.
   */
  public function tearDown() {
    unset($this->myServiceObject);
  }

}
