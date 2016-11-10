<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Model\Entity\Group;
use App\V1Module\Presenters\GroupsPresenter;
use Tester\Assert;

class TestGroupsPresenter extends Tester\TestCase
{
  private $userLogin = "user2@example.com";
  private $userPassword = "password2";

  private $adminLogin = "admin@admin.com";
  private $adminPassword = "admin";

  /** @var GroupsPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var  Nette\DI\Container */
  protected $container;

  /** @var Nette\Security\User */
  private $user;

  /** @var \App\Security\AccessManager */
  private $accessManager;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->accessManager = $container->getByType(\App\Security\AccessManager::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);
    $this->presenter = PresenterTestHelper::createPresenter($this->container, GroupsPresenter::class);
  }

  protected function tearDown()
  {
    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testUserCannotListAllGroups()
  {
    $token = PresenterTestHelper::login($this->container, $this->userLogin, $this->userPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    /** @var \Nette\Application\Responses\JsonResponse $response */
    Assert::exception(function () {
      $request = new Nette\Application\Request('V1:Groups', 'GET', ['action' => 'default']);
      $this->presenter->run($request);
    }, App\Exceptions\ForbiddenRequestException::class);
  }

  public function testAdminCanListAllGroups()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $request = new Nette\Application\Request('V1:Groups', 'GET', ['action' => 'default']);

    /** @var \Nette\Application\Responses\JsonResponse $response */
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result["code"]);
    Assert::equal(2, count($result["payload"]));
  }

  public function testUserCannotJoinPrivateGroup()
  {
    $token = PresenterTestHelper::login($this->container, $this->userLogin, $this->userPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $user = $this->accessManager->getUser($this->accessManager->decodeToken($token));
    $group = $user->instance->getGroups()->filter(
      function (Group $group) { return !$group->isPublic; }
    )->first();

    $request = new Nette\Application\Request('V1:Groups', 'POST', [
      'action' => 'addStudent',
      'id' => $group->id,
      'userId' => $user->id
    ]);

    /** @var \Nette\Application\Responses\JsonResponse $response */
    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, App\Exceptions\ForbiddenRequestException::class);
  }

  public function testUserCanJoinPublicGroup()
  {
    $token = PresenterTestHelper::login($this->container, $this->userLogin, $this->userPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $user = $this->accessManager->getUser($this->accessManager->decodeToken($token));
    $group = $user->instance->getGroups()->filter(
      function (Group $group) { return $group->isPublic; }
    )->first();

    $request = new Nette\Application\Request('V1:Groups', 'POST', [
      'action' => 'addStudent',
      'id' => $group->id,
      'userId' => $user->id
    ]);

    /** @var \Nette\Application\Responses\JsonResponse $response */
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result["code"]);
  }
}

$testCase = new TestGroupsPresenter();
$testCase->run();
