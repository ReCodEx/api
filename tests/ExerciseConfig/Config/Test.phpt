<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Environment;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\PipelineVars;
use App\Helpers\ExerciseConfig\VariableFactory;
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
    $this->loader = new Loader(new BoxService());
  }

  protected function setUp() {
    self::$config = [
      "environments" => [
        "envA" => [
          "pipelines" => [ [
              "name" => "newEnvAPipeline",
              "variables" => [
                [ "name" => "varA", "type" => "string", "value" => "valA" ]
              ]
            ]
          ]
        ],
        "envB" => [
          "pipelines" => []
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

  public function testMissingEnvironments() {
    unset(self::$config[Test::ENVIRONMENTS_KEY]);
    Assert::exception(function () {
      $this->loader->loadTest(self::$config);
    }, ExerciseConfigException::class);
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

    Assert::count(2, $test->getEnvironments());
    Assert::type(Environment::class, $test->getEnvironment("envA"));
    Assert::type(Environment::class, $test->getEnvironment("envB"));

    Assert::equal(self::$config, $test->toArray());
  }

}

# Testing methods run
$testCase = new TestTest;
$testCase->run();
