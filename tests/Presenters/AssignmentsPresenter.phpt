<?php
$container = require_once __DIR__ . "/../bootstrap.php";

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
    $localizedAssignments = [
      [ "locale" => "locA", "description" => "descA", "name" => "nameA" ]
    ];
    $firstDeadline = (new \DateTime())->getTimestamp();
    $maxPointsBeforeFirstDeadline = 123;
    $submissionsCountLimit = 321;
    $scoreConfig = "scoreConfiguration in yaml";
    $allowSecondDeadline = true;
    $canViewLimitRatios = false;
    $secondDeadline = (new \DateTime)->getTimestamp();
    $maxPointsBeforeSecondDeadline = 543;

    $request = new Nette\Application\Request('V1:Assignments', 'POST',
      ['action' => 'updateDetail', 'id' => $assignment->getId()],
      [
        'name' => $name,
        'isPublic' => $isPublic,
        'localizedAssignments' => $localizedAssignments,
        'firstDeadline' => $firstDeadline,
        'maxPointsBeforeFirstDeadline' => $maxPointsBeforeFirstDeadline,
        'submissionsCountLimit' => $submissionsCountLimit,
        'scoreConfig' => $scoreConfig,
        'allowSecondDeadline' => $allowSecondDeadline,
        'canViewLimitRatios' => $canViewLimitRatios,
        'secondDeadline' => $secondDeadline,
        'maxPointsBeforeSecondDeadline' => $maxPointsBeforeSecondDeadline
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

    // check localized assignment
    Assert::count(1, $updatedAssignment->getLocalizedAssignments());
    $localized = current($localizedAssignments);
    $updatedLocalized = $updatedAssignment->getLocalizedAssignments()->first();
    Assert::equal($updatedLocalized->getLocale(), $localized["locale"]);
    Assert::equal($updatedLocalized->getDescription(), $localized["description"]);
    Assert::equal($updatedLocalized->getName(), $localized["name"]);
  }

  public function testCreateAssignment()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    /** @var Mockery\Mock | JobConfig\TestConfig $mockJobConfig */
    $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
    $baseTaskData = [
      'task-id' => 'anything',
      'priority' => 42,
      'fatal-failure' => false,
      'cmd' => ['bin' => 'echo'],
    ];

    $mockJobConfig->shouldReceive("getTests")->withAnyArgs()->andReturn([
      new JobConfig\TestConfig("test1", [
        new JobConfig\Tasks\ExternalTask($baseTaskData + [
          'type' => 'execution',
          'sandbox' => ['name' => 'isolate', 'limits' => []]
        ]),
        new JobConfig\Tasks\InternalTask($baseTaskData + [
          'type' => 'evaluation'
        ])
      ])
    ]);

    /** @var Mockery\Mock | JobConfig\Storage $mockStorage */
    $mockStorage = Mockery::mock(JobConfig\Storage::class);
    $mockStorage->shouldReceive("getJobConfig")->withAnyArgs()->andReturn($mockJobConfig);
    $this->presenter->jobConfigs = $mockStorage;

    $mockUploadedStorage = Mockery::mock(\App\Helpers\UploadedJobConfigStorage::class);
    $mockUploadedStorage->shouldReceive("copyToUserAndUpdateRuntimeConfigs")->withAnyArgs()->andReturn();
    $this->presenter->uploadedJobConfigStorage = $mockUploadedStorage;

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
    $runtimeConfig = $assignment->getSolutionRuntimeConfigs()->first();
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
    $evaluationStarted = TRUE;
    $webSocketMonitorUrl = "webSocketMonitorUrl";

    /** @var Mockery\Mock | JobConfig\TestConfig $mockJobConfig */
    $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
    $mockJobConfig->shouldReceive("setJobId")->withArgs([Submission::JOB_TYPE, Mockery::any()])->andReturn()->once()
      ->shouldReceive("getJobId")->withAnyArgs()->andReturn($jobId)->atLeast(1)
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
    $submissionHelper = new \App\Helpers\SubmissionHelper($mockBrokerProxy, $mockFileserverProxy);
    $this->presenter->submissionHelper = $submissionHelper;

    // mock monitor configuration
    $mockMonitorConfig = Mockery::mock(\App\Helpers\MonitorConfig::class);
    $mockMonitorConfig->shouldReceive("getAddress")->andReturn($webSocketMonitorUrl)->once();
    $this->presenter->monitorConfig = $mockMonitorConfig;

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

  public function testGetLimits()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $assignment = current($this->assignments->findAll());

    /** @var Mockery\Mock | JobConfig\TestConfig $mockJobConfig */
    $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
    $limits = [
      [
        'hardwareGroup' => 'group1',
        'tests' => []
      ]
    ];

    $mockJobConfig->shouldReceive("getHardwareGroups")->withAnyArgs()->andReturn(["group1", "group2"])->atLeast(1)
      ->shouldReceive("getLimits")->withAnyArgs()->andReturn($limits)->atLeast(1);

    /** @var Mockery\Mock | JobConfig\Storage $mockStorage */
    $mockStorage = Mockery::mock(JobConfig\Storage::class);
    $mockStorage->shouldReceive("getJobConfig")->withAnyArgs()->andReturn($mockJobConfig);
    $this->presenter->jobConfigs = $mockStorage;

    $request = new Nette\Application\Request('V1:Assignments', 'GET',
      ['action' => 'getLimits', 'id' => $assignment->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(1, $result['payload']);

    $environments = $result['payload']['environments'];
    Assert::count(1, $environments);

    $environment = current($environments);
    Assert::equal(["group1", "group2"], $environment['hardwareGroups']);
    Assert::equal($limits, $environment['limits']);
  }

  public function testSetLimits()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $assignment = current($this->assignments->findAll());
    $setLimitsCallCount = count($assignment->getSolutionRuntimeConfigs());

    // prepare limits arrays
    $limit1 = [
      'task1' => ['hw-group-id' => 'group1'],
      'task2' => ['hw-group-id' => 'group1']
    ];
    $limit2 = [
      'task1' => ['hw-group-id' => 'group2'],
      'task2' => ['hw-group-id' => 'group2']
    ];

    /** @var Mockery\Mock | JobConfig\TestConfig $mockJobConfig */
    $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
    $mockJobConfig->shouldReceive("setLimits")->withArgs(['group1', $limit1])->andReturn()->times($setLimitsCallCount)
      ->shouldReceive("setLimits")->withArgs(['group2', $limit2])->andReturn()->times($setLimitsCallCount);

    /** @var Mockery\Mock | JobConfig\Storage $mockStorage */
    $mockStorage = Mockery::mock(JobConfig\Storage::class);
    $mockStorage->shouldReceive("getJobConfig")->withAnyArgs()->andReturn($mockJobConfig)->times($setLimitsCallCount);
    $mockStorage->shouldReceive("saveJobConfig")->withAnyArgs()->andReturn()->times($setLimitsCallCount);
    $this->presenter->jobConfigs = $mockStorage;

    // construct post parameter environments
    $environments = [];
    foreach ($assignment->getSolutionRuntimeConfigs() as $runtimeConfig) {
      $environments[] = [
        'environment' => ['id' => $runtimeConfig->getId()],
        'limits' => [
          [
            'hardwareGroup' => 'group1',
            'tests' => ['testA' => $limit1]
          ],
          [
            'hardwareGroup' => 'group2',
            'tests' => ['testB' => $limit2]
          ]
        ]
      ];
    }

    $request = new Nette\Application\Request('V1:Assignments', 'POST',
      ['action' => 'setLimits', 'id' => $assignment->getId()],
      ['environments' => $environments]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\ForwardResponse::class, $response);

    // result of setLimits is forward response which is set to getLimits action
    $req = $response->getRequest();
    Assert::equal(Nette\Application\Request::FORWARD, $req->getMethod());
  }
}

$testCase = new TestAssignmentsPresenter();
$testCase->run();