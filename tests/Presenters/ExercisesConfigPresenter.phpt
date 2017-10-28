<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\ExerciseConfig\PipelineVars;
use App\Helpers\ExerciseConfig\Test;
use App\Model\Entity\HardwareGroup;
use App\V1Module\Presenters\ExercisesConfigPresenter;
use Tester\Assert;

/**
 * @testCase
 */
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

  /** @var App\Model\Repository\Pipelines */
  protected $pipelines;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->exercises = $container->getByType(App\Model\Repository\Exercises::class);
    $this->pipelines = $container->getByType(App\Model\Repository\Pipelines::class);
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

  public function testGetEnvironmentConfigs()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->presenter->exercises->findAll());
    $environments = $exercise->getRuntimeEnvironmentsIds();

    $request = new Nette\Application\Request('V1:ExercisesConfig',
      'GET',
      ['action' => 'getEnvironmentConfigs', 'id' => $exercise->id]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    $payload = $result['payload'];

    // there are two runtime configurations in fixtures
    Assert::count(2, $payload);

    // check runtime environment
    foreach ($payload as $config) {
      Assert::contains($config["runtimeEnvironmentId"], $environments);

      // check variables, again defined in fixtures
      $variablesTable = $config["variablesTable"];
      Assert::count(1, $variablesTable);
      Assert::equal("source-files", current($variablesTable)["name"]);
    }
  }

  public function testUpdateEnvironmentConfigs()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->presenter->exercises->findAll());
    $environment = current($this->presenter->runtimeEnvironments->findAll());

    $request = new Nette\Application\Request('V1:ExercisesConfig',
      'POST',
      ['action' => 'updateEnvironmentConfigs', 'id' => $exercise->id],
      [
        'environmentConfigs' => [
          [
            'runtimeEnvironmentId' => $environment->getId(),
            'variablesTable' => [
              [
                'name' => 'varA',
                'type' => 'string',
                'value' => 'valA'
              ],
              [
                'name' => 'varB',
                'type' => 'file',
                'value' => 'valB'
              ]
            ]
          ]
        ]
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal("OK", $result['payload']);

    // check runtime environments directly on exercise
    $exercise = $this->exercises->findOrThrow($exercise->getId());
    Assert::count(1, $exercise->getRuntimeEnvironments());
    Assert::equal($environment->getId(), $exercise->getRuntimeEnvironments()->first()->getId());

    $updatedEnvironmentConfigs = $exercise->getExerciseEnvironmentConfigs();
    Assert::count(1, $updatedEnvironmentConfigs);

    $environmentConfig = $updatedEnvironmentConfigs->first();
    Assert::equal($environment->getId(), $environmentConfig->getRuntimeEnvironment()->getId());

    // check if environment was added to exercise config
    $exerciseConfig = $this->presenter->exerciseConfigLoader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());
    Assert::contains($environment->getId(), $exerciseConfig->getEnvironments());
    Assert::count(1, $exerciseConfig->getEnvironments());
  }

  public function testGetConfiguration()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->exercises->findAll());
    $exerciseConfig = $this->presenter->exerciseConfigLoader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());

    $request = new Nette\Application\Request('V1:ExercisesConfig', 'GET',
      [
        'action' => 'getConfiguration',
        'id' => $exercise->getId()
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    $payload = $result['payload'];

    // check all environments
    foreach ($payload as $environment) {
      Assert::contains($environment['name'], [ "default", "java", "c-gcc-linux" ]);
      Assert::count(count($exerciseConfig->getTests()), $environment['tests']);

      foreach ($environment['tests'] as $test) {
        $testId = $test['name'];
        Assert::notEqual($exerciseConfig->getTest($testId), null);
      }
    }
  }

  public function testSetConfiguration()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->exercises->findAll());
    $compilationPipeline = $this->pipelines->findOrThrow("compilationPipeline");
    $testPipeline = $this->pipelines->findOrThrow("testPipeline");

    // prepare config array
    $config = [
      [
        "name" => "default",
        "tests" => [
          ["name" => "testA", "pipelines" => [["name" => $compilationPipeline->getId(), "variables" => [
            ["name" => "source-files", "type" => "file[]", "value" => []]
          ]]]],
          ["name" => "testB", "pipelines" => [["name" => $testPipeline->getId(), "variables" => [
            ["name" => "input-file", "type" => "file", "value" => "defValB"],
            ["name" => "binary-file", "type" => "file", "value" => "defValB"],
            ["name" => "expected-output", "type" => "file", "value" => "defValB"]
          ]]]]
        ]
      ],
      [
        "name" => "c-gcc-linux",
        "tests" => [
          ["name" => "testA", "pipelines" => [["name" => $compilationPipeline->getId(), "variables" => [
          ]]]],
          ["name" => "testB", "pipelines" => [["name" => $testPipeline->getId(), "variables" => [
            ["name" => "input-file", "type" => "file", "value" => "defValB"],
            ["name" => "binary-file", "type" => "file", "value" => "defValB"],
            ["name" => "expected-output", "type" => "file", "value" => "BValB"]
          ]]]]
        ]
      ],
      [
        "name" => "java",
        "tests" => [
          ["name" => "testA", "pipelines" => [["name" => $compilationPipeline->getId(), "variables" => [
          ]]]],
          ["name" => "testB", "pipelines" => [["name" => $testPipeline->getId(), "variables" => [
            ["name" => "input-file", "type" => "file", "value" => "defValB"],
            ["name" => "binary-file", "type" => "file", "value" => "defValB"],
            ["name" => "expected-output", "type" => "file", "value" => "BValC"]
          ]]]]
        ]
      ]
    ];

    $request = new Nette\Application\Request('V1:ExercisesConfig', 'POST',
      [
        'action' => 'setConfiguration',
        'id' => $exercise->getId()
      ],
      ['config' => $config]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $exerciseConfig = $this->presenter->exerciseConfigLoader->loadExerciseConfig($exercise->getExerciseConfig()->getParsedConfig());
    Assert::count(2, $exerciseConfig->getTests());
    Assert::type(Test::class, $exerciseConfig->getTest('testA'));
    Assert::type(Test::class, $exerciseConfig->getTest('testB'));
    Assert::type(PipelineVars::class, $exerciseConfig->getTest('testA')->getPipeline($compilationPipeline->getId()));
    Assert::type(PipelineVars::class, $exerciseConfig->getTest('testB')->getPipeline($testPipeline->getId()));
    Assert::equal([], $exerciseConfig->getTest('testA')->getPipeline($compilationPipeline->getId())->getVariablesTable()->get('source-files')->getValue());
    Assert::equal("defValB", $exerciseConfig->getTest('testB')->getPipeline($testPipeline->getId())->getVariablesTable()->get('binary-file')->getValue());
  }

  public function testGetVariablesForExerciseConfig()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->exercises->findAll());
    $environment = $exercise->getRuntimeEnvironments()->first();
    $request = new Nette\Application\Request('V1:ExercisesConfig', 'POST',
      [
        'action' => 'getVariablesForExerciseConfig',
        'id' => $exercise->getId()
      ],
      [
        'runtimeEnvironmentId' => $environment->getId(),
        'pipelinesIds' => ['compilationPipeline', 'testPipeline']
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(2, $result['payload']);

    $payload = $result['payload'];
    Assert::true(array_key_exists("compilationPipeline", $payload));
    Assert::true(array_key_exists("testPipeline", $payload));
    Assert::count(0, $payload["compilationPipeline"]);

    $testPipeline = $payload["testPipeline"];
    Assert::count(2, $testPipeline);
    Assert::equal("input-file", $testPipeline[0]->getName());
    Assert::equal("expected-output", $testPipeline[1]->getName());
  }

  public function testGetLimits()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->exercises->findAll());
    $exerciseLimits = $exercise->getExerciseLimits()->first();

    $request = new Nette\Application\Request('V1:ExercisesConfig', 'GET',
      [
        'action' => 'getLimits',
        'id' => $exercise->getId(),
        'runtimeEnvironmentId' => $exerciseLimits->getRuntimeEnvironment()->getId()
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(1, $result['payload']);

    $structured = $exerciseLimits->getParsedLimits();
    Assert::equal($structured, $result['payload']);
  }

  public function testSetLimits()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->exercises->findAll());
    $exerciseLimits = $exercise->getExerciseLimits()->first();

    // prepare limits arrays
    $limits = [
      'Test 1' => ['wall-time' => 1.0],
      'Test 2' => ['wall-time' => 2.0]
    ];

    $request = new Nette\Application\Request('V1:ExercisesConfig', 'POST',
      [
        'action' => 'setLimits',
        'id' => $exercise->getId(),
        'runtimeEnvironmentId' => $exerciseLimits->getRuntimeEnvironment()->getId()
      ],
      ['limits' => $limits]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(2, $result['payload']);

    $updatedLimits = $result['payload'];
    Assert::same($updatedLimits, $limits);
    Assert::count(count($this->presenter->hardwareGroups->findAll()), $exercise->getHardwareGroups()->getValues());
  }

  public function testGetHardwareGroupLimits()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->exercises->findAll());
    $exerciseLimits = $exercise->getExerciseLimits()->first();

    $request = new Nette\Application\Request('V1:ExercisesConfig', 'GET',
      [
          'action' => 'getHardwareGroupLimits',
          'id' => $exercise->getId(),
          'runtimeEnvironmentId' => $exerciseLimits->getRuntimeEnvironment()->getId(),
          'hwGroupId' => $exerciseLimits->getHardwareGroup()->getId()
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(1, $result['payload']);

    $structured = $exerciseLimits->getParsedLimits();
    Assert::equal($structured, $result['payload']);
  }

  public function testSetHardwareGroupLimits()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    // create new hardware group
    $hwGroup = new HardwareGroup("new limits hwgroup", "desc");
    $this->presenter->hardwareGroups->persist($hwGroup);

    $exercise = current($this->exercises->findAll());
    $exerciseLimits = $exercise->getExerciseLimits()->first();

    // prepare limits arrays
    $limits = [
      'Test 1' => ['wall-time' => 1.0],
      'Test 2' => ['wall-time' => 2.0]
    ];

    $request = new Nette\Application\Request('V1:ExercisesConfig', 'POST',
      [
          'action' => 'setHardwareGroupLimits',
          'id' => $exercise->getId(),
          'runtimeEnvironmentId' => $exerciseLimits->getRuntimeEnvironment()->getId(),
          'hwGroupId' => $hwGroup->getId()
      ],
      ['limits' => $limits]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(2, $result['payload']);

    $updatedLimits = $result['payload'];
    Assert::same($updatedLimits, $limits);

    // check also if hwgroup was properly set
    Assert::true($exercise->getHardwareGroups()->contains($hwGroup));
  }

  public function testRemoveHardwareGroupLimits()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->exercises->findAll());
    $exerciseLimits = $exercise->getExerciseLimits()->first();
    $environment = $exerciseLimits->getRuntimeEnvironment();
    $hwGroup = $exerciseLimits->getHardwareGroup();

    $request = new Nette\Application\Request('V1:ExercisesConfig', 'DELETE',
      [
        'action' => 'removeHardwareGroupLimits',
        'id' => $exercise->getId(),
        'runtimeEnvironmentId' => $environment->getId(),
        'hwGroupId' => $hwGroup->getId()
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal("OK", $result['payload']);

    // check if limits and hwgroup was properly unset
    Assert::equal(null, $exercise->getLimitsByEnvironmentAndHwGroup($environment, $hwGroup));
    Assert::false($exercise->getHardwareGroups()->contains($hwGroup));
  }

  public function testGetScoreConfig()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $exercise = current($this->exercises->findAll());
    $exercise->setScoreConfig(""); // reset score config and let api generate new one

    $request = new Nette\Application\Request('V1:ExercisesConfig', 'GET',
      [
        'action' => 'getScoreConfig',
        'id' => $exercise->getId()
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $payload = $result['payload'];
    Assert::equal("testWeights:\n    'Test 1': 100\n    'Test 2': 100\n", $payload);
  }

  public function testSetScoreConfig()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $exercise = current($this->exercises->findAll());

    // prepare score config
    $config = "testWeights:\n  \"Test 1\": 100\n  \"Test 2\": 100\n  \"Test 3\": 100";

    $request = new Nette\Application\Request('V1:ExercisesConfig', 'POST',
      [
        'action' => 'setScoreConfig',
        'id' => $exercise->getId()
      ],
      ['scoreConfig' => $config]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $payload = $result['payload'];
    Assert::equal("testWeights:\n  \"Test 1\": 100\n  \"Test 2\": 100\n  \"Test 3\": 100", $payload);
  }

}

$testCase = new TestExercisesConfigPresenter();
$testCase->run();
