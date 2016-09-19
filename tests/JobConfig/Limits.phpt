<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\Limits;
use App\Helpers\JobConfig\InfiniteLimits;
use Symfony\Component\Yaml\Yaml;

class TestLimits extends Tester\TestCase
{
  static $cfg = [
    [ "hw-group-id" => "A", "memory" => 123, "time" => 456 ],
    [ "hw-group-id" => "B", "memory" => 321, "time" => 645 ]
  ];

  public function testSerialization() {
    $cfg = [ "hw-group-id" => "A", "memory" => 123, "time" => 456, "somethingElse" => 124578 ];
    $deserialized = Yaml::parse((string) new Limits($cfg));
    Assert::equal($cfg, $deserialized);
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
    $limits = new InfiniteLimits("XYZ");
    Assert::equal("XYZ", $limits->getId());
    Assert::equal(InfiniteLimits::INFINITE_MEMORY, $limits->getMemoryLimit());
    Assert::type("int", $limits->getMemoryLimit());
    Assert::equal(InfiniteLimits::INFINITE_TIME, $limits->getTimeLimit());
    Assert::type("float", $limits->getTimeLimit());
  }

}

# Testing methods run
$testCase = new TestLimits;
$testCase->run();
