<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\VariableFactory;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;
use Symfony\Component\Yaml\Yaml;

class TestLimits extends Tester\TestCase
{
  static $sample = [
    "memory" => 123,
    "wall-time" => 456.0
  ];
  static $cfg = [
    [ "memory" => 123, "wall-time" => 456 ],
    [ "memory" => 321, "wall-time" => 645 ]
  ];
  static $optional = [
    "wall-time" => 2.0,
    "memory" => 5,
    "parallel" => 6
  ];

  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader(new VariableFactory());
  }

  public function testSerialization()
  {
    $deserialized = Yaml::parse((string)$this->loader->loadLimits(self::$sample));
    Assert::equal(self::$sample, $deserialized);
  }

  public function testParsingA() {
    $limits = $this->loader->loadLimits(self::$cfg[0]);
    Assert::equal(123, $limits->getMemoryLimit());
    Assert::type("int", $limits->getMemoryLimit());
    Assert::equal(456.0, $limits->getWallTime());
    Assert::type("float", $limits->getWallTime());
  }

  public function testParsingB() {
    $limits = $this->loader->loadLimits(self::$cfg[1]);
    Assert::equal(321, $limits->getMemoryLimit());
    Assert::type("int", $limits->getMemoryLimit());
    Assert::equal(645.0, $limits->getWallTime());
    Assert::type("float", $limits->getWallTime());
  }

  public function testOptional() {
    $limits = $this->loader->loadLimits(self::$optional);
    Assert::equal(2.0, $limits->getWallTime());
    Assert::equal(5, $limits->getMemoryLimit());
    Assert::equal(6, $limits->getParallel());
    Assert::equal(self::$optional, $limits->toArray());
  }

}

# Testing methods run
$testCase = new TestLimits;
$testCase->run();
