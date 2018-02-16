<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\SubmissionFailedException;
use App\Helpers\BrokerProxy;
use App\Helpers\FileServerProxy;
use App\Helpers\JobConfig\GeneratorResult;
use App\Helpers\MonitorConfig;
use App\Helpers\BackendSubmitHelper;
use App\Helpers\SubmissionHelper;
use App\Model\Entity\Assignment;
use App\Model\Repository\Assignments;
use App\Model\Repository\AssignmentSolutions;
use App\V1Module\Presenters\SubmitPresenter;
use Tester\Assert;
use App\Helpers\JobConfig;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\AssignmentSolution;


/**
 * @testCase
 */
class TestSubmitPresenter extends Tester\TestCase
{
  /** @var SubmitPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
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

    $request = new Nette\Application\Request('V1:Submit', 'GET',
      ['action' => 'canSubmit', 'id' => $assignment->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal(true, $result['payload']);
  }

  public function testSubmit()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $user = current($this->presenter->users->findAll());
    $assignment = current($this->assignments->findAll());
    $ext = current($assignment->getRuntimeEnvironments()->first()->getExtensionsList());

    // save fake files into db
    $file1 = new UploadedFile("file1." . $ext, new \DateTime, 0, $user, "file1." . $ext);
    $file2 = new UploadedFile("file2." . $ext, new \DateTime, 0, $user, "file2." . $ext);
    $this->presenter->files->persist($file1);
    $this->presenter->files->persist($file2);
    $this->presenter->files->flush();
    $files = [ $file1->getId(), $file2->getId() ];

    // prepare return variables for mocked objects
    $jobId = 'jobId';
    $hwGroups = ["group1", "group2"];
    $archiveUrl = "archiveUrl";
    $resultsUrl = "resultsUrl";
    $fileserverUrl = "fileserverUrl";
    $tasksCount = 5;
    $evaluationStarted = true;
    $webSocketMonitorUrl = "webSocketMonitorUrl";

    /** @var Mockery\Mock | JobConfig\SubmissionHeader $mockSubmissionHeader */
    $mockSubmissionHeader = Mockery::mock(JobConfig\SubmissionHeader::class);
    $mockSubmissionHeader->shouldReceive("setId")->withArgs([Mockery::any()])->andReturn($mockSubmissionHeader)->once()
      ->shouldReceive("setType")->withArgs([AssignmentSolution::JOB_TYPE])->andReturn($mockSubmissionHeader)->once();

    /** @var Mockery\Mock | JobConfig\JobConfig $mockJobConfig */
    $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
    $mockJobConfig->shouldReceive("getJobId")->withAnyArgs()->andReturn($jobId)->atLeast(1)
      ->shouldReceive("getSubmissionHeader")->withAnyArgs()->andReturn($mockSubmissionHeader)->once()
      ->shouldReceive("getTasksCount")->withAnyArgs()->andReturn($tasksCount)->once()
      ->shouldReceive("getHardwareGroups")->andReturn($hwGroups)->atLeast(1)
      ->shouldReceive("setFileCollector")->with($fileserverUrl)->once();

    /** @var Mockery\Mock | JobConfig\Generator $mockGenerator */
    $mockGenerator = Mockery::mock(JobConfig\Generator::class);
    $mockGenerator->shouldReceive("generateJobConfig")->withAnyArgs()
      ->andReturn(new GeneratorResult("jobConfigPath", $mockJobConfig))->once();
    $this->presenter->jobConfigGenerator = $mockGenerator;

    // mock fileserver and broker proxies
    $mockFileserverProxy = Mockery::mock(App\Helpers\FileServerProxy::class);
    $mockFileserverProxy->shouldReceive("getFileserverTasksUrl")->andReturn($fileserverUrl)->once()
      ->shouldReceive("sendFiles")->withArgs([$jobId, Mockery::any(), Mockery::any()])
      ->andReturn([$archiveUrl, $resultsUrl])->once();
    $mockBrokerProxy = Mockery::mock(App\Helpers\BrokerProxy::class);
    $mockBrokerProxy->shouldReceive("startEvaluation")->withArgs([$jobId, $hwGroups, Mockery::any(), $archiveUrl, $resultsUrl])
      ->andReturn($evaluationStarted)->once();
    $this->presenter->submissionHelper = new SubmissionHelper(new BackendSubmitHelper($mockBrokerProxy, $mockFileserverProxy));

