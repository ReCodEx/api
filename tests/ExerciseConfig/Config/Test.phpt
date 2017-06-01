<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Environment;
use Symfony\Component\Yaml\Yaml;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Test;

class TestTest extends Tester\TestCase
{
  static $config;

  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader;
  }

  protected function setUp() {
    self::$config = [
      "pipelines" => [
        "hello",
        "world"
      ],
      "variables" => [
        "hello" => "world",
        "world" => "hello"
      ],
      "environments" => [
        "envA" => [
          "pipelines" => [],
          "variables" => []
        ],
        "envB" => [
          "pipelines" => [],
          "variables" => []
        ]
      ]
    ];
  }

  public function testSerialization() {
    $deserialized = Yaml::parse((string)$this->loader->loadTest(self::$config));
    Assert::equal(self::$config, $deserialized);
  }

  public function testIncorrectData() {
    Assert::exception(function () {
      $this->loader->loadTest(null);
    }, ExerciseConfigException::class);

    Assert::exception(function () {
      $this->loader->loadTest([]);
    }, ExerciseConfigException::class);

    Assert::exception(function () {
      $this->loader->loadTest("hello");
    }, ExerciseConfigException::class);
  }

  public function testMissingPipelines() {
    unset(self::$config[Test::PIPELINES_KEY]);
    Assert::exception(function () {
      $this->loader->loadTest(self::$config);
    }, ExerciseConfigException::class);
  }

  public function testMissingVariables() {
    unset(self::$config[Test::VARIABLES_KEY]);
    Assert::exception(function () {
      $this->loader->loadTest(self::$config);
    }, ExerciseConfigException::class);
  }

  public function testMissingEnvironments() {
    unset(self::$config[Test::ENVIRONMENTS_KEY]);
    Assert::exception(function () {
      $this->loader->loadTest(self::$config);
    }, ExerciseConfigException::class);
  }

  public function testVariablesOperations() {
    $test = new Test;

    $test->addVariable("variableA", "valueA");
    Assert::equal("valueA", $test->getVariableValue("variableA"));

    $test->removeVariable("non-existant");
    Assert::count(1, $test->getVariables());

    $test->removeVariable("variableA");
    Assert::count(0, $test->getVariables());
  }

  public function testEnvironmentsOperations() {
    $test = new Test;
    $environment = new Environment;

    $test->addEnvironment("environmentA", $environment);
    Assert::type(Environment::class, $test->getEnvironment("environmentA"));

    $test->removeEnvironment("non-existant");
    Assert::count(1, $test->getEnvironments());

    $test->removeEnvironment("environmentA");
    Assert::count(0, $test->getEnvironments());
  }

  public function testCorrect() {
    $test = $this->loader->loadTest(self::$config);

    Assert::count(2, $test->getPipelines());
    Assert::equal(self::$config["pipelines"], $test->getPipelines());
    Assert::contains("hello", $test->getPipelines());
    Assert::contains("world", $test->getPipelines());

    Assert::count(2, $test->getVariables());
    Assert::equal(self::$config["variables"], $test->getVariables());
    Assert::equal("world", $test->getVariableValue("hello"));
    Assert::equal("hello", $test->getVariableValue("world"));

    Assert::count(2, $test->getEnvironments());
    Assert::type(Environment::class, $test->getEnvironment("envA"));
    Assert::type(Environment::class, $test->getEnvironment("envB"));

    Assert::equal(self::$config, $test->toArray());
  }

}

# Testing methods run
$testCase = new TestTest;
$testCase->run();
