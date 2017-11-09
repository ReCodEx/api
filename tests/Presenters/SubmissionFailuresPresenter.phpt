<?php
use App\Model\Entity\SubmissionFailure;
use App\V1Module\Presenters\SubmissionFailuresPresenter;
use Nette\Application\Request;
use Nette\Application\Responses\JsonResponse;
use Tester\Assert;

$container = require_once "../bootstrap.php";


/**
 * @testCase
 */
class TestSubmissionFailures extends Tester\TestCase
{
  /** @var SubmissionFailuresPresenter */
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
    $this->em = PresenterTestHelper::getEntityManager($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);
    $this->presenter = PresenterTestHelper::createPresenter($this->container, SubmissionFailuresPresenter::class);
    PresenterTestHelper::loginDefaultAdmin($this->container);
  }

  protected function tearDown()
  {
    $this->user->logout(TRUE);
    Mockery::close();
  }

  public function testListAll()
  {
    $request = new Request("V1:SubmissionFailures", "GET", ["action" => "default"], []);
    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
    $result = $response->getPayload();
    Assert::count(3, $result["payload"]);
  }

  public function testListUnresolved()
  {
    $request = new Request("V1:SubmissionFailures", "GET", ["action" => "unresolved"], []);
    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
    $result = $response->getPayload();
    Assert::count(1, $result["payload"]);
  }

  public function testDetail()
  {
    $failure = current($this->presenter->submissionFailures->findAll());
    $request = new Request("V1:SubmissionFailures", "GET", ["action" => "detail", "id" => $failure->id], []);
    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
    $result = $response->getPayload();
    Assert::same($failure, $result["payload"]);
  }

  public function testListBySubmission()
  {
    $submission = current($this->presenter->submissions->findAll());
    $request = new Request("V1:SubmissionFailures", "GET", ["action" => "listBySubmission", "submissionId" => $submission->id], []);
    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::true(count($result["payload"]) > 0);

    /** @var SubmissionFailure $item */
    foreach ($result["payload"] as $item) {
      Assert::type(SubmissionFailure::class, $item);
      Assert::same($submission, $item->assignmentSolution);
    }
  }

  public function testResolve()
  {
    $failure = current($this->presenter->submissionFailures->findUnresolved());
    $request = new Request("V1:SubmissionFailures", "POST", ["action" => "resolve", "id" => $failure->id], ["note" => "bla"]);
    $response = $this->presenter->run($request);
    Assert::type(JsonResponse::class, $response);
    $result = $response->getPayload();
    Assert::same(200, $result["code"]);
    Assert::same($failure, $result["payload"]);
    Assert::same("bla", $failure->resolutionNote);
    Assert::notSame(null, $failure->resolvedAt);
  }
}

$testCase = new TestSubmissionFailures();
$testCase->run();
