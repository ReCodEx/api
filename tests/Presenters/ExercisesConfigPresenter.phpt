<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\ExerciseConfig\PipelineVars;
use App\Helpers\ExerciseConfig\Test;
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
      Assert::count(2, $variablesTable);
      Assert::true(array_key_exists("varA", $variablesTable));
      Assert::true(array_key_exists("varB", $variablesTable));

      // check types of variables and values
      Assert::equal("file", $variablesTable["varA"]["type"]);
      Assert::equal("string", $variablesTable["varB"]["type"]);
      Assert::equal("valA", $variablesTable["varA"]["value"]);
      Assert::equal("valB", $variablesTable["varB"]["value"]);
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
        'runtimeConfigs' => [ [
            'runtimeEnvironmentId' => $environment->getId(),
            'variablesTable' => [
              'varA' => [
                'type' => 'string',
                'value' => 'valA'
              ],
              'varB' => [
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
    Assert::type(App\Model\Entity\Exercise::class, $result['payload']);

    // check runtime environments directly on exercise
    Assert::count(1, $result['payload']->getRuntimeEnvironments());
    Assert::equal($environment->getId(), $result['payload']->getRuntimeEnvironments()->first()->getId());

    $updatedRuntimeConfigs = $result["payload"]->getExerciseEnvironmentConfigs();
    Assert::count(1, $updatedRuntimeConfigs);

    $runtimeConfig = $updatedRuntimeConfigs->first();
    Assert::equal($environment->getId(), $runtimeConfig->getRuntimeEnvironment()->getId());
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
      Assert::contains($environment['name'], [ "default", "java8", "c-gcc-linux" ]);
      Assert::count(count($exerciseConfig->getTests()), $environment['tests']);

      foreach ($environment['tests'] as $test) {
        $testId = $test['name'];
        Assert::notEqual($exerciseConfig->getTest($testId), null);
      }
    }
  }

  public function testSetConfiguration()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->exercises->findAll());

    // prepare config array
    $config = [
      [
        "name" => "default",
        "tests" => [
          ["name" => "testA", "pipelines" => [["name" => "compilationPipeline", "variables" => [["name" => "defVarA", "type" => "string", "value" => "defValA"]]]]],
          ["name" => "testB", "pipelines" => [["name" => "testPipeline", "variables" => [["name" => "defVarB", "type" => "file", "value" => "defValB"]]]]]
        ]
      ],
      [
        "name" => "c-gcc-linux",
        "tests" => [
          ["name" => "testA", "pipelines" => [["name" => "compilationPipeline", "variables" => [["name" => "AVarA", "type" => "string", "value" => "AValA"]]]]],
          ["name" => "testB", "pipelines" => [["name" => "testPipeline", "variables" => [["name" => "AVarB", "type" => "string", "value" => "AValB"]]]]]
        ]
      ],
      [
        "name" => "java8",
        "tests" => [
          ["name" => "testA", "pipelines" => [["name" => "compilationPipeline", "variables" => [["name" => "BVarA", "type" => "string", "value" => "BValA"]]]]],
          ["name" => "testB", "pipelines" => [["name" => "testPipeline", "variables" => [["name" => "BVarB", "type" => "string", "value" => "BValB"]]]]]
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
    Assert::type(PipelineVars::class, $exerciseConfig->getTest('testA')->getPipeline('compilationPipeline'));
    Assert::type(PipelineVars::class, $exerciseConfig->getTest('testB')->getPipeline('testPipeline'));
    Assert::equal("defValA", $exerciseConfig->getTest('testA')->getPipeline('compilationPipeline')->getVariable('defVarA')->getValue());
    Assert::equal("defValB", $exerciseConfig->getTest('testB')->getPipeline('testPipeline')->getVariable('defVarB')->getValue());
  }

  public function testGetLimits()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->exercises->findAll());
    $exerciseLimits = $exercise->getExerciseLimits()->first();

    $request = new Nette\Application\Request('V1:ExercisesConfig', 'GET',
      [
          'action' => 'getLimits',
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

  public function testSetLimits()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->exercises->findAll());
    $exerciseLimits = $exercise->getExerciseLimits()->first();

    // prepare limits arrays
    $limits = [
      'box-id-1' => ['wall-time' => 1.0],
      'box-id-2' => ['wall-time' => 2.0]
    ];

    $request = new Nette\Application\Request('V1:ExercisesConfig', 'POST',
      [
          'action' => 'setLimits',
          'id' => $exercise->getId(),
          'runtimeEnvironmentId' => $exerciseLimits->getRuntimeEnvironment()->getId(),
          'hwGroupId' => $exerciseLimits->getHardwareGroup()->getId()
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
  }
}

$testCase = new TestExercisesConfigPresenter();
$testCase->run();
