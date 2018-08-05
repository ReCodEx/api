<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\EvaluationResults\Stats;
use App\Helpers\EvaluationResults\StatsInterpretation;
use App\Helpers\JobConfig\Loader;
use App\Helpers\JobConfig\Limits;

/**
 * @testCase
 */
class TestStatsInterpretation extends Tester\TestCase
{

  static $limitsSample = [ "hw-group-id" => "A", "memory" => 123, "time" => 456 ];
  static $statsSample = [
    "exitcode"  => 0,
    "max-rss"   => 19696,
    "memory"    => 6032,
    "wall-time" => 0.092,
    "exitsig"   => 0,
    "message"   => "This is a random message",
    "status"    => "OK",
    "time"      => 0.037,
    "killed"    => false
  ];

  /** @var Loader */
  private $builder;

  public function __construct() {
    $this->builder = new Loader();
  }

  public function testWallTimeUnused() {
    $stats = new Stats(self::$statsSample);
    $limits = $this->builder->loadLimits(array_merge(self::$limitsSample, [ "hw-group-id" => "xzy", "wall-time" => 0.1 ]));
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(true, $interpretation->isWallTimeOK());
    Assert::equal(0.092 / 0.1, $interpretation->getUsedWallTimeRatio());
    Assert::equal(0.092, $interpretation->getUsedWallTime());
  }

  public function testWallTimeSame() {
    $stats = new Stats(array_merge(self::$statsSample, [ "wall-time" => 0.1 ]));
    $limits = $this->builder->loadLimits(array_merge(self::$limitsSample, [ "hw-group-id" => "xzy", "wall-time" => 0.1 ]));
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(true, $interpretation->isWallTimeOK());
    Assert::equal(1.0, $interpretation->getUsedWallTimeRatio());
    Assert::equal(0.1, $interpretation->getUsedWallTime());
  }

  public function testWallTimeExceeded() {
    $stats = new Stats(array_merge(self::$statsSample, [ "wall-time" => 0.1 ]));
    $limits = $this->builder->loadLimits(array_merge(self::$limitsSample, [ "hw-group-id" => "xzy", "wall-time" => 0.037 ]));
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(false, $interpretation->isWallTimeOK());
    Assert::equal(0.1 / 0.037, $interpretation->getUsedWallTimeRatio());
    Assert::equal(0.1, $interpretation->getUsedWallTime());
  }

  public function testCpuTimeUnused() {
    $stats = new Stats(self::$statsSample);
    $limits = $this->builder->loadLimits(array_merge(self::$limitsSample, [ "hw-group-id" => "xzy", "time" => 0.1 ]));
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(true, $interpretation->isCpuTimeOK());
    Assert::equal(0.037 / 0.1, $interpretation->getUsedCpuTimeRatio());
    Assert::equal(0.037, $interpretation->getUsedCpuTime());
  }

  public function testCpuTimeSame() {
    $stats = new Stats(array_merge(self::$statsSample, [ "time" => 0.1 ]));
    $limits = $this->builder->loadLimits(array_merge(self::$limitsSample, [ "hw-group-id" => "xzy", "time" => 0.1 ]));
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(true, $interpretation->isCpuTimeOK());
    Assert::equal(1.0, $interpretation->getUsedCpuTimeRatio());
    Assert::equal(0.1, $interpretation->getUsedCpuTime());
  }

  public function testCpuTimeExceeded() {
    $stats = new Stats(array_merge(self::$statsSample, [ "time" => 0.1 ]));
    $limits = $this->builder->loadLimits(array_merge(self::$limitsSample, [ "hw-group-id" => "xzy", "time" => 0.037 ]));
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(false, $interpretation->isCpuTimeOK());
    Assert::equal(0.1 / 0.037, $interpretation->getUsedCpuTimeRatio());
    Assert::equal(0.1, $interpretation->getUsedCpuTime());
  }

  public function testMemoryUnused() {
    $stats = new Stats(array_merge(self::$statsSample, [ "memory" => 128 ]));
    $limits = $this->builder->loadLimits(array_merge(self::$limitsSample, [ "hw-group-id" => "xzy", "memory" => 256 ]));
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(true, $interpretation->isMemoryOK());
    Assert::equal(0.5, $interpretation->getUsedMemoryRatio());
    Assert::equal(128, $interpretation->getUsedMemory());
  }

  public function testMemorySame() {
    $stats = new Stats(array_merge(self::$statsSample, [ "memory" => 128 ]));
    $limits = $this->builder->loadLimits(array_merge(self::$limitsSample, [ "hw-group-id" => "xzy", "memory" => 128 ]));
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(false, $interpretation->isMemoryOK());
    Assert::equal(1.0, $interpretation->getUsedMemoryRatio());
    Assert::equal(128, $interpretation->getUsedMemory());
  }

  public function testMemoryExceeded() {
    $stats = new Stats(array_merge(self::$statsSample, [ "memory" => 256 ]));
    $limits = $this->builder->loadLimits(array_merge(self::$limitsSample, [ "hw-group-id" => "xzy", "memory" => 128 ]));
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(false, $interpretation->isMemoryOK());
    Assert::equal(2.0, $interpretation->getUsedMemoryRatio());
    Assert::equal(256, $interpretation->getUsedMemory());
  }

  public function testAllOK() {
    $stats = new Stats(array_merge(self::$statsSample, [ "wall-time" => 1, "time" => 2, "memory" => 64 ]));
    $limits = $this->builder->loadLimits([ "hw-group-id" => "xzy", "wall-time" => 2, "time" => 3, "memory" => 128 ]);
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(true, $interpretation->doesMeetAllCriteria());
  }

  public function testAllExceeded() {
    $stats = new Stats(array_merge(self::$statsSample, [ "wall-time" => 3, "time" => 4, "memory" => 2560 ]));
    $limits = $this->builder->loadLimits([ "hw-group-id" => "xzy", "wall-time" => 2, "time" => 3, "memory" => 128 ]);
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(false, $interpretation->doesMeetAllCriteria());
  }

  public function testOnlyWallTimeExceeded() {
    $stats = new Stats(array_merge(self::$statsSample, [ "wall-time" => 3, "memory" => 1 ]));
    $limits = $this->builder->loadLimits([ "hw-group-id" => "xzy", "wall-time" => 2, "memory" => 128 ]);
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(false, $interpretation->doesMeetAllCriteria());
  }

  public function testOnlyCpuTimeExceeded() {
    $stats = new Stats(array_merge(self::$statsSample, [ "time" => 3, "memory" => 1 ]));
    $limits = $this->builder->loadLimits([ "hw-group-id" => "xzy", "time" => 2, "memory" => 128 ]);
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(false, $interpretation->doesMeetAllCriteria());
  }

  public function testOnlyMemoryExceeded() {
    $stats = new Stats(array_merge(self::$statsSample, [ "wall-time" => 1, "memory" => 2560 ]));
    $limits = $this->builder->loadLimits([ "hw-group-id" => "xzy", "wall-time" => 2, "memory" => 128 ]);
    $interpretation = new StatsInterpretation($stats, $limits);

    Assert::equal(false, $interpretation->doesMeetAllCriteria());
  }


}

# Testing methods run
$testCase = new TestStatsInterpretation();
$testCase->run();
