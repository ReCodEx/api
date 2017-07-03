<?php

include '../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\VariableFactory;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Transformer;


class TestExerciseConfigTransformer extends Tester\TestCase
{
  static $exerciseConfig = [
    "environments" => [ "envA", "envB" ],
    "tests" => [
      "testA" => [
        "pipelines" => [
          "hello" => [ "variables" => [ "world" => [ "type" => "string", "value" => "hello" ] ] ]
        ],
        "environments" => [
          "envA" => [ "pipelines" => [ "envPipeline" => [ "variables" => [] ] ] ],
          "envB" => [ "pipelines" => [ "hello" => [ "variables" => [ "varA" => [ "type" => "string", "value" => "valA" ] ] ] ] ]
        ]
      ],
      "testB" => [
        "pipelines" => [
          "world" => [ "variables" => [ "hello" => [ "type" => "string", "value" => "world" ] ] ]
        ],
        "environments" => [
          "envA" => [ "pipelines" => [] ],
          "envB" => [ "pipelines" => [] ]
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
    $this->loader = new Loader(new VariableFactory());
    $this->transformer = new Transformer($this->loader);
  }


  protected function setUp() {
    self::$externalConfig = [
      [
        "name" => "default",
        "tests" => [
          [ "name" => "testA", "pipelines" => [ [
            "name" => "hello", "variables" => [
              [ "name" => "world", "type" => "string", "value" => "hello" ]
            ]
          ] ] ],
          [ "name" => "testB", "pipelines" => [ [
            "name" => "world", "variables" => [
              [ "name" => "hello", "type" => "string", "value" => "world" ]
            ]
          ] ] ]
        ]
      ],
      [
        "name" => "envA",
        "tests" => [
          [ "name" => "testA", "pipelines" => [ [ "name" => "envPipeline", "variables" => [] ] ] ],
          [ "name" => "testB", "pipelines" => [ [
            "name" => "world" , "variables" => [
              [ "name" => "hello", "type" => "string", "value" => "world" ]
            ]
          ] ] ]
        ]
      ],
      [
        "name" => "envB",
        "tests" => [
          [ "name" => "testA", "pipelines" => [ [
            "name" => "hello" , "variables" => [
              [ "name" => "varA", "type" => "string", "value" => "valA" ]
            ]
          ] ] ],
          [ "name" => "testB", "pipelines" => [ [
            "name" => "world" , "variables" => [
              [ "name" => "hello", "type" => "string", "value" => "world" ]
            ]
          ] ] ]
        ]
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
      unset(self::$externalConfig[0]);
      $this->transformer->toExerciseConfig(self::$externalConfig);
    }, ExerciseConfigException::class);
  }

  public function testToExerciseConfigDefineOnlyDefault() {
    Assert::exception(function () {
      unset(self::$externalConfig[1]);
      unset(self::$externalConfig[2]);
      $this->transformer->toExerciseConfig(self::$externalConfig);
    }, ExerciseConfigException::class);
  }

  public function testToExerciseConfigDifferentTestIds() {
    Assert::exception(function () {
      self::$externalConfig[1]["tests"][0]["name"] = 'testNew';
      $this->transformer->toExerciseConfig(self::$externalConfig);
    }, ExerciseConfigException::class);
  }

  public function testToExerciseConfigDifferentNumberOfTests() {
    Assert::exception(function () {
      self::$externalConfig[1]["tests"][] = self::$externalConfig[1]["tests"][0];
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
