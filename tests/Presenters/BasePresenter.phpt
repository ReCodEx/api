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
}

$test = new TestBasePresenter($container);
$test->run();