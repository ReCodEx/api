<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\LocalImmutableFile;
use App\Helpers\FileStorage\LocalFileStorage;
use App\Helpers\FileStorage\LocalHashFileStorage;
use App\Helpers\WorkerFilesConfig;
use App\Helpers\TmpFilesHelper;
use App\V1Module\Presenters\WorkerFilesPresenter;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\WrongCredentialsException;
use App\Exceptions\NotFoundException;
use App\Model\Repository\AssignmentSolutionSubmissions;
use App\Model\Repository\ReferenceSolutionSubmissions;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Responses\StorageFileResponse;

use Tester\Assert;

/**
 * @httpCode any
 * @testCase
 */
class TestWorkerFilesPresenter extends Tester\TestCase
{
    /** @var WorkerFilesPresenter */
    protected $presenter;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var string */
    private $presenterPath = "V1:WorkerFiles";

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

        // patch container, since we cannot create actual file storage manarer
        $fsName = current($this->container->findByType(FileStorageManager::class));
        $this->container->removeService($fsName);
        $this->container->addService($fsName, new FileStorageManager(
            Mockery::mock(LocalFileStorage::class), 
            Mockery::mock(LocalHashFileStorage::class),
            Mockery::mock(TmpFilesHelper::class),
            ""
        ));

        // Rig HTTP authentication credentials
        $configName = current($this->container->findByType(WorkerFilesConfig::class));
        $this->container->removeService($configName);
        $this->container->addService(
            $configName,
            new WorkerFilesConfig(
                [
                    "enabled" => true,
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
        $this->presenter = PresenterTestHelper::createPresenter($this->container, WorkerFilesPresenter::class);
    }

    protected function tearDown()
    {
        Mockery::close();
    }

    public function testNoAuth()
    {
        $this->presenter->config = new WorkerFilesConfig([
            "enabled" => true,
            "auth" => [
                "username" => "user",
                "password" => "wrongpass"
            ]
        ]);

        Assert::exception(function() {
            $request = new \Nette\Application\Request($this->presenterPath, 'GET',
            ['action' => 'downloadSupplementaryFile', 'hash' => 'a123']);
                $response = $this->presenter->run($request);
        }, WrongCredentialsException::class, '');
    }

    public function testDisabled()
    {
        $this->presenter->config = new WorkerFilesConfig([
            "enabled" => false,
            "auth" => [
                "username" => "user",
                "password" => "pass"
            ]
        ]);

        Assert::exception(function() {
            $request = new \Nette\Application\Request($this->presenterPath, 'GET',
            ['action' => 'downloadSupplementaryFile', 'hash' => 'a123']);
                $response = $this->presenter->run($request);
        }, ForbiddenRequestException::class, 'Worker files interface is disabled in the configuration.');
    }

    public function testDownloadSupplementaryFile()
    {
        // mock file and storage
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getSupplementaryFileByHash")
            ->withArgs(['a123'])->andReturn( Mockery::mock(LocalImmutableFile::class) )->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new \Nette\Application\Request($this->presenterPath, 'GET',
            ['action' => 'downloadSupplementaryFile', 'hash' => 'a123']);
        $response = $this->presenter->run($request);

        Assert::type(StorageFileResponse::class, $response);
    }

    public function testDownloadSupplementaryFileNonexist()
    {
        // mock file and storage
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getSupplementaryFileByHash")
            ->withArgs(['a123'])->andReturn(null)->once();
        $this->presenter->fileStorage = $mockFileStorage;

        Assert::exception(function() {
            $request = new \Nette\Application\Request($this->presenterPath, 'GET',
            ['action' => 'downloadSupplementaryFile', 'hash' => 'a123']);
                $response = $this->presenter->run($request);
        }, NotFoundException::class, 'Not Found - Supplementary file not found in the storage');
    }

    public function testDownloadSubmissionArchive()
    {
        // mock file and storage
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getWorkerSubmissionArchive")
            ->withArgs(['student', 'id1'])->andReturn( Mockery::mock(LocalImmutableFile::class) )->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new \Nette\Application\Request($this->presenterPath, 'GET',
            ['action' => 'downloadSubmissionArchive', 'type' => 'student', 'id' => 'id1']);
        $response = $this->presenter->run($request);

        Assert::type(StorageFileResponse::class, $response);
    }

    public function testDownloadSubmissionArchiveCreate()
    {
        $submission = Mockery::mock(AssignmentSolutionSubmission::class);
        $mockSubmissions = Mockery::mock(AssignmentSolutionSubmissions::class);
        $mockSubmissions->shouldReceive("findOrThrow")->withArgs(['id1'])->andReturn($submission)->once();
        $this->presenter->assignmentSubmissions = $mockSubmissions;

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getWorkerSubmissionArchive")
            ->withArgs(['student', 'id1'])->andReturn(null)->once();
        $mockFileStorage->shouldReceive("createWorkerSubmissionArchive")
            ->withArgs([$submission])->andReturn( Mockery::mock(LocalImmutableFile::class) )->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new \Nette\Application\Request($this->presenterPath, 'GET',
            ['action' => 'downloadSubmissionArchive', 'type' => 'student', 'id' => 'id1']);
        $response = $this->presenter->run($request);

        Assert::type(StorageFileResponse::class, $response);
    }

    public function testDownloadSubmissionArchiveNoexist()
    {
        $submission = Mockery::mock(AssignmentSolutionSubmission::class);
        $mockSubmissions = Mockery::mock(AssignmentSolutionSubmissions::class);
        $mockSubmissions->shouldReceive("findOrThrow")->withArgs(['id1'])->andReturn($submission)->once();
        $this->presenter->assignmentSubmissions = $mockSubmissions;

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getWorkerSubmissionArchive")
            ->withArgs(['student', 'id1'])->andReturn(null)->once();
        $mockFileStorage->shouldReceive("createWorkerSubmissionArchive")
            ->withArgs([$submission])->andReturn(null)->once();
        $this->presenter->fileStorage = $mockFileStorage;

        Assert::exception(function() {
            $request = new \Nette\Application\Request($this->presenterPath, 'GET',
                ['action' => 'downloadSubmissionArchive', 'type' => 'student', 'id' => 'id1']);
            $response = $this->presenter->run($request);
        }, NotFoundException::class, 'Not Found - Unable to create worker submission archive (some ingredients may be missing)');
    }

    public function testUploadResultsFile()
    {
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("saveUploadedResultsArchive")
            ->withArgs(['student', 'id1'])->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter, $this->presenterPath, 'PUT',
            ['action' => 'uploadResultsFile', 'type' => 'student', 'id' => 'id1']
        );
        Assert::equal("OK", $payload);
    }
}

$testCase = new TestWorkerFilesPresenter();
$testCase->run();
