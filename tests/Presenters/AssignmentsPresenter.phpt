<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\V1Module\Presenters\AssignmentsPresenter;
use Tester\Assert;
use App\Helpers\JobConfig;

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
    PresenterTestHelper::setToken($this->presenter, $token);

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
    PresenterTestHelper::setToken($this->presenter, $token);

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
    PresenterTestHelper::setToken($this->presenter, $token);

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
    PresenterTestHelper::setToken($this->presenter, $token);

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

  public function testGetDefaultScoreConfig()
  {
    // TODO:
  }

  public function testRemove()
  {
    // TODO:
  }

  public function testCanSubmit()
  {
    // TODO:
  }

  public function testSubmissions()
  {
    // TODO:
  }

  public function testSubmit()
  {
    // TODO:
  }

  public function testGetLimits()
  {
    // TODO:
  }

  public function testSetLimits()
  {
    // TODO:
  }
}

$testCase = new TestAssignmentsPresenter();
$testCase->run();