<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\ExerciseConfigException;
use App\Exceptions\SubmissionFailedException;
use App\Helpers\BrokerProxy;
use App\Helpers\MonitorConfig;
use App\Helpers\BackendSubmitHelper;
use App\Helpers\SubmissionHelper;
use App\Helpers\FileStorageManager;
use App\Helpers\TmpFilesHelper;
use App\Helpers\FileStorage\LocalFileStorage;
use App\Helpers\FileStorage\LocalHashFileStorage;
use App\Model\Entity\Assignment;
use App\Model\Repository\Assignments;
use App\Model\Repository\AssignmentSolutions;
use App\V1Module\Presenters\SubmitPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;
use App\Helpers\JobConfig;
use App\Model\Entity\UploadedFile;
use App\Async\Handler\ResubmitAllAsyncJobHandler;


/**
 * @testCase
 */
class TestSubmitPresenter extends Tester\TestCase
{
    /** @var SubmitPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var App\Model\Repository\Assignments */
    protected $assignments;

    /** @var Nette\Security\User */
    private $user;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->assignments = $container->getByType(App\Model\Repository\Assignments::class);

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

    private function createSubmissionHelper($mockBrokerProxy, $mockFileStorage, $mockGenerator = null)
    {
        return new SubmissionHelper(
            new BackendSubmitHelper($mockBrokerProxy, $mockFileStorage),
            $this->container->getByType(App\Model\Repository\AssignmentSolutions::class),
            $this->container->getByType(App\Model\Repository\AssignmentSolutionSubmissions::class),
            $this->container->getByType(App\Model\Repository\ReferenceSolutionSubmissions::class),
            $this->container->getByType(App\Model\Repository\SubmissionFailures::class),
            $this->container->getByType(App\Helpers\FailureHelper::class),
            $mockGenerator ?? $this->container->getByType(App\Helpers\JobConfig\Generator::class),
            $mockFileStorage,
            $this->container->getByType(App\Model\Repository\UploadedFiles::class),
        );
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);

