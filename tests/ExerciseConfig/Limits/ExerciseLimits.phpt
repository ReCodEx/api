<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\VariableFactory;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\ExerciseLimits;
use Symfony\Component\Yaml\Yaml;

class TestExerciseLimits extends Tester\TestCase
{
  static $sample = [
    "box-id-1" => [
      "memory" => 123,
      "wall-time" => 456.0
    ]
  ];
  static $parse = [
    "box-id-1" => [
      "hw-group-id" => "A",
      "memory" => 123,
      "wall-time" => 456
    ],
    "box-id-2" => [
      "hw-group-id" => "B",
      "memory" => 321,
      "wall-time" => 645
    ]
  ];
  static $optional = [
    "box-id-1" => [
      "wall-time" => 2,
      "memory" => 5,
      "parallel" => 6
    ],
    "box-id-2" => [
      "wall-time" => 3,
      "memory" => 6,
      "parallel" => 7
    ]
  ];

  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader(new VariableFactory());
  }

  public function testSerialization() {
    $deserialized = Yaml::parse((string) $this->loader->loadExerciseLimits(self::$sample));
    Assert::equal(self::$sample, $deserialized);
  }

  public function testEmptyLimits() {
    Assert::exception(function () {
      $this->loader->loadExerciseLimits(NULL);
    }, \App\Exceptions\ExerciseConfigException::class);
  }

  public function testParsing() {
    $limits = $this->loader->loadExerciseLimits(self::$parse)->getLimitsArray();
    Assert::count(2, $limits);

    Assert::equal(123, $limits['box-id-1']->getMemoryLimit());
    Assert::type("int", $limits['box-id-1']->getMemoryLimit());
    Assert::equal(456.0, $limits['box-id-1']->getWallTime());
    Assert::type("float", $limits['box-id-1']->getWallTime());

    Assert::equal(321, $limits['box-id-2']->getMemoryLimit());
    Assert::type("int", $limits['box-id-2']->getMemoryLimit());
    Assert::equal(645.0, $limits['box-id-2']->getWallTime());
    Assert::type("float", $limits['box-id-2']->getWallTime());
  }

  public function testOptional() {
    $limits = $this->loader->loadExerciseLimits(self::$optional)->getLimitsArray();
    Assert::count(2, $limits);

    Assert::equal(2.0, $limits['box-id-1']->getWallTime());
    Assert::equal(5, $limits['box-id-1']->getMemoryLimit());
    Assert::equal(6, $limits['box-id-1']->getParallel());

    Assert::equal(3.0, $limits['box-id-2']->getWallTime());
    Assert::equal(6, $limits['box-id-2']->getMemoryLimit());
    Assert::equal(7, $limits['box-id-2']->getParallel());
  }

}

# Testing methods run
$testCase = new TestExerciseLimits;
$testCase->run();
