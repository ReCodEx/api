<?php

use App\V1Module\Presenters\SubmissionFailuresPresenter;
use Doctrine\ORM\EntityManagerInterface;
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

    /** @var EntityManagerInterface */
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
        $this->user->logout(true);
        Mockery::close();
    }

    public function testListAll()
    {
        $request = new Request("V1:SubmissionFailures", "GET", ["action" => "default"], []);
        $response = $this->presenter->run($request);
        Assert::type(JsonResponse::class, $response);
        $result = $response->getPayload();
        Assert::count(2, $result["payload"]);
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
        $request = new Request("V1:SubmissionFailures", "GET", ["action" => "detail", "id" => $failure->getId()], []);
        $response = $this->presenter->run($request);
        Assert::type(JsonResponse::class, $response);
        $result = $response->getPayload();
        Assert::same($failure, $result["payload"]);
    }

    public function testResolveWithoutEmail()
    {
        $mockFailuresSender = Mockery::mock();
        $mockFailuresSender->shouldReceive("failureResolved")->never();
        $this->presenter->failureResolutionEmailsSender = $mockFailuresSender;

        $failure = current($this->presenter->submissionFailures->findUnresolved());
        $request = new Request(
            "V1:SubmissionFailures",
            "POST",
            ["action" => "resolve", "id" => $failure->getId()],
            ["note" => "bla", "sendEmail" => false]
        );
        $response = $this->presenter->run($request);
        Assert::type(JsonResponse::class, $response);
        $result = $response->getPayload();
        Assert::same(200, $result["code"]);
        Assert::same($failure, $result["payload"]);
        Assert::same("bla", $failure->getResolutionNote());
        Assert::notSame(null, $failure->getResolvedAt());
    }

    public function testResolveWithEmail()
    {
        $mockFailuresSender = Mockery::mock();
        $mockFailuresSender->shouldReceive("failureResolved")->once();
        $this->presenter->failureResolutionEmailsSender = $mockFailuresSender;

        $failure = current($this->presenter->submissionFailures->findUnresolved());
        $request = new Request(
            "V1:SubmissionFailures",
            "POST",
            ["action" => "resolve", "id" => $failure->getId()],
            ["note" => "bla", "sendEmail" => true]
        );
        $response = $this->presenter->run($request);
        Assert::type(JsonResponse::class, $response);
        $result = $response->getPayload();
        Assert::same(200, $result["code"]);
        Assert::same($failure, $result["payload"]);
        Assert::same("bla", $failure->getResolutionNote());
        Assert::notSame(null, $failure->getResolvedAt());
    }
}

$testCase = new TestSubmissionFailures();
$testCase->run();