    // fake monitor configuration
    $monitorConfig = new MonitorConfig([
      "address" => $webSocketMonitorUrl
    ]);
    $this->presenter->monitorConfig = $monitorConfig;

    $request = new Nette\Application\Request('V1:Submit', 'POST',
      ['action' => 'submit', 'id' => $assignment->getId()],
      ['note' => 'someNiceNoteAboutThisCrazySubmit', 'files' => $files]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(2, $result['payload']);

    $submission = $result['payload']['submission'];
    Assert::type('array', $submission);
    Assert::equal($assignment->getId(), $submission['exerciseAssignmentId']);

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
    $ext = current($assignment->getRuntimeEnvironments()->first()->getExtensionsList());

    // save fake files into db
    $file1 = new UploadedFile("file1." . $ext, new \DateTime, 0, $user, "file1." . $ext);
    $file2 = new UploadedFile("file2." . $ext, new \DateTime, 0, $user, "file2." . $ext);
    $this->presenter->files->persist($file1);
    $this->presenter->files->persist($file2);
    $this->presenter->files->flush();
    $files = [ $file1->getId(), $file2->getId() ];

    // prepare return variables for mocked objects
    $jobId = 'jobId';
    $hwGroups = ["group1", "group2"];
    $archiveUrl = "archiveUrl";
    $resultsUrl = "resultsUrl";
    $fileserverUrl = "fileserverUrl";
    $tasksCount = 5;
    $webSocketMonitorUrl = "webSocketMonitorUrl";

    /** @var Mockery\Mock | JobConfig\SubmissionHeader $mockSubmissionHeader */
    $mockSubmissionHeader = Mockery::mock(JobConfig\SubmissionHeader::class);
    $mockSubmissionHeader->shouldReceive("setId")->withArgs([Mockery::any()])->andReturn($mockSubmissionHeader)->once()
      ->shouldReceive("setType")->withArgs([AssignmentSolution::JOB_TYPE])->andReturn($mockSubmissionHeader)->once();

    /** @var Mockery\Mock | JobConfig\JobConfig $mockJobConfig */
    $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
    $mockJobConfig->shouldReceive("getJobId")->withAnyArgs()->andReturn($jobId)->atLeast(1)
      ->shouldReceive("getSubmissionHeader")->withAnyArgs()->andReturn($mockSubmissionHeader)->once()
      ->shouldReceive("getTasksCount")->withAnyArgs()->andReturn($tasksCount)->zeroOrMoreTimes()
      ->shouldReceive("getHardwareGroups")->andReturn($hwGroups)->atLeast(1)
      ->shouldReceive("setFileCollector")->with($fileserverUrl)->once();

    /** @var Mockery\Mock | JobConfig\Generator $mockGenerator */
    $mockGenerator = Mockery::mock(JobConfig\Generator::class);
    $mockGenerator->shouldReceive("generateJobConfig")->withAnyArgs()
      ->andReturn(new GeneratorResult("jobConfigPath", $mockJobConfig))->once();
    $this->presenter->jobConfigGenerator = $mockGenerator;

    // mock fileserver and broker proxies
    $mockFileserverProxy = Mockery::mock(App\Helpers\FileServerProxy::class);
    $mockFileserverProxy->shouldReceive("getFileserverTasksUrl")->andReturn($fileserverUrl)->once()
      ->shouldReceive("sendFiles")->withArgs([$jobId, Mockery::any(), Mockery::any()])
      ->andReturn([$archiveUrl, $resultsUrl])->once();
    $mockBrokerProxy = Mockery::mock(App\Helpers\BrokerProxy::class);
    $mockBrokerProxy->shouldReceive("startEvaluation")->withArgs([$jobId, $hwGroups, Mockery::any(), $archiveUrl, $resultsUrl])
      ->andThrow(SubmissionFailedException::class)->once();
    $this->presenter->submissionHelper = new SubmissionHelper(new BackendSubmitHelper($mockBrokerProxy, $mockFileserverProxy));

    // fake monitor configuration
    $monitorConfig = new MonitorConfig([
      "address" => $webSocketMonitorUrl
    ]);
    $this->presenter->monitorConfig = $monitorConfig;

    $request = new Nette\Application\Request('V1:Submit', 'POST',
      ['action' => 'submit', 'id' => $assignment->getId()],
      ['note' => 'someNiceNoteAboutThisCrazySubmit', 'files' => $files]
    );

    $failureCount = count($this->presenter->submissionFailures->findAll());

    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, SubmissionFailedException::class);

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

