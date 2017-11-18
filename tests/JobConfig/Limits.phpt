<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\Loader;
use App\Helpers\JobConfig\UndefinedLimits;
use Symfony\Component\Yaml\Yaml;

/**
 * @testCase
 */
class TestLimits extends Tester\TestCase
{
  static $sample = [
    "hw-group-id" => "A",
    "memory" => 123,
    "time" => 456
  ];
  static $cfg = [
    [ "hw-group-id" => "A", "memory" => 123, "time" => 456 ],
    [ "hw-group-id" => "B", "memory" => 321, "time" => 645 ]
  ];
  static $boundDir = [
    "src" => "/tmp", "dst" => "/temp", "mode" => "RW"
  ];
  static $optional = [
    "hw-group-id" => "optional",
    "time" => 1,
    "wall-time" => 2,
    "extra-time" => 3,
    "stack-size" => 4,
    "memory" => 5,
    "parallel" => 6,
    "disk-size" => 7,
    "disk-files" => 8,
    "environ-variable" => [ "varA", "varB", "varC" ],
    "bound-directories" => [
      [ "src" => "/sourceA", "dst" => "/destinationA", "mode" => "RO" ],
      [ "src" => "/sourceB", "dst" => "/destinationB", "mode" => "MAYBE" ],
    ]
  ];

  /** @var Loader */
  private $builder;

  public function __construct() {
    $this->builder = new Loader;
  }

  public function testSerialization() {
    $cfg = [ "hw-group-id" => "A", "memory" => 123, "time" => 456, "somethingElse" => 124578 ];
    $deserialized = Yaml::parse((string) $this->builder->loadLimits($cfg));
    Assert::equal($cfg["hw-group-id"], $deserialized["hw-group-id"]);
    Assert::equal($cfg["memory"], $deserialized["memory"]);
    Assert::equal($cfg["time"], intval($deserialized["time"]));
    Assert::equal($cfg["somethingElse"], $deserialized["somethingElse"]);
  }

  public function testMissingHWGroupId() {
    $data = self::$sample;
    unset($data["hw-group-id"]);
    Assert::exception(function () use ($data) {
      $this->builder->loadLimits($data);
    }, 'App\Exceptions\JobConfigLoadingException');
  }

  public function testParsingA() {
    $limits = $this->builder->loadLimits(self::$cfg[0]);
    Assert::equal("A", $limits->getId());
    Assert::equal(123, $limits->getMemoryLimit());
    Assert::type("int", $limits->getMemoryLimit());
    Assert::equal(456.0, $limits->getTimeLimit());
    Assert::type("float", $limits->getTimeLimit());
  }

  public function testParsingB() {
    $limits = $this->builder->loadLimits(self::$cfg[1]);
    Assert::equal("B", $limits->getId());
    Assert::equal(321, $limits->getMemoryLimit());
    Assert::type("int", $limits->getMemoryLimit());
    Assert::equal(645.0, $limits->getTimeLimit());
    Assert::type("float", $limits->getTimeLimit());
  }

  public function testInfiniteLimits() {
    $limits = new UndefinedLimits("XYZ");
    Assert::equal("XYZ", $limits->getId());
    Assert::equal(0, $limits->getMemoryLimit());
    Assert::type("int", $limits->getMemoryLimit());
    Assert::equal(0.0, $limits->getTimeLimit());
    Assert::type("float", $limits->getTimeLimit());
    Assert::equal([ "hw-group-id" => "XYZ" ], $limits->toArray());
  }

  public function testOptional() {
    $limits = $this->builder->loadLimits(self::$optional);
    Assert::equal("optional", $limits->getId());
    Assert::equal(1.0, $limits->getTimeLimit());
    Assert::equal(2.0, $limits->getWallTime());
    Assert::equal(3.0, $limits->getExtraTime());
    Assert::equal(4, $limits->getStackSize());
    Assert::equal(5, $limits->getMemoryLimit());
    Assert::equal(6, $limits->getParallel());
    Assert::equal(7, $limits->getDiskSize());
    Assert::equal(8, $limits->getDiskFiles());
    Assert::isEqual([ "varA", "varB", "varC" ], $limits->getEnvironVariables());
    Assert::count(2, $limits->getBoundDirectories());
    Assert::isEqual(self::$optional, $limits->toArray());
  }

  public function testBoundDirectoryConfig() {
    $boundDir = $this->builder->loadBoundDirectoryConfig(self::$boundDir);
    Assert::equal("/tmp", $boundDir->getSource());
    Assert::equal("/temp", $boundDir->getDestination());
    Assert::equal("RW", $boundDir->getMode());
    Assert::isEqual(self::$boundDir, $boundDir->toArray());
  }

}

# Testing methods run
$testCase = new TestLimits;
$testCase->run();
