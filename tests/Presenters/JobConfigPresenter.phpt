<?php
use App\V1Module\Presenters\JobConfigPresenter;
use Nette\Application\Request;
use Nette\Application\Responses\JsonResponse;
use Tester\Assert;

$container = require_once __DIR__ . "/../bootstrap.php";

class TestJobConfigPresenter extends Tester\TestCase
{
  /** @var JobConfigPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var Nette\DI\Container */
  protected $container;

  /** @var Nette\Security\User */
  private $user;

  private $validConfig = "
submission:
    job-id: testConfig
    hw-groups:
        - group2
tasks:
    - task-id: task1
      priority: 2
      cmd:
          bin: gcc
";

  private $invalidConfig = "
submission:
    job-id: testConfig
  hw-groups:
        - group2
tasks:
    - task-id: task1
    priority: 2
      cmd:
          bin: gcc
";

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
    $this->presenter = PresenterTestHelper::createPresenter($this->container, JobConfigPresenter::class);
  }

  protected function tearDown()
  {
    $this->user->logout(TRUE);
  }

  public function testValidation1()
  {
    $request = new Request("V1:JobConfig", "POST", ["action" => "validate"], [
      "jobConfig" => $this->validConfig
    ]);

    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
    $result = $response->getPayload();

    Assert::same(200, $result["code"]);
    Assert::equal([], $result["payload"]);
  }

  public function testValidation2()
  {
    $request = new Request("V1:JobConfig", "POST", ["action" => "validate"], [
      "jobConfig" => $this->invalidConfig
    ]);

    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
    $result = $response->getPayload();

    Assert::same(200, $result["code"]);
    Assert::equal(4, $result["payload"]["line"]);
  }

  public function testValidation3()
  {
    $request = new Request("V1:JobConfig", "POST", ["action" => "validate"], [
      "jobConfig" => "submission:\n  job-id: bla\n"
    ]);

    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
    $result = $response->getPayload();

    Assert::same(200, $result["code"]);
    Assert::count(1, $result["payload"]);
  }
}

$testCase = new TestJobConfigPresenter();
$testCase->run();
