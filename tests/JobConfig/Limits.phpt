<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\Limits;
use App\Helpers\JobConfig\BoundDirectoryConfig;
use App\Helpers\JobConfig\UndefinedLimits;
use Symfony\Component\Yaml\Yaml;

// TODO: finish tests
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
    "chdir" => "/change/dir",
    "bound-directories" => [
      [ "src" => "/sourceA", "dst" => "/destinationA", "mode" => "RO" ],
      [ "src" => "/sourceB", "dst" => "/destinationB", "mode" => "MAYBE" ],
    ]
  ];

  public function testSerialization() {
    $cfg = [ "hw-group-id" => "A", "memory" => 123, "time" => 456, "somethingElse" => 124578 ];
    $deserialized = Yaml::parse((string) new Limits($cfg));
    Assert::isEqual($cfg, $deserialized);
  }

  public function testMissingHWGroupId() {
    $data = self::$sample;
    unset($data["hw-group-id"]);
    Assert::exception(function () use ($data) { new Limits($data); }, 'App\Exceptions\JobConfigLoadingException');
  }

  public function testParsingA() {
    $limits = new Limits(self::$cfg[0]);
    Assert::equal("A", $limits->getId());
    Assert::equal(123, $limits->getMemoryLimit());
    Assert::type("int", $limits->getMemoryLimit());
    Assert::equal(456.0, $limits->getTimeLimit());
    Assert::type("float", $limits->getTimeLimit());
  }

  public function testParsingB() {
    $limits = new Limits(self::$cfg[1]);
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
    $limits = new Limits(self::$optional);
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
    Assert::equal("/change/dir", $limits->getChdir());
    Assert::equal(2, count($limits->getBoundDirectories()));
    Assert::isEqual(self::$optional, $limits->toArray());
  }

  public function testBoundDirectoryConfig() {
    $boundDir = new BoundDirectoryConfig(self::$boundDir);
    Assert::equal("/tmp", $boundDir->getSource());
    Assert::equal("/temp", $boundDir->getDestination());
    Assert::equal("RW", $boundDir->getMode());
    Assert::isEqual(self::$boundDir, $boundDir->toArray());
  }

}

# Testing methods run
$testCase = new TestLimits;
$testCase->run();
