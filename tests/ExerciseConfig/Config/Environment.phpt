<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Environment;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\PipelineVars;
use App\Helpers\ExerciseConfig\VariableFactory;
use Symfony\Component\Yaml\Yaml;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

class TestEnvironment extends Tester\TestCase
{
  static $config = [
    "pipelines" => [
      "hello" => [
        "variables" => [
          [ "name" => "hello", "type" => "string", "value" => "world" ],
          [ "name" => "world", "type" => "string", "value" => "hello" ]
        ]
      ],
      "world" => [
        "variables" => []
      ]
    ],

  ];

  static $pipelines = [
    "pipelines" => [
      "pipelineA" => [
        "variables" => []
      ],
      "pipelineB" => [
        "variables" => []
      ]
    ]
  ];

  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader(new BoxService());
  }

  public function testSerialization() {
    $deserialized = Yaml::parse((string)$this->loader->loadEnvironment(self::$config));
    Assert::equal(self::$config, $deserialized);
  }

  public function testParsingPipelines() {
    $env = $this->loader->loadEnvironment(self::$pipelines);
    Assert::count(2, $env->getPipelines());

    Assert::type(PipelineVars::class, $env->getPipeline("pipelineA"));
    Assert::type(PipelineVars::class, $env->getPipeline("pipelineB"));
  }

  public function testPipelinesOperations() {
    $environment = new Environment();
    $pipeline = new PipelineVars;

    $environment->addPipeline("pipelineA", $pipeline);
    Assert::type(PipelineVars::class, $environment->getPipeline("pipelineA"));

    $environment->removePipeline("non-existant");
    Assert::count(1, $environment->getPipelines());

    $environment->removePipeline("pipelineA");
    Assert::count(0, $environment->getPipelines());
  }

  public function testCorrect() {
    $env = $this->loader->loadEnvironment(self::$config);
    Assert::count(2, $env->getPipelines());
    Assert::equal("hello", $env->getPipeline("hello")->getVariablesTable()->get("world")->getValue());
    Assert::equal("world", $env->getPipeline("hello")->getVariablesTable()->get("hello")->getValue());
    Assert::equal(self::$config, $env->toArray());
  }

}

# Testing methods run
$testCase = new TestEnvironment;
$testCase->run();