    /** @var Mockery\Mock | JobConfig\SubmissionHeader $mockSubmissionHeader */
    $mockSubmissionHeader = Mockery::mock(JobConfig\SubmissionHeader::class);
    $mockSubmissionHeader->shouldReceive("setId")->withArgs([Mockery::any()])->andReturn($mockSubmissionHeader)->once()
      ->shouldReceive("setType")->withArgs([AssignmentSolution::JOB_TYPE])->andReturn($mockSubmissionHeader)->once();

    /** @var Mockery\Mock | JobConfig\JobConfig $mockJobConfig */
    $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
    $mockJobConfig->shouldReceive("getJobId")->withAnyArgs()->andReturn($jobId)->atLeast(1)
      ->shouldReceive("getSubmissionHeader")->withAnyArgs()->andReturn($mockSubmissionHeader)->once()
      ->shouldReceive("getTasksCount")->withAnyArgs()->andReturn($tasksCount)->zeroOrMoreTimes()
      ->shouldReceive("getHardwareGroups")->andReturn($hwGroups)->atLeast(1)
      ->shouldReceive("setFileCollector")->with($fileserverUrl)->once();

    /** @var Mockery\Mock | JobConfig\Generator $mockGenerator */
    $mockGenerator = Mockery::mock(JobConfig\Generator::class);
    $mockGenerator->shouldReceive("generateJobConfig")->withAnyArgs()
      ->andReturn(new GeneratorResult("jobConfigPath", $mockJobConfig))->once();
    $this->presenter->jobConfigGenerator = $mockGenerator;

    // mock fileserver and broker proxies
    /** @var Mockery\Mock | FileServerProxy $mockFileserverProxy */
    $mockFileserverProxy = Mockery::mock(FileServerProxy::class);
    $mockFileserverProxy->shouldReceive("getFileserverTasksUrl")->andReturn($fileserverUrl)->once()
      ->shouldReceive("sendFiles")->withArgs([$jobId, Mockery::any(), Mockery::any()])
      ->andReturn([$archiveUrl, $resultsUrl])->once();
    /** @var Mockery\Mock | BrokerProxy $mockBrokerProxy */
    $mockBrokerProxy = Mockery::mock(BrokerProxy::class);
    $mockBrokerProxy->shouldReceive("startEvaluation")->withArgs([$jobId, $hwGroups, Mockery::any(), $archiveUrl, $resultsUrl])
      ->andReturn($evaluationStarted = true)->once();
    $this->presenter->submissionHelper = new SubmissionHelper(new BackendSubmitHelper($mockBrokerProxy, $mockFileserverProxy));

    // fake monitor configuration
    $monitorConfig = new MonitorConfig([
      "address" => $webSocketMonitorUrl
    ]);
    $this->presenter->monitorConfig = $monitorConfig;

    $request = new Nette\Application\Request('V1:Submit', 'POST',
      ['action' => 'resubmit', 'id' => $solution->getId()],
      ['private' => 0]
    );

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($solutionCount, count($solutions->findAll()));
    Assert::equal($submissionCount, $solution->getSubmissions()->count());
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

