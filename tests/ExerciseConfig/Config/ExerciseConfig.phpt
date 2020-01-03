<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\PipelineVars;
use App\Helpers\ExerciseConfig\Test;
use App\Helpers\ExerciseConfig\VariableFactory;
use App\Helpers\Yaml;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

class TestExerciseConfig extends Tester\TestCase
{
    static $config = [
        "environments" => ["envA", "envB"],
        "tests" => [
            "testA" => [
                "environments" => [
                    "envA" => [
                        "pipelines" => [
                            [
                                "name" => "hello",
                                "variables" => [
                                    ["name" => "world", "type" => "string", "value" => "hello"]
                                ]
                            ]
                        ]
                    ],
                    "envB" => [
                        "pipelines" => [
                            [
                                "name" => "hello",
                                "variables" => [
                                    ["name" => "world", "type" => "string", "value" => "hello"]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "testB" => [
                "environments" => [
                    "envA" => [
                        "pipelines" => [
                            [
                                "name" => "world",
                                "variables" => [
                                    ["name" => "hello", "type" => "string", "value" => "world"]
                                ]
                            ]
                        ]
                    ],
                    "envB" => [
                        "pipelines" => [
                            [
                                "name" => "world",
                                "variables" => [
                                    ["name" => "hello", "type" => "string", "value" => "world"]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];

    /** @var Loader */
    private $loader;

    public function __construct()
    {
        $this->loader = new Loader(new BoxService());
    }

    public function testSerialization()
    {
        $deserialized = Yaml::parse((string)$this->loader->loadExerciseConfig(self::$config));
        Assert::equal(self::$config, $deserialized);
    }

    public function testIncorrectData()
    {
        Assert::exception(
            function () {
                $this->loader->loadExerciseConfig(null);
            },
            ExerciseConfigException::class
        );

        Assert::exception(
            function () {
                $this->loader->loadExerciseConfig("hello");
            },
            ExerciseConfigException::class
        );
    }

    public function testMissingTestBody()
    {
        Assert::exception(
            function () {
                $this->loader->loadExerciseConfig(["testA" => "testABody"]);
            },
            ExerciseConfigException::class
        );
    }

    public function testTestsOperations()
    {
        $conf = new ExerciseConfig();
        $test = new Test();

        $conf->addTest("testA", $test);
        Assert::count(1, $conf->getTests());

        $conf->removeTest("non-existant");
        Assert::count(1, $conf->getTests());

        $conf->removeTest("testA");
        Assert::count(0, $conf->getTests());
    }

    public function testEnvironmentsOperations()
    {
        $conf = new ExerciseConfig();

        $conf->addEnvironment("newEnvironment");
        Assert::count(1, $conf->getEnvironments());

        $conf->removeEnvironment("non-existant");
        Assert::count(1, $conf->getEnvironments());

        $conf->removeEnvironment("newEnvironment");
        Assert::count(0, $conf->getEnvironments());
    }

    public function testCorrect()
    {
        $conf = $this->loader->loadExerciseConfig(self::$config);
        Assert::count(2, $conf->getTests());

        Assert::type(Test::class, $conf->getTest("testA"));
        Assert::type(Test::class, $conf->getTest("testB"));

        Assert::type(PipelineVars::class, $conf->getTest("testA")->getEnvironment("envA")->getPipeline("hello"));
        Assert::type(PipelineVars::class, $conf->getTest("testA")->getEnvironment("envB")->getPipeline("hello"));
        Assert::type(PipelineVars::class, $conf->getTest("testB")->getEnvironment("envA")->getPipeline("world"));
        Assert::type(PipelineVars::class, $conf->getTest("testB")->getEnvironment("envB")->getPipeline("world"));
    }

}

# Testing methods run
$testCase = new TestExerciseConfig();
$testCase->run();