        $this->presenter = PresenterTestHelper::createPresenter($this->container, SubmitPresenter::class);
        $this->presenter->submissionHelper = Mockery::mock(App\Helpers\SubmissionHelper::class);
        $this->presenter->monitorConfig = new App\Helpers\MonitorConfig(['address' => 'localhost']);
    }

    protected function tearDown()
    {
        Mockery::close();

        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    public function testCanSubmit()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $assignment = current($this->assignments->findAll());

        $request = new Nette\Application\Request(
            'V1:Submit',
            'GET',
            ['action' => 'canSubmit', 'id' => $assignment->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $payload = $result['payload'];
        Assert::count(2, $payload);
        Assert::equal(true, $payload["canSubmit"]);
        Assert::equal(0, $payload["submittedCount"]);
    }

    public function testSubmit()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $user = current($this->presenter->users->findAll());
        $assignment = current($this->assignments->findAll());
        $environment = $assignment->getRuntimeEnvironments()->first();
        $ext = current($environment->getExtensionsList());

        // save fake files into db
        $file1 = new UploadedFile("file1.$ext", new \DateTime(), 0, $user);
        $file2 = new UploadedFile("file2.$ext", new \DateTime(), 0, $user);
        $this->presenter->files->persist($file1);
        $this->presenter->files->persist($file2);
        $this->presenter->files->flush();
        $files = [$file1->getId(), $file2->getId()];

        // prepare return variables for mocked objects
        $jobId = 'jobId';
        $hwGroups = ["group1", "group2"];
        $archiveUrl = "archiveUrl";
        $resultsUrl = "resultsUrl";
        $fileserverUrl = "fileserverUrl";
        $tasksCount = 5;
        $evaluationStarted = true;
        $webSocketMonitorUrl = "webSocketMonitorUrl";

        $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
        $mockJobConfig->shouldReceive("getId")->andReturn($jobId)->atLeast(1)
            ->shouldReceive("getJobId")->andReturn($jobId)->atLeast(1)
            ->shouldReceive("getJobType")->andReturn("student")->atLeast(1)
            ->shouldReceive("getTasksCount")->andReturn($tasksCount)->once()
            ->shouldReceive("getHardwareGroups")->andReturn($hwGroups)->atLeast(1);

        $mockGenerator = Mockery::mock(JobConfig\Generator::class);
        $mockGenerator->shouldReceive("generateJobConfig")->andReturn($mockJobConfig)->once();
        $this->presenter->jobConfigGenerator = $mockGenerator;

        // mock fileserver and broker proxies
        $mockBrokerProxy = Mockery::mock(App\Helpers\BrokerProxy::class);
        $mockBrokerProxy->shouldReceive("startEvaluation")->withArgs(
            [$jobId, $hwGroups, Mockery::any(), $archiveUrl, $resultsUrl]
        )->andReturn($evaluationStarted)->once();

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getWorkerSubmissionExternalUrl")->withArgs(["student", $jobId])->andReturn($archiveUrl)->once()
            ->shouldReceive("getWorkerResultExternalUrl")->withArgs(["student", $jobId])->andReturn($resultsUrl)->once()
            ->shouldReceive("storeUploadedSolutionFile")->withArgs([Mockery::any(), $file1])->once()
            ->shouldReceive("storeUploadedSolutionFile")->withArgs([Mockery::any(), $file2])->once();

        $this->presenter->submissionHelper = $this->createSubmissionHelper($mockBrokerProxy, $mockFileStorage, $mockGenerator);
        $this->presenter->fileStorage = $mockFileStorage;

        // fake monitor configuration
        $monitorConfig = new MonitorConfig(
            [
                "address" => $webSocketMonitorUrl
            ]
        );
        $this->presenter->monitorConfig = $monitorConfig;

        $request = new Nette\Application\Request(
            'V1:Submit',
            'POST',
            ['action' => 'submit', 'id' => $assignment->getId()],
            [
                'note' => 'someNiceNoteAboutThisCrazySubmit',
                'files' => $files,
                'runtimeEnvironmentId' => $environment->getId()
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(3, $result['payload']);

        $solution = $result['payload']['solution'];
        Assert::type('array', $solution);
        Assert::equal($assignment->getId(), $solution['exerciseAssignmentId']);

        $submission = $result['payload']['submission'];
        Assert::type('array', $submission);
        Assert::equal($solution['id'], $submission['assignmentSolutionId']);

        $webSocketChannel = $result['payload']['webSocketChannel'];
        Assert::count(3, $webSocketChannel);
        Assert::equal($jobId, $webSocketChannel['id']);
        Assert::equal($webSocketMonitorUrl, $webSocketChannel['monitorUrl']);
        Assert::equal($tasksCount, $webSocketChannel['expectedTasksCount']);
    }

    public function testSubmissionFailure()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $user = current($this->presenter->users->findAll());
        $assignment = current($this->assignments->findAll());
        $environment = $assignment->getRuntimeEnvironments()->first();
        $ext = current($environment->getExtensionsList());

        // save fake files into db
        $file1 = new UploadedFile("file1.$ext", new \DateTime(), 0, $user);
        $file2 = new UploadedFile("file2.$ext", new \DateTime(), 0, $user);
        $this->presenter->files->persist($file1);
        $this->presenter->files->persist($file2);
        $this->presenter->files->flush();
        $files = [$file1->getId(), $file2->getId()];

        // prepare return variables for mocked objects
        $jobId = 'jobId';
        $hwGroups = ["group1", "group2"];
        $archiveUrl = "archiveUrl";
        $resultsUrl = "resultsUrl";
        $fileserverUrl = "fileserverUrl";
        $tasksCount = 5;
        $webSocketMonitorUrl = "webSocketMonitorUrl";

        $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
        $mockJobConfig->shouldReceive("getId")->andReturn($jobId)->atLeast(1)
            ->shouldReceive("getJobId")->andReturn($jobId)->atLeast(1)
            ->shouldReceive("getJobType")->andReturn("student")->atLeast(1)
            ->shouldReceive("getTasksCount")->withAnyArgs()->andReturn($tasksCount)->zeroOrMoreTimes()
            ->shouldReceive("getHardwareGroups")->andReturn($hwGroups)->atLeast(1);

        $mockGenerator = Mockery::mock(JobConfig\Generator::class);
        $mockGenerator->shouldReceive("generateJobConfig")->withAnyArgs()
            ->andReturn($mockJobConfig)->once();
        $this->presenter->jobConfigGenerator = $mockGenerator;

        // mock file storage and broker proxies
        $mockBrokerProxy = Mockery::mock(App\Helpers\BrokerProxy::class);
        $mockBrokerProxy->shouldReceive("startEvaluation")->withArgs(
            [$jobId, $hwGroups, Mockery::any(), $archiveUrl, $resultsUrl]
        )
            ->andThrow(SubmissionFailedException::class)->once();

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getWorkerSubmissionExternalUrl")->withArgs(["student", $jobId])->andReturn($archiveUrl)->once()
            ->shouldReceive("getWorkerResultExternalUrl")->withArgs(["student", $jobId])->andReturn($resultsUrl)->once()
            ->shouldReceive("storeUploadedSolutionFile")->withArgs([Mockery::any(), $file1])->once()
            ->shouldReceive("storeUploadedSolutionFile")->withArgs([Mockery::any(), $file2])->once();

        $this->presenter->submissionHelper = $this->createSubmissionHelper($mockBrokerProxy, $mockFileStorage, $mockGenerator);
        $this->presenter->fileStorage = $mockFileStorage;

        // fake monitor configuration
        $monitorConfig = new MonitorConfig(
            [
                "address" => $webSocketMonitorUrl
            ]
        );
        $this->presenter->monitorConfig = $monitorConfig;

        $request = new Nette\Application\Request(
            'V1:Submit',
            'POST',
            ['action' => 'submit', 'id' => $assignment->getId()],
            [
                'note' => 'someNiceNoteAboutThisCrazySubmit',
                'files' => $files,
                'runtimeEnvironmentId' => $environment->getId()
            ]
        );

        $failureCount = count($this->presenter->submissionFailures->findAll());

        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            SubmissionFailedException::class
        );

        $newFailureCount = count($this->presenter->submissionFailures->findAll());
        Assert::same($failureCount + 1, $newFailureCount);
    }

    public function testSubmissionFailureInJobConfigGeneration()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $user = current($this->presenter->users->findAll());
        $assignment = current($this->assignments->findAll());
        $environment = $assignment->getRuntimeEnvironments()->first();
        $ext = current($environment->getExtensionsList());

        // save fake files into db
        $file1 = new UploadedFile("file1.$ext", new \DateTime(), 0, $user);
        $file2 = new UploadedFile("file2.$ext", new \DateTime(), 0, $user);
        $this->presenter->files->persist($file1);
        $this->presenter->files->persist($file2);
        $this->presenter->files->flush();
        $files = [$file1->getId(), $file2->getId()];

        // prepare return variables for mocked objects
        $jobId = 'jobId';
        $archiveUrl = "archiveUrl";
        $resultsUrl = "resultsUrl";
        $fileserverUrl = "fileserverUrl";
        $webSocketMonitorUrl = "webSocketMonitorUrl";

        /** @var Mockery\Mock | JobConfig\Generator $mockGenerator */
        $mockGenerator = Mockery::mock(JobConfig\Generator::class);
        $mockGenerator->shouldReceive("generateJobConfig")->withAnyArgs()
            ->andThrow(new ExerciseConfigException());
        $this->presenter->jobConfigGenerator = $mockGenerator;

        // mock fileserver and broker proxies
        $mockBrokerProxy = Mockery::mock(App\Helpers\BrokerProxy::class);
        $mockBrokerProxy->shouldReceive("startEvaluation")->never();

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("storeUploadedSolutionFile")->withArgs([Mockery::any(), $file1])->once()
            ->shouldReceive("storeUploadedSolutionFile")->withArgs([Mockery::any(), $file2])->once();

        $this->presenter->submissionHelper = $this->createSubmissionHelper($mockBrokerProxy, $mockFileStorage, $mockGenerator);
        $this->presenter->fileStorage = $mockFileStorage;

        // fake monitor configuration
        $monitorConfig = new MonitorConfig(
            [
                "address" => $webSocketMonitorUrl
            ]
        );
        $this->presenter->monitorConfig = $monitorConfig;

        $request = new Nette\Application\Request(
            'V1:Submit',
            'POST',
            ['action' => 'submit', 'id' => $assignment->getId()],
            [
                'note' => 'someNiceNoteAboutThisCrazySubmit',
                'files' => $files,
                'runtimeEnvironmentId' => $environment->getId()
            ]
        );

        $failureCount = count($this->presenter->submissionFailures->findAll());

        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            ExerciseConfigException::class
        );

        $newFailureCount = count($this->presenter->submissionFailures->findAll());
        Assert::same($failureCount + 1, $newFailureCount);
    }

    public function testResubmit()
    {
        /** @var AssignmentSolutions $solutions */
        $solutions = $this->container->getByType(AssignmentSolutions::class);
        $solution = current($solutions->findAll());
        $solutionCount = count($solutions->findAll());
        $submissionCount = $solution->getSubmissions()->count();

        PresenterTestHelper::loginDefaultAdmin($this->container);

        // prepare return variables for mocked objects
        $jobId = 'jobId';
        $hwGroups = ["group1", "group2"];
        $archiveUrl = "archiveUrl";
        $resultsUrl = "resultsUrl";
        $fileserverUrl = "fileserverUrl";
        $tasksCount = 5;
        $webSocketMonitorUrl = "webSocketMonitorUrl";

        $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
        $mockJobConfig->shouldReceive("getId")->andReturn($jobId)->atLeast(1)
            ->shouldReceive("getJobId")->andReturn($jobId)->atLeast(1)
            ->shouldReceive("getJobType")->andReturn("student")->atLeast(1)
            ->shouldReceive("getTasksCount")->andReturn($tasksCount)->zeroOrMoreTimes()
            ->shouldReceive("getHardwareGroups")->andReturn($hwGroups)->atLeast(1);

        $mockGenerator = Mockery::mock(JobConfig\Generator::class);
        $mockGenerator->shouldReceive("generateJobConfig")->andReturn($mockJobConfig)->once();
        $this->presenter->jobConfigGenerator = $mockGenerator;

        // mock file storage and broker proxies
        $mockBrokerProxy = Mockery::mock(BrokerProxy::class);
        $mockBrokerProxy->shouldReceive("startEvaluation")->withArgs(
            [$jobId, $hwGroups, Mockery::any(), $archiveUrl, $resultsUrl]
        )->andReturn($evaluationStarted = true)->once();

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getWorkerSubmissionExternalUrl")->withArgs(["student", $jobId])->andReturn($archiveUrl)->once()
            ->shouldReceive("getWorkerResultExternalUrl")->withArgs(["student", $jobId])->andReturn($resultsUrl)->once();

        $this->presenter->submissionHelper = $this->createSubmissionHelper($mockBrokerProxy, $mockFileStorage, $mockGenerator);
        $this->presenter->fileStorage = $mockFileStorage;

        // fake monitor configuration
        $monitorConfig = new MonitorConfig(
            [
                "address" => $webSocketMonitorUrl
            ]
        );
        $this->presenter->monitorConfig = $monitorConfig;

        $request = new Nette\Application\Request(
            'V1:Submit',
            'POST',
            ['action' => 'resubmit', 'id' => $solution->getId()],
            ['private' => 0]
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal($solutionCount, count($solutions->findAll()));
        Assert::equal($submissionCount + 1, $solution->getSubmissions()->count());
    }

    public function testResubmitAll()
    {
        /** @var AssignmentSolutions $solutions */
        $solutions = $this->container->getByType(AssignmentSolutions::class);

        /** @var Assignments $assignments */
        $assignments = $this->container->getByType(Assignments::class);

        $assignment = null;
        $totalSubmissionCount = count($this->presenter->assignmentSubmissions->findAll());
        $solutionCount = 2;

        // Find an assignment with desired amount of submissions
        /** @var Assignment $candidate */
        foreach ($assignments->findAll() as $candidate) {
            if ($candidate->getAssignmentSolutions()->count() == $solutionCount) {
                $assignment = $candidate;
                break;
            }
        }

        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        // prepare return variables for mocked objects
        $jobId = 'jobId';
        $hwGroups = ["group1", "group2"];
        $archiveUrl = "archiveUrl";
        $resultsUrl = "resultsUrl";
        $fileserverUrl = "fileserverUrl";
        $tasksCount = 5;
        $webSocketMonitorUrl = "webSocketMonitorUrl";

        $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
        $mockJobConfig->shouldReceive("getId")->andReturn($jobId)->atLeast($solutionCount)
            ->shouldReceive("getJobId")->andReturn($jobId)->atLeast($solutionCount)
            ->shouldReceive("getJobType")->andReturn("student")->atLeast(1)
            ->shouldReceive("getTasksCount")->andReturn($tasksCount)->atLeast($solutionCount)
            ->shouldReceive("getHardwareGroups")->andReturn($hwGroups)->atLeast($solutionCount);

        $mockGenerator = Mockery::mock(JobConfig\Generator::class);
        $mockGenerator->shouldReceive("generateJobConfig")->withAnyArgs()
            ->andReturn($mockJobConfig)->times($solutionCount);
        $this->presenter->jobConfigGenerator = $mockGenerator;

        // mock file storage and broker proxies
        $mockBrokerProxy = Mockery::mock(BrokerProxy::class);
        $mockBrokerProxy->shouldReceive("startEvaluation")->withArgs(
            [$jobId, $hwGroups, Mockery::any(), $archiveUrl, $resultsUrl]
        )->andReturn($evaluationStarted = true)->times($solutionCount);

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getWorkerSubmissionExternalUrl")->withArgs(["student", $jobId])->andReturn($archiveUrl)->atLeast(1)
            ->shouldReceive("getWorkerResultExternalUrl")->withArgs(["student", $jobId])->andReturn($resultsUrl)->atLeast(1);

        $this->presenter->submissionHelper = $this->createSubmissionHelper($mockBrokerProxy, $mockFileStorage, $mockGenerator);
        $this->presenter->fileStorage = $mockFileStorage;

        // fake monitor configuration
        $monitorConfig = new MonitorConfig(
            [
                "address" => $webSocketMonitorUrl
            ]
        );
        $this->presenter->monitorConfig = $monitorConfig;

        $request = new Nette\Application\Request(
            'V1:Submit',
            'POST',
            ['action' => 'resubmitAll', 'id' => $assignment->getId()],
            []
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal(['pending', 'failed'], array_keys($result['payload']));
        Assert::count(1, $result['payload']['pending']);
        Assert::count(0, $result['payload']['failed']);

        if (!empty($result['payload']['pending']) && count($result['payload']['pending']) === 1) {
            // manually process the async job to make sure the result will be correct
            $asyncJob = $result['payload']['pending'][0];
            $handler = new ResubmitAllAsyncJobHandler($this->presenter->submissionHelper, $this->presenter->assignments);
            $handler->execute($asyncJob);
            Assert::equal(
                $totalSubmissionCount + $solutionCount,
                count($this->presenter->assignmentSubmissions->findAll())
            );
        }
    }

    public function testPreSubmit()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $user = current($this->presenter->users->findAll());
        $assignment = current($this->assignments->findAll());
        $assignment->setSolutionFilesLimit(2);
        $assignment->setSolutionSizeLimit(42);
        $environment = $assignment->getRuntimeEnvironments()->first();
        $ext = current($environment->getExtensionsList());

        $mockBrokerProxy = Mockery::mock(BrokerProxy::class);
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $this->presenter->submissionHelper = $this->createSubmissionHelper($mockBrokerProxy, $mockFileStorage);

        // save fake files into db
        $file1 = new UploadedFile("file1.$ext", new \DateTime(), 20, $user);
        $file2 = new UploadedFile("file2.$ext", new \DateTime(), 22, $user);
        $this->presenter->files->persist($file1);
        $this->presenter->files->persist($file2);
        $this->presenter->files->flush();
        $files = [$file1->getId(), $file2->getId()];

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Submit',
            'POST',
            ['action' => 'preSubmit', 'id' => $assignment->getId()],
            ['files' => $files]
        );

        Assert::count(4, $payload);
        Assert::true(array_key_exists("environments", $payload));
        Assert::true(array_key_exists("submitVariables", $payload));
        Assert::true($payload["countLimitOK"]);
        Assert::true($payload["sizeLimitOK"]);

        Assert::equal([$environment->getId()], $payload["environments"]);
        Assert::count(2, $payload["submitVariables"]);
    }

    public function testPreSubmitFailCountLimit()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $user = current($this->presenter->users->findAll());
        $assignment = current($this->assignments->findAll());
        $assignment->setSolutionFilesLimit(1);
        $environment = $assignment->getRuntimeEnvironments()->first();
        $ext = current($environment->getExtensionsList());

        $mockBrokerProxy = Mockery::mock(BrokerProxy::class);
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $this->presenter->submissionHelper = $this->createSubmissionHelper($mockBrokerProxy, $mockFileStorage);

        // save fake files into db
        $file1 = new UploadedFile("file1.$ext", new \DateTime(), 0, $user);
        $file2 = new UploadedFile("file2.$ext", new \DateTime(), 0, $user);
        $this->presenter->files->persist($file1);
        $this->presenter->files->persist($file2);
        $this->presenter->files->flush();
        $files = [$file1->getId(), $file2->getId()];

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Submit',
            'POST',
            ['action' => 'preSubmit', 'id' => $assignment->getId()],
            ['files' => $files]
        );

        Assert::false($payload["countLimitOK"]);
        Assert::true($payload["sizeLimitOK"]);
    }

    public function testPreSubmitFailSizeLimit()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $user = current($this->presenter->users->findAll());
        $assignment = current($this->assignments->findAll());
        $assignment->setSolutionSizeLimit(42);
        $environment = $assignment->getRuntimeEnvironments()->first();
        $ext = current($environment->getExtensionsList());

        $mockBrokerProxy = Mockery::mock(BrokerProxy::class);
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $this->presenter->submissionHelper = $this->createSubmissionHelper($mockBrokerProxy, $mockFileStorage);

        // save fake files into db
        $file1 = new UploadedFile("file1.$ext", new \DateTime(), 40000, $user);
        $file2 = new UploadedFile("file2.$ext", new \DateTime(), 40000, $user);
        $this->presenter->files->persist($file1);
        $this->presenter->files->persist($file2);
        $this->presenter->files->flush();
        $files = [$file1->getId(), $file2->getId()];

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Submit',
            'POST',
            ['action' => 'preSubmit', 'id' => $assignment->getId()],
            ['files' => $files]
        );

        Assert::true($payload["countLimitOK"]);
        Assert::false($payload["sizeLimitOK"]);
    }
}

(new TestSubmitPresenter())->run();
