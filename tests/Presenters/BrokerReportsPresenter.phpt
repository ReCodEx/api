<?php

use App\Helpers\BrokerConfig;
use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\LocalImmutableFile;
use App\Helpers\TmpFilesHelper;
use App\Helpers\FileStorage\LocalFileStorage;
use App\Helpers\FileStorage\LocalHashFileStorage;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Entity\AssignmentSolution;
use App\V1Module\Presenters\BrokerReportsPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Application\Request;
use Nette\Application\Responses\JsonResponse;
use Tester\Assert;

$container = require_once __DIR__ . "/../bootstrap.php";
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

/**
 * @testCase
 */
class TestBrokerReportsPresenter extends Tester\TestCase
{
    /** @var BrokerReportsPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Nette\Http\Request */
    protected $originalHttpRequest;

    /** @var string */
    protected $httpRequestName;

    /** @var Mockery\Mock|Nette\Http\Request */
    protected $mockHttpRequest;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);

        // Rig HTTP authentication credentials
        $brokerConfig = current($this->container->findByType(BrokerConfig::class));
        $this->container->removeService($brokerConfig);
        $this->container->addService(
            $brokerConfig,
            new BrokerConfig(
                [
                    "auth" => [
                        "username" => "user",
                        "password" => "pass"
                    ]
                ]
            )
        );

        // Remove HTTP Request object from container
        $this->httpRequestName = current($this->container->findByType(Nette\Http\Request::class));
        $this->originalHttpRequest = $this->container->getService($this->httpRequestName);
        $this->container->removeService($this->httpRequestName);

        // patch container, since we cannot create actual file storage manarer
        $fsName = current($this->container->findByType(FileStorageManager::class));
        $this->container->removeService($fsName);
        $this->container->addService($fsName, new FileStorageManager(
            Mockery::mock(LocalFileStorage::class),
            Mockery::mock(LocalHashFileStorage::class),
            Mockery::mock(TmpFilesHelper::class),
            ""
        ));
    }

    protected function setUp()
    {
        $this->mockHttpRequest = Mockery::mock($this->originalHttpRequest);
        $this->mockHttpRequest->makePartial();
        $this->container->addService($this->httpRequestName, $this->mockHttpRequest);
        $this->mockHttpRequest->shouldReceive("getHeader")
            ->zeroOrMoreTimes()
            ->with("Authorization")
            ->andReturn("Basic " . base64_encode("user:pass"));

        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, BrokerReportsPresenter::class);
    }

    protected function tearDown()
    {
        Mockery::close();
    }

    public function testJobFailed()
    {
        $submission = current($this->presenter->submissions->findAll());
        $request = new Request(
            "V1:BrokerReports",
            "POST",
            [
            "action" => "jobStatus",
            "jobId" => AssignmentSolution::JOB_TYPE . '_' . $submission->getId()
            ],
            [
                "status" => "FAILED",
                "message" => "whatever"
            ]
        );

        $response = $this->presenter->run($request);
        Assert::type(JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::same(200, $result["code"]);
        Assert::same("OK", $result["payload"]);

        $this->presenter->submissions->refresh($submission);
        Assert::true($submission->getFailure() !== null);
    }

    public function testReferenceEvaluationFailed()
    {
        $submission = current($this->presenter->referenceSolutionSubmissions->findAll());
        $request = new Request(
            "V1:BrokerReports",
            "POST",
            [
            "action" => "jobStatus",
            "jobId" => ReferenceSolutionSubmission::JOB_TYPE . '_' . $submission->getId()
            ],
            [
                "status" => "FAILED",
                "message" => "whatever"
            ]
        );

        $response = $this->presenter->run($request);
        Assert::type(JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::same(200, $result["code"]);
        Assert::same("OK", $result["payload"]);

        $this->presenter->referenceSolutionSubmissions->refresh($submission);
        Assert::true($submission->getFailure() !== null);
    }
}

$testCase = new TestBrokerReportsPresenter();
$testCase->run();
