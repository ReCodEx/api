<?php
use App\Security\IAuthorizator;
use App\Security\Resource;
use App\V1Module\Presenters\BasePresenter;
use Mockery\Mock;
use Nette\Application\Request;
use Nette\Application\Responses\JsonResponse;
use Tester\Assert;

$container = require_once __DIR__ . '/../bootstrap.php';

class FakePresenter extends BasePresenter
{
  /**
   * @POST
   * @UserIsAllowed(groups="action")
   * @Resource(groups="id")
   * @param $id
   */
  public function actionSimple($id)
  {
    $this->sendSuccessResponse("OK");
  }

  /**
   * @POST
   * @UserIsAllowed(groups="action")
   * @UserIsAllowed(users="action")
   * @Resource(groups="id", users="id")
   * @param $id
   */
  public function actionOrCondition($id)
  {
    $this->sendSuccessResponse("OK");
  }

  /**
   * @POST
   * @UserIsAllowed(groups="action", users="action")
   * @Resource(groups="id", users="id")
   * @param $id
   */
  public function actionAndCondition($id)
  {
    $this->sendSuccessResponse("OK");
  }

  /**
   * @POST
   * @UserIsAllowed(groups="action")
   */
  public function actionNoResource()
  {
    $this->sendSuccessResponse("OK");
  }
}

class TestBasePresenter extends Tester\TestCase
{
  use MockeryTrait;

  /** @var FakePresenter */
  private $presenter;

  /** @var Mock|IAuthorizator */
  private $authorizator;

  /** @var Nette\DI\Container */
  private $container;

  function __construct(Nette\DI\Container $container)
  {
    $this->container = $container;
  }

  protected function setUp()
  {
    $this->presenter = new FakePresenter();
    $this->container->callInjects($this->presenter);
    $this->presenter->authorizator = $this->authorizator = Mockery::mock(IAuthorizator::class);
  }

  public function testSimpleAllowed()
  {
    $this->authorizator->shouldReceive("isAllowed")->withArgs([
      Mockery::any(),
      Mockery::on(function (Resource $resource) {
        return $resource->getResourceId() === "groups" && $resource->getId() === "42";
      }),
      "action"
    ])->andReturn(TRUE);

    $request = new Request("Fake", "POST", [
      "action" => "simple",
      "id" => 42
    ]);

    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
  }

  public function testSimpleDenied()
  {
    $this->authorizator->shouldReceive("isAllowed")->withArgs([
      Mockery::any(),
      Mockery::on(function (Resource $resource) {
        return $resource->getResourceId() === "groups" && $resource->getId() === "42";
      }),
      "action"
    ])->andReturn(FALSE);

    $request = new Request("Fake", "POST", [
      "action" => "simple",
      "id" => 42
    ]);

    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, App\Exceptions\ForbiddenRequestException::class);
  }

  public function testOrConditionAllowed()
  {
    $this->authorizator->shouldReceive("isAllowed")->withArgs([
      Mockery::any(),
      Mockery::on(function (Resource $resource) {
        return $resource->getResourceId() === "groups" && $resource->getId() === "42";
      }),
      "action"
    ])->andReturn(FALSE);

    $this->authorizator->shouldReceive("isAllowed")->withArgs([
      Mockery::any(),
      Mockery::on(function (Resource $resource) {
        return $resource->getResourceId() === "users" && $resource->getId() === "42";
      }),
      "action"
    ])->andReturn(TRUE);

    $request = new Request("Fake", "POST", [
      "action" => "orCondition",
      "id" => 42
    ]);

    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
  }

  public function testOrConditionDenied()
  {
    $this->authorizator->shouldReceive("isAllowed")->withArgs([
      Mockery::any(),
      Mockery::on(function (Resource $resource) {
        return $resource->getResourceId() === "groups" && $resource->getId() === "42";
      }),
      "action"
    ])->andReturn(FALSE);

    $this->authorizator->shouldReceive("isAllowed")->withArgs([
      Mockery::any(),
      Mockery::on(function (Resource $resource) {
        return $resource->getResourceId() === "users" && $resource->getId() === "42";
      }),
      "action"
    ])->andReturn(FALSE);

    $request = new Request("Fake", "POST", [
      "action" => "orCondition",
      "id" => 42
    ]);

    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, App\Exceptions\ForbiddenRequestException::class);
  }

  public function testAndConditionAllowed()
  {
    $this->authorizator->shouldReceive("isAllowed")->withArgs([
      Mockery::any(),
      Mockery::on(function (Resource $resource) {
        return $resource->getResourceId() === "groups" && $resource->getId() === "42";
      }),
      "action"
    ])->andReturn(TRUE);

    $this->authorizator->shouldReceive("isAllowed")->withArgs([
      Mockery::any(),
      Mockery::on(function (Resource $resource) {
        return $resource->getResourceId() === "users" && $resource->getId() === "42";
      }),
      "action"
    ])->andReturn(TRUE);

    $request = new Request("Fake", "POST", [
      "action" => "andCondition",
      "id" => 42
    ]);

    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
  }

  public function testAndConditionDenied()
  {
    $this->authorizator->shouldReceive("isAllowed")->withArgs([
      Mockery::any(),
      Mockery::on(function (Resource $resource) {
        return $resource->getResourceId() === "groups" && $resource->getId() === "42";
      }),
      "action"
    ])->andReturn(FALSE);

    $this->authorizator->shouldReceive("isAllowed")->withArgs([
      Mockery::any(),
      Mockery::on(function (Resource $resource) {
        return $resource->getResourceId() === "users" && $resource->getId() === "42";
      }),
      "action"
    ])->andReturn(FALSE);

    $request = new Request("Fake", "POST", [
      "action" => "andCondition",
      "id" => 42
    ]);

    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, App\Exceptions\ForbiddenRequestException::class);
  }

  public function testNoResourceAnnotation()
  {
    $this->authorizator->shouldReceive("isAllowed")->withArgs([
      Mockery::any(),
      Mockery::on(function (string $resource) {
        return $resource === "groups";
      }),
      "action"
    ])->andReturn(TRUE);

    $request = new Request("Fake", "POST", [
      "action" => "noResource"
    ]);

    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
  }
}

$test = new TestBasePresenter($container);
$test->run();