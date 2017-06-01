<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Environment;
use Symfony\Component\Yaml\Yaml;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

class TestEnvironment extends Tester\TestCase
{
  static $config = [
    "pipelines" => [
      "hello",
      "world"
    ],
    "variables" => [
      "hello" => "world",
      "world" => "hello"
    ]
  ];

  static $pipelines = [
    "pipelines" => [
      "pipelineA",
      "pipelineB"
    ]
  ];

  static $variables = [
    "variables" => [
      "varA" => "valA",
      "varB" => "valB",
      "varC" => "valC"
    ]
  ];

  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader;
  }

  public function testSerialization() {
    $deserialized = Yaml::parse((string)$this->loader->loadEnvironment(self::$config));
    Assert::equal(self::$config, $deserialized);
  }

  public function testParsingPipelines() {
    $env = $this->loader->loadEnvironment(self::$pipelines);
    Assert::count(2, $env->getPipelines());
    Assert::count(0, $env->getVariables());
    Assert::equal(self::$pipelines["pipelines"], $env->getPipelines());

    Assert::contains("pipelineA", $env->getPipelines());
    Assert::contains("pipelineB", $env->getPipelines());
  }

  public function testParsingVariables() {
    $env = $this->loader->loadEnvironment(self::$variables);
    Assert::count(0, $env->getPipelines());
    Assert::count(3, $env->getVariables());
    Assert::equal(self::$variables["variables"], $env->getVariables());

    Assert::equal("valA", $env->getVariableValue("varA"));
    Assert::equal("valB", $env->getVariableValue("varB"));
    Assert::equal("valC", $env->getVariableValue("varC"));
  }

  public function testVariablesOperations() {
    $env = new Environment;

    $env->addVariable("variableA", "valueA");
    Assert::equal("valueA", $env->getVariableValue("variableA"));

    $env->removeVariable("non-existant");
    Assert::count(1, $env->getVariables());

    $env->removeVariable("variableA");
    Assert::count(0, $env->getVariables());
  }

  public function testCorrect() {
    $env = $this->loader->loadEnvironment(self::$config);
    Assert::count(2, $env->getPipelines());
    Assert::count(2, $env->getVariables());
    Assert::equal(self::$config["pipelines"], $env->getPipelines());
    Assert::equal(self::$config["variables"], $env->getVariables());
    Assert::equal(self::$config, $env->toArray());
  }

}

# Testing methods run
$testCase = new TestEnvironment;
$testCase->run();
