<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\V1Module\Presenters\AssignmentsPresenter;
use Tester\Assert;
use App\Helpers\JobConfig;

class TestAssignmentsPresenter extends Tester\TestCase
{
  private $adminLogin = "admin@admin.com";
  private $adminPassword = "admin";

  /** @var AssignmentsPresenter */
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
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
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
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $request = new Nette\Application\Request('V1:Assignments', 'GET', ['action' => 'default']);
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($this->presenter->assignments->findAll(), $result['payload']);
  }

  public function testCreateAssignment()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
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

    $exercise = $this->presenter->exercises->findAll()[0];
    $group = $this->presenter->groups->findAll()[0];

    $request = new Nette\Application\Request(
      'V1:Assignments',
      'POST',
      ['action' => 'create'],
      ['exerciseId' => $exercise->id, 'groupId' => $group->id]
    );

    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    // Make sure the assignment was persisted
    Assert::same($this->presenter->assignments->findOneBy(['id' => $result['payload']->id]), $result['payload']);
  }
}

$testCase = new TestAssignmentsPresenter();
$testCase->run();