<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\VariableFactory;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\ExerciseLimits;
use Symfony\Component\Yaml\Yaml;

class TestExerciseLimits extends Tester\TestCase
{
  static $sample = [
    "test-id-1" => [
      "memory" => 123,
      "wall-time" => 456.0
    ]
  ];
  static $parse = [
    "test-id-1" => [
      "memory" => 123,
      "wall-time" => 456
    ],
    "test-id-2" => [
      "memory" => 321,
      "wall-time" => 645
    ]
  ];
  static $optional = [
    "test-id-1" => [
      "wall-time" => 2,
      "memory" => 5,
      "parallel" => 6
    ],
    "test-id-2" => [
      "wall-time" => 3,
      "memory" => 6,
      "parallel" => 7
    ]
  ];

  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader(new BoxService());
  }

  public function testSerialization() {
    $deserialized = Yaml::parse((string) $this->loader->loadExerciseLimits(self::$sample));
    Assert::equal(self::$sample, $deserialized);
  }

  public function testEmptyLimits() {
    Assert::exception(function () {
      $this->loader->loadExerciseLimits(null);
    }, \App\Exceptions\ExerciseConfigException::class);
  }

  public function testParsing() {
    $limits = $this->loader->loadExerciseLimits(self::$parse)->getLimitsArray();
    Assert::count(2, $limits);

    Assert::equal(123, $limits["test-id-1"]->getMemoryLimit());
    Assert::type("int", $limits["test-id-1"]->getMemoryLimit());
    Assert::equal(456.0, $limits["test-id-1"]->getWallTime());
    Assert::type("float", $limits["test-id-1"]->getWallTime());

    Assert::equal(321, $limits["test-id-2"]->getMemoryLimit());
    Assert::type("int", $limits["test-id-2"]->getMemoryLimit());
    Assert::equal(645.0, $limits["test-id-2"]->getWallTime());
    Assert::type("float", $limits["test-id-2"]->getWallTime());
  }

  public function testOptional() {
    $limits = $this->loader->loadExerciseLimits(self::$optional)->getLimitsArray();
    Assert::count(2, $limits);

    Assert::equal(2.0, $limits["test-id-1"]->getWallTime());
    Assert::equal(5, $limits["test-id-1"]->getMemoryLimit());
    Assert::equal(6, $limits["test-id-1"]->getParallel());

    Assert::equal(3.0, $limits["test-id-2"]->getWallTime());
    Assert::equal(6, $limits["test-id-2"]->getMemoryLimit());
    Assert::equal(7, $limits["test-id-2"]->getParallel());
  }

}

# Testing methods run
$testCase = new TestExerciseLimits();
$testCase->run();
