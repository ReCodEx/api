<?php

include '../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Loader;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Transformer;


class TestExerciseConfigTransformer extends Tester\TestCase
{
  static $exerciseConfig = [
    "tests" => [
      "testA" => [
        "pipelines" => [ "hello" ],
        "variables" => [ "world" => "hello" ],
        "environments" => [
          "envA" => [ "pipelines" => [ "envPipeline" ], "variables" => [] ],
          "envB" => [ "pipelines" => [], "variables" => [ "varA" => "valA" ] ]
        ]
      ],
      "testB" => [
        "pipelines" => [ "world" ],
        "variables" => [ "hello" => "world" ],
        "environments" => [
          "envA" => [ "pipelines" => [], "variables" => [] ],
          "envB" => [ "pipelines" => [], "variables" => [] ]
        ]
      ]
    ]
  ];

  static $externalConfig;


  /** @var Loader */
  private $loader;

  /** @var Transformer */
  private $transformer;

  public function __construct() {
    $this->loader = new Loader;
    $this->transformer = new Transformer($this->loader);
  }


  protected function setUp() {
    self::$externalConfig = [
      "default" => [
        "testA" => [ "pipelines" => [ "hello" ], "variables" => [ "world" => "hello" ] ],
        "testB" => [ "pipelines" => [ "world" ], "variables" => [ "hello" => "world" ] ]
      ],
      "envA" => [
        "testA" => [ "pipelines" => [ "envPipeline" ], "variables" => [ "world" => "hello" ] ],
        "testB" => [ "pipelines" => [ "world" ], "variables" => [ "hello" => "world" ] ]
      ],
      "envB" => [
        "testA" => [ "pipelines" => [ "hello" ], "variables" => [ "varA" => "valA" ] ],
        "testB" => [ "pipelines" => [ "world" ], "variables" => [ "hello" => "world" ] ]
      ]
    ];
  }

  public function testFromExerciseConfigCorrect() {
    $parsedConfig = $this->loader->loadExerciseConfig(self::$exerciseConfig);
    $transformed = $this->transformer->fromExerciseConfig($parsedConfig);
    Assert::equal(self::$externalConfig, $transformed);
  }

  public function testToExerciseConfigMissingDefaultSection() {
    Assert::exception(function () {
      unset(self::$externalConfig["default"]);
      $this->transformer->toExerciseConfig(self::$externalConfig);
    }, ExerciseConfigException::class);
  }

  public function testToExerciseConfigDefineOnlyDefault() {
    Assert::exception(function () {
      unset(self::$externalConfig["envA"]);
      unset(self::$externalConfig["envB"]);
      $this->transformer->toExerciseConfig(self::$externalConfig);
    }, ExerciseConfigException::class);
  }

  public function testToExerciseConfigDifferentTestIds() {
    Assert::exception(function () {
      self::$externalConfig["envA"]["testNew"] = self::$externalConfig["envA"]["testA"];
      unset(self::$externalConfig["envA"]["testA"]);
      $this->transformer->toExerciseConfig(self::$externalConfig);
    }, ExerciseConfigException::class);
  }

  public function testToExerciseConfigDifferentNumberOfTests() {
    Assert::exception(function () {
      self::$externalConfig["envA"]["testNew"] = self::$externalConfig["envA"]["testA"];
      $this->transformer->toExerciseConfig(self::$externalConfig);
    }, ExerciseConfigException::class);

    Assert::exception(function () {
      unset(self::$externalConfig["envA"]["testA"]);
      $this->transformer->toExerciseConfig(self::$externalConfig);
    }, ExerciseConfigException::class);
  }

  public function testToExerciseConfigCorrect() {
    $transformed = $this->transformer->toExerciseConfig(self::$externalConfig);
    Assert::equal(self::$exerciseConfig, $transformed->toArray());
  }

}

# Testing methods run
$testCase = new TestExerciseConfigTransformer();
$testCase->run();
