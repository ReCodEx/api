<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\Limits;
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

  public function testMissingMemoryLimit() {
    $data = self::$sample;
    unset($data["memory"]);
    Assert::exception(function () use ($data) { new Limits($data); }, 'App\Exceptions\JobConfigLoadingException');
  }

  public function testMissingTimeLimit() {
    $data = self::$sample;
    unset($data["time"]);
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
  }

}

# Testing methods run
$testCase = new TestLimits;
$testCase->run();
