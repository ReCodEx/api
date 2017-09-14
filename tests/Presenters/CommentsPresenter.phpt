<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\V1Module\Presenters\CommentsPresenter;
use Tester\Assert;


/**
 * @testCase
 */
class TestCommentsPresenter extends Tester\TestCase
{
  private $userLogin = "user1@example.com";
  private $userPassword = "password1";

  /** @var CommentsPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var  Nette\DI\Container */
  protected $container;

  /** @var Nette\Security\User */
  private $user;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);

    $this->presenter = PresenterTestHelper::createPresenter($this->container, CommentsPresenter::class);
  }

  protected function tearDown()
  {
    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testGetCommentsInNormalThread()
  {
    $token = PresenterTestHelper::login($this->container, $this->userLogin, $this->userPassword);

    $request = new Nette\Application\Request('V1:Comments', 'GET', ['action' => 'default', 'id' => 'mainThread']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($this->presenter->comments->getThread('mainThread'), $result['payload']);
    $comments = $result['payload']->jsonSerialize()['comments'];

    // Two comments for this user
    Assert::equal(2, count($comments));
    // But only one public comment
    Assert::equal(1, count(array_filter($comments, function ($comment) {return $comment->getIsPrivate();})));
  }

  public function testGetCommentsInEmptyThread()
  {
    $token = PresenterTestHelper::login($this->container, $this->userLogin, $this->userPassword);

    $request = new Nette\Application\Request('V1:Comments', 'GET', ['action' => 'default', 'id' => 'emptyThread']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    $comments = $result['payload']->jsonSerialize()['comments'];
    Assert::equal(0, count($comments));
  }

  public function testAddCommentIntoExistingThread()
  {
    $token = PresenterTestHelper::login($this->container, $this->userLogin, $this->userPassword);

    $request = new Nette\Application\Request(
      'V1:Comments',
      'POST',
      ['action' => 'addComment', 'id' => 'mainThread'],
      ['text' => 'some comment text', 'isPrivate' => 'false']
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    $comment = $result['payload'];
    Assert::false($comment->isPrivate);
    Assert::equal("some comment text", $comment->text);
    Assert::equal("mainThread", $comment->commentThread->id);

    // Make sure the assignment was persisted
    Assert::same($this->presenter->comments->findOneBy(['id' => $comment->id]), $result['payload']);
  }

  public function testAddCommentIntoNonexistingThread()
  {
    $token = PresenterTestHelper::login($this->container, $this->userLogin, $this->userPassword);

    $request = new Nette\Application\Request(
      'V1:Comments',
      'POST',
      ['action' => 'addComment', 'id' => 'dummyThreadId'],
      ['text' => 'some comment text', 'isPrivate' => 'false']
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    $comment = $result['payload'];
    Assert::equal("dummyThreadId", $comment->commentThread->id);
  }

  public function testTogglePrivate()
  {
    PresenterTestHelper::login($this->container, $this->userLogin);

    $comments = $this->presenter->comments->findAll();
    $exampleComment = array_pop($comments);
    $oldPrivateFlag = $exampleComment->isPrivate;
    $oldId = $exampleComment->id;

    $request = new Nette\Application\Request(
      'V1:Comments',
      'POST',
      ['action' => 'togglePrivate', 'threadId' => 'mainThread', 'commentId' => $exampleComment->id]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    $comment = $result['payload'];
    Assert::equal($comment->id, $oldId);
    Assert::true($comment->isPrivate !== $oldPrivateFlag);
  }
}

$testCase = new TestCommentsPresenter();
$testCase->run();
