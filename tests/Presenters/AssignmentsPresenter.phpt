<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\SubmissionFailedException;
use App\Helpers\BrokerProxy;
use App\Helpers\FileServerProxy;
use App\Helpers\MonitorConfig;
use App\Helpers\SubmissionHelper;
use App\Model\Entity\Assignment;
use App\Model\Repository\Assignments;
use App\Model\Repository\Submissions;
use App\V1Module\Presenters\AssignmentsPresenter;
use Tester\Assert;
use App\Helpers\JobConfig;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\Submission;
use App\Exceptions\NotFoundException;

class TestAssignmentsPresenter extends Tester\TestCase
{
  /** @var AssignmentsPresenter */
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
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->assignments = $container->getByType(App\Model\Repository\Assignments::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);

    $this->presenter = PresenterTestHelper::createPresenter($this->container, AssignmentsPresenter::class);
    $this->presenter->submissionHelper = Mockery::mock(App\Helpers\SubmissionHelper::class);
    $this->presenter->monitorConfig = new App\Helpers\MonitorConfig(['address' => 'localhost']);
  }

  protected function tearDown()
  {
    Mockery::close();

    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testListAssignments()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $request = new Nette\Application\Request('V1:Assignments', 'GET', ['action' => 'default']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($this->presenter->assignments->findAll(), $result['payload']);
  }

  public function testDetail()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $assignments = $this->assignments->findAll();
    $assignment = array_pop($assignments);

    $request = new Nette\Application\Request('V1:Assignments', 'GET',
      ['action' => 'detail', 'id' => $assignment->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($assignment, $result['payload']);
  }

  public function testUpdateDetail()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $assignments = $this->assignments->findAll();
    $assignment = array_pop($assignments);

    $name = "newAssignmentName";
    $isPublic = true;
    $localizedTexts = [
      [ "locale" => "locA", "text" => "descA" ]
    ];
    $firstDeadline = (new \DateTime())->getTimestamp();
    $maxPointsBeforeFirstDeadline = 123;
    $submissionsCountLimit = 321;
    $scoreConfig = "scoreConfiguration in yaml";
    $allowSecondDeadline = true;
    $canViewLimitRatios = false;
    $secondDeadline = (new \DateTime)->getTimestamp();
    $maxPointsBeforeSecondDeadline = 543;
    $isBonus = true;
    $pointsPercentualThreshold = 90;

    $request = new Nette\Application\Request('V1:Assignments', 'POST',
      ['action' => 'updateDetail', 'id' => $assignment->getId()],
      [
        'name' => $name,
        'isPublic' => $isPublic,
        'version' => 1,
        'localizedTexts' => $localizedTexts,
        'firstDeadline' => $firstDeadline,
        'maxPointsBeforeFirstDeadline' => $maxPointsBeforeFirstDeadline,
        'submissionsCountLimit' => $submissionsCountLimit,
        'scoreConfig' => $scoreConfig,
        'allowSecondDeadline' => $allowSecondDeadline,
        'canViewLimitRatios' => $canViewLimitRatios,
        'secondDeadline' => $secondDeadline,
        'maxPointsBeforeSecondDeadline' => $maxPointsBeforeSecondDeadline,
        'isBonus' => $isBonus,
        'pointsPercentualThreshold' => $pointsPercentualThreshold
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    // check updated assignment
    $updatedAssignment = $result['payload'];
    Assert::type(\App\Model\Entity\Assignment::class, $updatedAssignment);
    Assert::equal($name, $updatedAssignment->getName());
    Assert::equal($isPublic, $updatedAssignment->getIsPublic());
    Assert::equal($firstDeadline, $updatedAssignment->getFirstDeadline()->getTimestamp());
    Assert::equal($maxPointsBeforeFirstDeadline, $updatedAssignment->getMaxPointsBeforeFirstDeadline());
    Assert::equal($submissionsCountLimit, $updatedAssignment->getSubmissionsCountLimit());
    Assert::equal($scoreConfig, $updatedAssignment->getScoreConfig());
    Assert::equal($allowSecondDeadline, $updatedAssignment->getAllowSecondDeadline());
    Assert::equal($canViewLimitRatios, $updatedAssignment->getCanViewLimitRatios());
    Assert::equal($secondDeadline, $updatedAssignment->getSecondDeadline()->getTimestamp());
    Assert::equal($maxPointsBeforeSecondDeadline, $updatedAssignment->getMaxPointsBeforeSecondDeadline());
    Assert::equal($isBonus, $updatedAssignment->getIsBonus());
    Assert::equal($pointsPercentualThreshold / 100, $updatedAssignment->getPointsPercentualThreshold());

    // check localized texts
    Assert::count(1, $updatedAssignment->getLocalizedTexts());
    $localized = current($localizedTexts);
    $updatedLocalized = $updatedAssignment->getLocalizedTexts()->first();
    Assert::equal($updatedLocalized->getLocale(), $localized["locale"]);
    Assert::equal($updatedLocalized->getText(), $localized["text"]);
  }

  public function testCreateAssignment()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    /** @var Mockery\Mock | JobConfig\TestConfig $mockJobConfig */
    $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);

    $mockJobConfig->shouldReceive("getTests")->withAnyArgs()->andReturn([
      new JobConfig\TestConfig("test1", [
        (new JobConfig\Tasks\Task)->setType('execution')->setSandboxConfig((new JobConfig\SandboxConfig)->setName('isolate')),
        (new JobConfig\Tasks\Task)->setType('evaluation')
      ])
    ]);

    /** @var Mockery\Mock | JobConfig\Storage $mockStorage */
    $mockStorage = Mockery::mock(JobConfig\Storage::class);
    $mockStorage->shouldReceive("getJobConfig")->withAnyArgs()->andReturn($mockJobConfig);
    $this->presenter->jobConfigs = $mockStorage;

    $exercise = $this->presenter->exercises->findAll()[0];
    $group = $this->presenter->groups->findAll()[0];

    $request = new Nette\Application\Request(
      'V1:Assignments',
      'POST',
      ['action' => 'create'],
      ['exerciseId' => $exercise->id, 'groupId' => $group->id]
    );

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    // Make sure the assignment was persisted
    Assert::same($this->presenter->assignments->findOneBy(['id' => $result['payload']->id]), $result['payload']);
  }

  public function testRemove()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $assignment = current($this->assignments->findAll());

    $request = new Nette\Application\Request('V1:Assignments', 'DELETE',
      ['action' => 'remove', 'id' => $assignment->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal("OK", $result['payload']);
    Assert::exception(function () use ($assignment) {
      $this->assignments->findOrThrow($assignment->getId());
    }, NotFoundException::class);
  }

  public function testCanSubmit()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $assignment = current($this->assignments->findAll());

    $request = new Nette\Application\Request('V1:Assignments', 'GET',
      ['action' => 'canSubmit', 'id' => $assignment->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal(true, $result['payload']);
  }

  public function testSubmissions()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $submission = current($this->presenter->submissions->findAll());
    $user = $submission->getUser();
    $assignment = $submission->getAssignment();
    $submissions = $this->presenter->submissions->findSubmissions($assignment, $user->getId());

    $request = new Nette\Application\Request('V1:Assignments', 'GET',
      ['action' => 'submissions', 'id' => $assignment->getId(), 'userId' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(count($submissions), $result['payload']);
    Assert::same($submissions, $result['payload']);
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
    $evaluationStarted = TRUE;
    $webSocketMonitorUrl = "webSocketMonitorUrl";

    /** @var Mockery\Mock | JobConfig\SubmissionHeader $mockSubmissionHeader */
    $mockSubmissionHeader = Mockery::mock(JobConfig\SubmissionHeader::class);
    $mockSubmissionHeader->shouldReceive("setId")->withArgs([Mockery::any()])->andReturn($mockSubmissionHeader)->once()
      ->shouldReceive("setType")->withArgs([Submission::JOB_TYPE])->andReturn($mockSubmissionHeader)->once();

    /** @var Mockery\Mock | JobConfig\JobConfig $mockJobConfig */
    $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
    $mockJobConfig->shouldReceive("getJobId")->withAnyArgs()->andReturn($jobId)->atLeast(1)
      ->shouldReceive("getSubmissionHeader")->withAnyArgs()->andReturn($mockSubmissionHeader)->once()
      ->shouldReceive("getTasksCount")->withAnyArgs()->andReturn($tasksCount)->once()
      ->shouldReceive("getHardwareGroups")->andReturn($hwGroups)->atLeast(1)
      ->shouldReceive("setFileCollector")->with($fileserverUrl)->once();

    /** @var Mockery\Mock | JobConfig\Storage $mockStorage */
    $mockStorage = Mockery::mock(JobConfig\Storage::class);
    $mockStorage->shouldReceive("getJobConfig")->withAnyArgs()->andReturn($mockJobConfig)->once();
    $this->presenter->jobConfigs = $mockStorage;

    // mock fileserver and broker proxies
    $mockFileserverProxy = Mockery::mock(App\Helpers\FileServerProxy::class);
    $mockFileserverProxy->shouldReceive("getFileserverTasksUrl")->andReturn($fileserverUrl)->once()
      ->shouldReceive("sendFiles")->withArgs([$jobId, Mockery::any(), Mockery::any()])
      ->andReturn([$archiveUrl, $resultsUrl])->once();
    $mockBrokerProxy = Mockery::mock(App\Helpers\BrokerProxy::class);
    $mockBrokerProxy->shouldReceive("startEvaluation")->withArgs([$jobId, $hwGroups, Mockery::any(), $archiveUrl, $resultsUrl])
      ->andReturn($evaluationStarted)->once();
    $submissionHelper = new SubmissionHelper($mockBrokerProxy, $mockFileserverProxy);
    $this->presenter->submissionHelper = $submissionHelper;

    // fake monitor configuration
    $monitorConfig = new MonitorConfig([
      "address" => $webSocketMonitorUrl
    ]);
    $this->presenter->monitorConfig = $monitorConfig;

    $request = new Nette\Application\Request('V1:Assignments', 'POST',
      ['action' => 'submit', 'id' => $assignment->getId()],
      ['note' => 'someNiceNoteAboutThisCrazySubmit', 'files' => $files]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(2, $result['payload']);

    $submission = $result['payload']['submission'];
    Assert::type(Submission::class, $submission);
    Assert::equal($assignment->getId(), $submission->getAssignment()->getId());

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
    $runtimeConfig = $assignment->getRuntimeConfigs()->first();
    $ext = current($runtimeConfig->getRuntimeEnvironment()->getExtensionsList());

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
      ->shouldReceive("setType")->withArgs([Submission::JOB_TYPE])->andReturn($mockSubmissionHeader)->once();

    /** @var Mockery\Mock | JobConfig\JobConfig $mockJobConfig */
    $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
    $mockJobConfig->shouldReceive("getJobId")->withAnyArgs()->andReturn($jobId)->atLeast(1)
      ->shouldReceive("getSubmissionHeader")->withAnyArgs()->andReturn($mockSubmissionHeader)->once()
      ->shouldReceive("getTasksCount")->withAnyArgs()->andReturn($tasksCount)->zeroOrMoreTimes()
      ->shouldReceive("getHardwareGroups")->andReturn($hwGroups)->atLeast(1)
      ->shouldReceive("setFileCollector")->with($fileserverUrl)->once();

    /** @var Mockery\Mock | JobConfig\Storage $mockStorage */
    $mockStorage = Mockery::mock(JobConfig\Storage::class);
    $mockStorage->shouldReceive("getJobConfig")->withAnyArgs()->andReturn($mockJobConfig)->once();
    $this->presenter->jobConfigs = $mockStorage;

    // mock fileserver and broker proxies
    $mockFileserverProxy = Mockery::mock(App\Helpers\FileServerProxy::class);
    $mockFileserverProxy->shouldReceive("getFileserverTasksUrl")->andReturn($fileserverUrl)->once()
      ->shouldReceive("sendFiles")->withArgs([$jobId, Mockery::any(), Mockery::any()])
      ->andReturn([$archiveUrl, $resultsUrl])->once();
    $mockBrokerProxy = Mockery::mock(App\Helpers\BrokerProxy::class);
    $mockBrokerProxy->shouldReceive("startEvaluation")->withArgs([$jobId, $hwGroups, Mockery::any(), $archiveUrl, $resultsUrl])
      ->andThrow(SubmissionFailedException::class)->once();
    $submissionHelper = new SubmissionHelper($mockBrokerProxy, $mockFileserverProxy);
    $this->presenter->submissionHelper = $submissionHelper;

    // fake monitor configuration
    $monitorConfig = new MonitorConfig([
      "address" => $webSocketMonitorUrl
    ]);
    $this->presenter->monitorConfig = $monitorConfig;

    $request = new Nette\Application\Request('V1:Assignments', 'POST',
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
    /** @var Submissions $submissions */
    $submissions = $this->container->getByType(Submissions::class);

    $submission = current($submissions->findAll());
    $submissionCount = count($submissions->findAll());
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
    $mockSubmissionHeader->shouldReceive("setId")->withArgs([Mockery::any()])->andReturn($mockSubmissionHeader)->once()
      ->shouldReceive("setType")->withArgs([Submission::JOB_TYPE])->andReturn($mockSubmissionHeader)->once();

    /** @var Mockery\Mock | JobConfig\JobConfig $mockJobConfig */
    $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
    $mockJobConfig->shouldReceive("getJobId")->withAnyArgs()->andReturn($jobId)->atLeast(1)
      ->shouldReceive("getSubmissionHeader")->withAnyArgs()->andReturn($mockSubmissionHeader)->once()
      ->shouldReceive("getTasksCount")->withAnyArgs()->andReturn($tasksCount)->zeroOrMoreTimes()
      ->shouldReceive("getHardwareGroups")->andReturn($hwGroups)->atLeast(1)
      ->shouldReceive("setFileCollector")->with($fileserverUrl)->once();

    /** @var Mockery\Mock | JobConfig\Storage $mockStorage */
    $mockStorage = Mockery::mock(JobConfig\Storage::class);
    $mockStorage->shouldReceive("getJobConfig")->withAnyArgs()->andReturn($mockJobConfig)->once();
    $this->presenter->jobConfigs = $mockStorage;

    // mock fileserver and broker proxies
    /** @var Mockery\Mock | FileServerProxy $mockFileserverProxy */
    $mockFileserverProxy = Mockery::mock(FileServerProxy::class);
    $mockFileserverProxy->shouldReceive("getFileserverTasksUrl")->andReturn($fileserverUrl)->once()
      ->shouldReceive("sendFiles")->withArgs([$jobId, Mockery::any(), Mockery::any()])
      ->andReturn([$archiveUrl, $resultsUrl])->once();
    /** @var Mockery\Mock | BrokerProxy $mockBrokerProxy */
    $mockBrokerProxy = Mockery::mock(BrokerProxy::class);
    $mockBrokerProxy->shouldReceive("startEvaluation")->withArgs([$jobId, $hwGroups, Mockery::any(), $archiveUrl, $resultsUrl])
      ->andReturn($evaluationStarted = TRUE)->once();
    $submissionHelper = new SubmissionHelper($mockBrokerProxy, $mockFileserverProxy);
    $this->presenter->submissionHelper = $submissionHelper;

    // fake monitor configuration
    $monitorConfig = new MonitorConfig([
      "address" => $webSocketMonitorUrl
    ]);
    $this->presenter->monitorConfig = $monitorConfig;

    $request = new Nette\Application\Request('V1:Assignments', 'POST',
      ['action' => 'resubmit', 'id' => $submission->getId()],
      ['private' => 0]
    );

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    $payload = $result["payload"];
    Assert::equal($submissionCount + 1, count($submissions->findAll()));
  }

  public function testResubmitAll()
  {
    /** @var Submissions $submissions */
    $submissions = $this->container->getByType(Submissions::class);

    /** @var Assignments $assignments */
    $assignments = $this->container->getByType(Assignments::class);

    $assignment = NULL;
    $totalSubmissionCount = count($submissions->findAll());
    $submissionCount = 2;

    // Find an assignment with desired amount of submissions
    /** @var Assignment $candidate */
    foreach ($assignments->findAll() as $candidate) {
      if (count($candidate->getSubmissions()) == $submissionCount) {
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
    $mockSubmissionHeader->shouldReceive("setId")->withArgs([Mockery::any()])->andReturn($mockSubmissionHeader)->times($submissionCount)
      ->shouldReceive("setType")->withArgs([Submission::JOB_TYPE])->andReturn($mockSubmissionHeader)->times($submissionCount);

    /** @var Mockery\Mock | JobConfig\JobConfig $mockJobConfig */
    $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
    $mockJobConfig->shouldReceive("getJobId")->withAnyArgs()->andReturn($jobId)->atLeast($submissionCount)
      ->shouldReceive("getSubmissionHeader")->withAnyArgs()->andReturn($mockSubmissionHeader)->times($submissionCount)
      ->shouldReceive("getTasksCount")->withAnyArgs()->andReturn($tasksCount)->atLeast($submissionCount)
      ->shouldReceive("getHardwareGroups")->andReturn($hwGroups)->atLeast($submissionCount)
      ->shouldReceive("setFileCollector")->with($fileserverUrl)->times($submissionCount);

    /** @var Mockery\Mock | JobConfig\Storage $mockStorage */
    $mockStorage = Mockery::mock(JobConfig\Storage::class);
    $mockStorage->shouldReceive("getJobConfig")->withAnyArgs()->andReturn($mockJobConfig)->times($submissionCount);
    $this->presenter->jobConfigs = $mockStorage;

    // mock fileserver and broker proxies
    /** @var Mockery\Mock | FileServerProxy $mockFileserverProxy */
    $mockFileserverProxy = Mockery::mock(FileServerProxy::class);
    $mockFileserverProxy->shouldReceive("getFileserverTasksUrl")->andReturn($fileserverUrl)->times($submissionCount)
      ->shouldReceive("sendFiles")->withArgs([$jobId, Mockery::any(), Mockery::any()])
      ->andReturn([$archiveUrl, $resultsUrl])->times($submissionCount);
    /** @var Mockery\Mock | BrokerProxy $mockBrokerProxy */
    $mockBrokerProxy = Mockery::mock(BrokerProxy::class);
    $mockBrokerProxy->shouldReceive("startEvaluation")->withArgs([$jobId, $hwGroups, Mockery::any(), $archiveUrl, $resultsUrl])
      ->andReturn($evaluationStarted = TRUE)->times($submissionCount);
    $submissionHelper = new SubmissionHelper($mockBrokerProxy, $mockFileserverProxy);
    $this->presenter->submissionHelper = $submissionHelper;

    // fake monitor configuration
    $monitorConfig = new MonitorConfig([
      "address" => $webSocketMonitorUrl
    ]);
    $this->presenter->monitorConfig = $monitorConfig;

    $request = new Nette\Application\Request('V1:Assignments', 'POST',
      ['action' => 'resubmitAll', 'id' => $assignment->getId()],
      []
    );

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    $payload = $result["payload"];
    Assert::equal($totalSubmissionCount + $submissionCount, count($submissions->findAll()));
  }
}

$testCase = new TestAssignmentsPresenter();
$testCase->run();
