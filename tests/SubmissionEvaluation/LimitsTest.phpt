<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\Limits;

class LimitsTest extends Tester\TestCase
{
  
  static $sample = [ "hw-group-id" => "A", "memory" => 123, "time" => 456 ];

  public function testHardwareGroupId() {
    $limits = new Limits(self::$sample);
    Assert::equal("A", $limits->getId());
  }

  public function testMemoryAndTimeLimits() {
    $limits = new Limits(self::$sample);
    Assert::equal(123, $limits->getMemoryLimit());
    Assert::equal(456.0, $limits->getTimeLimit());
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

}

# Testing methods run
$testCase = new LimitsTest;
$testCase->run();
