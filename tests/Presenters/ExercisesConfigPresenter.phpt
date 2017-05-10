<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\V1Module\Presenters\ExercisesConfigPresenter;
use Tester\Assert;

class TestExercisesConfigPresenter extends Tester\TestCase
{
  /** @var ExercisesConfigPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var  Nette\DI\Container */
  protected $container;

  /** @var Nette\Security\User */
  private $user;

  /** @var App\Model\Repository\Exercises */
  protected $exercises;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->runtimeEnvironments = $container->getByType(\App\Model\Repository\RuntimeEnvironments::class);
    $this->exercises = $container->getByType(App\Model\Repository\Exercises::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);

    $this->presenter = PresenterTestHelper::createPresenter($this->container, ExercisesConfigPresenter::class);
  }

  protected function tearDown()
  {
    Mockery::close();

    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testGetLimits()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->exercises->findAll());

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

    $request = new Nette\Application\Request('V1:ExercisesConfig', 'GET',
      ['action' => 'getLimits', 'id' => $exercise->getId()]
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

    $exercise = current($this->exercises->findAll());
    $setLimitsCallCount = count($exercise->getRuntimeConfigs());

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
    $mockJobConfig->shouldReceive("setLimits")->withArgs(['group1', $limit1])->andReturn()->times($setLimitsCallCount);
    $mockJobConfig->shouldReceive("setLimits")->withArgs(['group2', $limit2])->andReturn()->times($setLimitsCallCount);

    /** @var Mockery\Mock | JobConfig\Storage $mockStorage */
    $mockStorage = Mockery::mock(JobConfig\Storage::class);
    $mockStorage->shouldReceive("getJobConfig")->withAnyArgs()->andReturn($mockJobConfig)->times($setLimitsCallCount);
    $mockStorage->shouldReceive("saveJobConfig")->withAnyArgs()->andReturn()->times($setLimitsCallCount);
    $this->presenter->jobConfigs = $mockStorage;

    // construct post parameter environments
    $environments = [];
    foreach ($exercise->getRuntimeConfigs() as $runtimeConfig) {
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

    $request = new Nette\Application\Request('V1:ExercisesConfig', 'POST',
      ['action' => 'setLimits', 'id' => $exercise->getId()],
      ['environments' => $environments]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\ForwardResponse::class, $response);

    // result of setLimits is forward response which is set to getLimits action
    $req = $response->getRequest();
    Assert::equal(Nette\Application\Request::FORWARD, $req->getMethod());
  }
}

$testCase = new TestExercisesConfigPresenter();
$testCase->run();
