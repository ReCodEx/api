<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\PipelineVars;
use App\Helpers\ExerciseConfig\Test;
use App\Helpers\ExercisesConfig;
use App\Model\Entity\ExerciseTest;
use App\Model\Entity\HardwareGroup;
use App\V1Module\Presenters\ExercisesConfigPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;
use App\Helpers\Yaml;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

/**
 * @testCase
 */
class TestExercisesConfigPresenter extends Tester\TestCase
{
    /** @var ExercisesConfigPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
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
        $this->em = PresenterTestHelper::getEntityManager($container);
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
            $this->user->logout(true);
        }
    }

    public function testGetEnvironmentConfigs()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $exercise = current($this->presenter->exercises->findAll());
        $environments = $exercise->getRuntimeEnvironmentsIds();

        $request = new Nette\Application\Request(
            'V1:ExercisesConfig',
            'GET',
            ['action' => 'getEnvironmentConfigs', 'id' => $exercise->getId()]
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

        $testEnvironmentConfigs = [
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
        ];

        $request = new Nette\Application\Request(
            'V1:ExercisesConfig',
            'POST',
            ['action' => 'updateEnvironmentConfigs', 'id' => $exercise->getId()],
            ['environmentConfigs' => $testEnvironmentConfigs]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal($testEnvironmentConfigs, $result['payload']);

        // check runtime environments directly on exercise
        $exercise = $this->exercises->findOrThrow($exercise->getId());
        Assert::count(1, $exercise->getRuntimeEnvironments());
        Assert::equal($environment->getId(), $exercise->getRuntimeEnvironments()->first()->getId());

        $updatedEnvironmentConfigs = $exercise->getExerciseEnvironmentConfigs();
        Assert::count(1, $updatedEnvironmentConfigs);

        $environmentConfig = $updatedEnvironmentConfigs->first();
        Assert::equal($environment->getId(), $environmentConfig->getRuntimeEnvironment()->getId());

        // check if environment was added to exercise config
        $exerciseConfig = $this->presenter->exerciseConfigLoader->loadExerciseConfig(
            $exercise->getExerciseConfig()->getParsedConfig()
        );
        Assert::contains($environment->getId(), $exerciseConfig->getEnvironments());
        Assert::count(1, $exerciseConfig->getEnvironments());
    }

    public function testGetConfiguration()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $exercise = current($this->exercises->findAll());
        $exerciseConfig = $this->presenter->exerciseConfigLoader->loadExerciseConfig(
            $exercise->getExerciseConfig()->getParsedConfig()
        );

        $request = new Nette\Application\Request(
            'V1:ExercisesConfig',
            'GET',
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
            Assert::contains($environment['name'], ["default", "java", "c-gcc-linux"]);
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
        $compilationPipeline = $this->pipelines->findOrThrow("2341b599-c388-4357-8fea-be1e3bb182e0");
        $testPipeline = $this->pipelines->findOrThrow("9a511efd-fd36-43ce-aa45-e2721845ae3b");

        // prepare config array
        $config = [
            [
                "name" => "c-gcc-linux",
                "tests" => [
                    [
                        "name" => "1",
                        "pipelines" => [
                            [
                                "name" => $compilationPipeline->getId(),
                                "variables" => []
                            ]
                        ]
                    ],
                    [
                        "name" => "2",
                        "pipelines" => [
                            [
                                "name" => $testPipeline->getId(),
                                "variables" => [
                                    ["name" => "input-files", "type" => "file[]", "value" => ["defValB"]],
                                    ["name" => "binary-file", "type" => "file", "value" => "defValB"],
                                    ["name" => "expected-output", "type" => "file", "value" => "BValB"]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                "name" => "java",
                "tests" => [
                    [
                        "name" => "1",
                        "pipelines" => [
                            [
                                "name" => $compilationPipeline->getId(),
                                "variables" => []
                            ]
                        ]
                    ],
                    [
                        "name" => "2",
                        "pipelines" => [
                            [
                                "name" => $testPipeline->getId(),
                                "variables" => [
                                    ["name" => "input-files", "type" => "file[]", "value" => ["defValB"]],
                                    ["name" => "binary-file", "type" => "file", "value" => "defValB"],
                                    ["name" => "expected-output", "type" => "file", "value" => "BValC"]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $request = new Nette\Application\Request(
            'V1:ExercisesConfig',
            'POST',
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

        $exerciseConfig = $this->presenter->exerciseConfigLoader->loadExerciseConfig(
            $exercise->getExerciseConfig()->getParsedConfig()
        );
        Assert::count(2, $exerciseConfig->getTests());
        Assert::type(Test::class, $exerciseConfig->getTest('1'));
        Assert::type(Test::class, $exerciseConfig->getTest('2'));
        Assert::type(
            PipelineVars::class,
            $exerciseConfig->getTest('1')
                ->getEnvironment("c-gcc-linux")->getPipeline($compilationPipeline->getId())
        );
        Assert::type(
            PipelineVars::class,
            $exerciseConfig->getTest('2')
                ->getEnvironment("c-gcc-linux")->getPipeline($testPipeline->getId())
        );
        Assert::equal(
            [],
            $exerciseConfig->getTest('1')->getEnvironment("c-gcc-linux")
                ->getPipeline($compilationPipeline->getId())->getVariablesTable()->toArray()
        );
        Assert::equal(
            "defValB",
            $exerciseConfig->getTest('2')->getEnvironment("c-gcc-linux")
                ->getPipeline($testPipeline->getId())->getVariablesTable()->get('binary-file')->getValue()
        );
    }

    public function testGetVariablesForExerciseConfig()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $exercise = current($this->exercises->findAll());
        $environment = $exercise->getRuntimeEnvironments()->first();
        $request = new Nette\Application\Request(
            'V1:ExercisesConfig',
            'POST',
            [
                'action' => 'getVariablesForExerciseConfig',
                'id' => $exercise->getId()
            ],
            [
                'runtimeEnvironmentId' => $environment->getId(),
                'pipelinesIds' => ['2341b599-c388-4357-8fea-be1e3bb182e0', '9a511efd-fd36-43ce-aa45-e2721845ae3b']
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(2, $result['payload']);

        $payload = $result['payload'];
        Assert::equal("2341b599-c388-4357-8fea-be1e3bb182e0", $payload[0]["id"]);
        Assert::equal("9a511efd-fd36-43ce-aa45-e2721845ae3b", $payload[1]["id"]);
        Assert::count(0, $payload[0]["variables"]);

        $testPipelineVars = $payload[1]["variables"];
        Assert::count(2, $testPipelineVars);
        Assert::equal("input-files", $testPipelineVars[0]->getName());
        Assert::equal("expected-output", $testPipelineVars[1]->getName());
    }

    public function testGetHardwareGroupLimits()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $exercise = current($this->exercises->findAll());
        $exerciseLimits = $exercise->getExerciseLimits()->first();

        $request = new Nette\Application\Request(
            'V1:ExercisesConfig',
            'GET',
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

    public function testSetHardwareGroupLimitsDeprecated()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        // create new hardware group
        $hwGroup = new HardwareGroup(
            "new limits hwgroup",
            "name",
            "desc",
            "memory: 1048576\ncpuTimePerTest: 60\ncpuTimePerExercise: 300\nwallTimePerTest: 60\nwallTimePerExercise: 300"
        );
        $this->presenter->hardwareGroups->persist($hwGroup);

        $exercise = current($this->exercises->findAll());
        $exerciseLimits = $exercise->getExerciseLimits()->first();

        // prepare limits arrays
        $limits = [
            '1' => ['wall-time' => 1.1, 'memory' => 1024],
            '2' => ['wall-time' => 2.2, 'memory' => 1024]
        ];

        $request = new Nette\Application\Request(
            'V1:ExercisesConfig',
            'POST',
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

    public function testSetHardwareGroupLimitsIncorrectDeprecated()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        // create new hardware group
        $hwGroup = new HardwareGroup(
            "new limits hwgroup",
            "name",
            "desc",
            "memory: 1048576\ncpuTimePerTest: 60\ncpuTimePerExercise: 300\nwallTimePerTest: 60\nwallTimePerExercise: 300"
        );
        $this->presenter->hardwareGroups->persist($hwGroup);

        $exercise = current($this->exercises->findAll());
        $exerciseLimits = $exercise->getExerciseLimits()->first();

        // prepare limits arrays
        $limits = [
            '1' => ['wall-time' => 0.0, 'memory' => 1024],
            '2' => ['wall-time' => 0.0, 'memory' => 1024]
        ];

        $request = new Nette\Application\Request(
            'V1:ExercisesConfig',
            'POST',
            [
                'action' => 'setHardwareGroupLimits',
                'id' => $exercise->getId(),
                'runtimeEnvironmentId' => $exerciseLimits->getRuntimeEnvironment()->getId(),
                'hwGroupId' => $hwGroup->getId()
            ],
            ['limits' => $limits]
        );

        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            ExerciseConfigException::class
        );
    }

    public function testSetLimits()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $exercise = current($this->exercises->findAll());
        $exerciseLimits = $exercise->getExerciseLimits()->first();
        $environmentId = $exerciseLimits->getRuntimeEnvironment()->getId();
        $hwGroupId = $exerciseLimits->getHardwareGroup()->getId();

        // prepare limits arrays
        $limits = [
            $hwGroupId => [
                $environmentId => [
                    '1' => ['wall-time' => 1.1, 'memory' => 1024],
                    '2' => ['wall-time' => 2.2, 'memory' => 1024]
                ]
            ]
        ];

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:ExercisesConfig',
            'POST',
            [
                'action' => 'setLimits',
                'id' => $exercise->getId(),
            ],
            ['limits' => $limits]
        );

        $limits = PresenterTestHelper::flattenNestedStructure($limits);
        $payload = PresenterTestHelper::flattenNestedStructure($payload);
        foreach ($limits as $name => $value) {
            Assert::equal($value, $payload[$name]);
        }
    }

    public function testSetLimitsIncorrect()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        // create new hardware group
        $hwGroup = new HardwareGroup(
            "new limits hwgroup",
            "name",
            "desc",
            "memory: 1048576\ncpuTimePerTest: 60\ncpuTimePerExercise: 300\nwallTimePerTest: 60\nwallTimePerExercise: 300"
        );
        $this->presenter->hardwareGroups->persist($hwGroup);

        $exercise = current($this->exercises->findAll());
        $exercise->addHardwareGroup($hwGroup);
        $this->exercises->flush();

        $exerciseLimits = $exercise->getExerciseLimits()->first();
        $environmentId = $exerciseLimits->getRuntimeEnvironment()->getId();
        $hwGroupId = $hwGroup->getId();

        // prepare limits arrays
        $limits = [
            $hwGroupId => [
                $environmentId => [
                    '1' => ['wall-time' => 0.0, 'memory' => 1024],
                    '2' => ['wall-time' => 0.0, 'memory' => 1024]
                ]
            ]
        ];

        Assert::exception(
            function () use ($exercise, $limits) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:ExercisesConfig',
                    'POST',
                    [
                        'action' => 'setLimits',
                        'id' => $exercise->getId(),
                    ],
                    ['limits' => $limits]
                );
            },
            ExerciseConfigException::class
        );
    }

    public function testRemoveHardwareGroupLimits()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $exercise = current($this->exercises->findAll());
        $exerciseLimits = $exercise->getExerciseLimits()->first();
        $environment = $exerciseLimits->getRuntimeEnvironment();
        $hwGroup = $exerciseLimits->getHardwareGroup();

        $request = new Nette\Application\Request(
            'V1:ExercisesConfig',
            'DELETE',
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

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:ExercisesConfig',
            'GET',
            ['action' => 'getScoreConfig', 'id' => $exercise->getId()]
        );
        $resultConfig = $payload->getConfigParsed();
        Assert::equal(['testWeights' => ['Test 1' => 100, 'Test 2' => 100]], $resultConfig);
    }

    public function testSetScoreConfig()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $exercise = current($this->exercises->findAll());

        // prepare score config
        $calculator = 'weighted';
        $config = ['testWeights' => [
            'Test 1' => 100,
            'Test 2' => 100,
            'Test 3' => 100,
        ]];

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:ExercisesConfig',
            'POST',
            [
                'action' => 'setScoreConfig',
                'id' => $exercise->getId()
            ],
            [
                'scoreCalculator' => $calculator,
                'scoreConfig' => $config,
            ]
        );

        Assert::equal($calculator, $payload->getCalculator());
        $resultConfig = $payload->getConfigParsed();
        Assert::equal($config, $resultConfig);
    }

    public function testGetTests()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $exercise = current($this->exercises->findAll());

        $request = new Nette\Application\Request(
            'V1:ExercisesConfig',
            'GET',
            [
                'action' => 'getTests',
                'id' => $exercise->getId()
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $payload = $result['payload'];
        Assert::count(2, $payload);

        $tests = array_map(
            function (ExerciseTest $test) {
                return $test->getName();
            },
            $payload
        );
        Assert::true(array_search("Test 1", $tests) !== false);
        Assert::true(array_search("Test 2", $tests) !== false);
    }

    public function testSetTests()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $exercise = current($this->exercises->findAll());

        // prepare tests
        $tests = [
            [
                "id" => 1,
                "name" => "Test 1",
                "description" => "desc"
            ],
            [
                "id" => 2,
                "name" => "Test 2",
                "description" => "second desc"
            ],
            [
                "name" => "Test 3",
            ]
        ];

        $request = new Nette\Application\Request(
            'V1:ExercisesConfig',
            'POST',
            [
                'action' => 'setTests',
                'id' => $exercise->getId()
            ],
            ['tests' => $tests]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $payload = $result['payload'];
        Assert::count(3, $payload);
        Assert::equal("Test 1", $payload[0]->getName());
        Assert::equal("desc", $payload[0]->getDescription());
        Assert::equal("Test 2", $payload[1]->getName());
        Assert::equal("second desc", $payload[1]->getDescription());
        Assert::equal("Test 3", $payload[2]->getName());
        Assert::equal("", $payload[2]->getDescription());
    }

    public function setTestsTooManyTests()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $exercise = current($this->exercises->findAll());

        $restrictions = new ExercisesConfig(
            [
                "testLimit" => 10
            ]
        );

        $this->presenter->exerciseRestrictionsConfig = $restrictions;

        // prepare tests
        $tests = [];
        for ($i = 1; $i <= 20; $i++) {
            $tests[] = [
                "id" => $i,
                "name" => "Test $i",
                "description" => "desc"
            ];
        }

        $request = new Nette\Application\Request(
            'V1:ExercisesConfig',
            'POST',
            [
                'action' => 'setTests',
                'id' => $exercise->getId()
            ],
            ['tests' => $tests]
        );

        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            App\Exceptions\InvalidApiArgumentException::class
        );
    }
}

$testCase = new TestExercisesConfigPresenter();
$testCase->run();