    /** @var Mockery\Mock | JobConfig\SubmissionHeader $mockSubmissionHeader */
    $mockSubmissionHeader = Mockery::mock(JobConfig\SubmissionHeader::class);
    $mockSubmissionHeader->shouldReceive("setId")->withArgs([Mockery::any()])->andReturn($mockSubmissionHeader)->times($solutionCount)
      ->shouldReceive("setType")->withArgs([AssignmentSolution::JOB_TYPE])->andReturn($mockSubmissionHeader)->times($solutionCount);

    /** @var Mockery\Mock | JobConfig\JobConfig $mockJobConfig */
    $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
    $mockJobConfig->shouldReceive("getJobId")->withAnyArgs()->andReturn($jobId)->atLeast($solutionCount)
      ->shouldReceive("getSubmissionHeader")->withAnyArgs()->andReturn($mockSubmissionHeader)->times($solutionCount)
      ->shouldReceive("getTasksCount")->withAnyArgs()->andReturn($tasksCount)->atLeast($solutionCount)
      ->shouldReceive("getHardwareGroups")->andReturn($hwGroups)->atLeast($solutionCount)
      ->shouldReceive("setFileCollector")->with($fileserverUrl)->times($solutionCount);

    /** @var Mockery\Mock | JobConfig\Generator $mockGenerator */
    $mockGenerator = Mockery::mock(JobConfig\Generator::class);
    $mockGenerator->shouldReceive("generateJobConfig")->withAnyArgs()
      ->andReturn(new GeneratorResult("jobConfigPath", $mockJobConfig))->times($solutionCount);
    $this->presenter->jobConfigGenerator = $mockGenerator;

    // mock fileserver and broker proxies
    /** @var Mockery\Mock | FileServerProxy $mockFileserverProxy */
    $mockFileserverProxy = Mockery::mock(FileServerProxy::class);
    $mockFileserverProxy->shouldReceive("getFileserverTasksUrl")->andReturn($fileserverUrl)->times($solutionCount)
      ->shouldReceive("sendFiles")->withArgs([$jobId, Mockery::any(), Mockery::any()])
      ->andReturn([$archiveUrl, $resultsUrl])->times($solutionCount);
    /** @var Mockery\Mock | BrokerProxy $mockBrokerProxy */
    $mockBrokerProxy = Mockery::mock(BrokerProxy::class);
    $mockBrokerProxy->shouldReceive("startEvaluation")->withArgs([$jobId, $hwGroups, Mockery::any(), $archiveUrl, $resultsUrl])
      ->andReturn($evaluationStarted = true)->times($solutionCount);
    $this->presenter->submissionHelper = new SubmissionHelper(new BackendSubmitHelper($mockBrokerProxy, $mockFileserverProxy));

    // fake monitor configuration
    $monitorConfig = new MonitorConfig([
      "address" => $webSocketMonitorUrl
    ]);
    $this->presenter->monitorConfig = $monitorConfig;

    $request = new Nette\Application\Request('V1:Submit', 'POST',
      ['action' => 'resubmitAll', 'id' => $assignment->getId()],
      []
    );

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($totalSubmissionCount + $solutionCount, count($this->presenter->assignmentSubmissions->findAll()));
  }

  public function testSubmitOracle()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $user = current($this->presenter->users->findAll());
    $assignment = current($this->assignments->findAll());
    $ext = current($assignment->getRuntimeEnvironments()->first()->getExtensionsList());

    // save fake files into db
    $file1 = new UploadedFile("file1." . $ext, new \DateTime, 0, $user, "file1." . $ext);
    $file2 = new UploadedFile("file2." . $ext, new \DateTime, 0, $user, "file2." . $ext);
    $this->presenter->files->persist($file1);
    $this->presenter->files->persist($file2);
    $this->presenter->files->flush();
    $files = [ $file1->getId(), $file2->getId() ];

    $request = new Nette\Application\Request('V1:Submit', 'GET',
      ['action' => 'submitOracle', 'id' => $assignment->getId(), 'files' => $files]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(2, $result['payload']);
  }

}

(new TestSubmitPresenter())->run();
