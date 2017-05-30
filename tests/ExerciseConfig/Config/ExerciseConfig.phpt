<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\Test;
use Symfony\Component\Yaml\Yaml;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

class TestExerciseConfig extends Tester\TestCase
{
  static $config = [
    "tests" => [
      "testA" => [
          "pipelines" => [
            "hello",
          ],
          "variables" => [
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
      ],
      "testB" => [
        "pipelines" => [
          "world"
        ],
        "variables" => [
          "hello" => "world",
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
      ]
    ]
  ];

  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader;
  }

  public function testSerialization() {
    $deserialized = Yaml::parse((string)$this->loader->loadExerciseConfig(self::$config));
    Assert::equal(self::$config, $deserialized);
  }

  public function testIncorrectData() {
    Assert::exception(function () {
      $this->loader->loadExerciseConfig(null);
    }, ExerciseConfigException::class);

    Assert::exception(function () {
      $this->loader->loadExerciseConfig("hello");
    }, ExerciseConfigException::class);
  }

  public function testMissingTestBody() {
    Assert::exception(function () {
      $this->loader->loadExerciseConfig(["testA" => "testABody"]);
    }, ExerciseConfigException::class);
  }

  public function testTestsOperations() {
    $conf = new ExerciseConfig;
    $test = new Test;

    $conf->addTest("testA", $test);
    Assert::count(1, $conf->getTests());

    $conf->removeTest("non-existant");
    Assert::count(1, $conf->getTests());

    $conf->removeTest("testA");
    Assert::count(0, $conf->getTests());
  }

  public function testCorrect() {
    $conf = $this->loader->loadExerciseConfig(self::$config);
    Assert::count(2, $conf->getTests());

    Assert::type(Test::class, $conf->getTest("testA"));
    Assert::type(Test::class, $conf->getTest("testB"));

    Assert::equal(["hello"], $conf->getTest("testA")->getPipelines());
    Assert::equal(["world"], $conf->getTest("testB")->getPipelines());
  }

}

# Testing methods run
$testCase = new TestExerciseConfig;
$testCase->run();
