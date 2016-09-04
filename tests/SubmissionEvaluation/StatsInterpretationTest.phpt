<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\EvaluationResults\Stats;
use App\Helpers\EvaluationResults\StatsInterpretation;
use App\Helpers\JobConfig\Limits;

class TestStatsInterpretation extends Tester\TestCase
{

  public function testTimeUnused() {
    $stats = new Stats([ "time" => 0.037 ]);
    $limits = new Limits([ "hw-group-id" => "xzy", "time" => 0.1 ]);
    $interpretation = new StatsInterpretation($stats, $limits);
    
    Assert::equal(TRUE, $interpretation->isTimeOK());
    Assert::equal(0.037 / 0.1, $interpretation->getUsedTimeRatio());
  }

  public function testTimeSame() {
    $stats = new Stats([ "time" => 0.1 ]);
    $limits = new Limits([ "hw-group-id" => "xzy", "time" => 0.1 ]);
    $interpretation = new StatsInterpretation($stats, $limits);
    
    Assert::equal(TRUE, $interpretation->isTimeOK());
    Assert::equal(1.0, $interpretation->getUsedTimeRatio());
  }

  public function testTimeExceeded() {
    $stats = new Stats([ "time" => 0.1 ]);
    $limits = new Limits([ "hw-group-id" => "xzy", "time" => 0.037 ]);
    $interpretation = new StatsInterpretation($stats, $limits);
    
    Assert::equal(FALSE, $interpretation->isTimeOK());
    Assert::equal(0.1 / 0.037, $interpretation->getUsedTimeRatio());
  }

  public function testMemoryUnused() {
    $stats = new Stats([ "memory" => 128 ]);
    $limits = new Limits([ "hw-group-id" => "xzy", "memory" => 256 ]);
    $interpretation = new StatsInterpretation($stats, $limits);
    
    Assert::equal(TRUE, $interpretation->isMemoryOK());
    Assert::equal(0.5, $interpretation->getUsedMemoryRatio());
  }

  public function testMemorySame() {
    $stats = new Stats([ "memory" => 128 ]);
    $limits = new Limits([ "hw-group-id" => "xzy", "memory" => 128 ]);
    $interpretation = new StatsInterpretation($stats, $limits);
    
    Assert::equal(TRUE, $interpretation->isMemoryOK());
    Assert::equal(1.0, $interpretation->getUsedMemoryRatio());
  }

  public function testMemoryExceeded() {
    $stats = new Stats([ "memory" => 256 ]);
    $limits = new Limits([ "hw-group-id" => "xzy", "memory" => 128 ]);
    $interpretation = new StatsInterpretation($stats, $limits);
    
    Assert::equal(FALSE, $interpretation->isMemoryOK());
    Assert::equal(2.0, $interpretation->getUsedMemoryRatio());
  }

  public function testBothOK() {
    $stats = new Stats([ "time" => 1, "memory" => 64 ]);
    $limits = new Limits([ "hw-group-id" => "xzy", "time" => 2, "memory" => 128 ]);
    $interpretation = new StatsInterpretation($stats, $limits);
    
    Assert::equal(TRUE, $interpretation->doesMeetAllCriteria());
  }

  public function testBothExceeded() {
    $stats = new Stats([ "time" => 3, "memory" => 2560 ]);
    $limits = new Limits([ "hw-group-id" => "xzy", "time" => 2, "memory" => 128 ]);
    $interpretation = new StatsInterpretation($stats, $limits);
    
    Assert::equal(FALSE, $interpretation->doesMeetAllCriteria());
  }

  public function testOnlyTimeExceeded() {
    $stats = new Stats([ "time" => 3, "memory" => 1 ]);
    $limits = new Limits([ "hw-group-id" => "xzy", "time" => 2, "memory" => 128 ]);
    $interpretation = new StatsInterpretation($stats, $limits);
    
    Assert::equal(FALSE, $interpretation->doesMeetAllCriteria());
  }

  public function testOnlyMemoryExceeded() {
    $stats = new Stats([ "time" => 1, "memory" => 2560 ]);
    $limits = new Limits([ "hw-group-id" => "xzy", "time" => 2, "memory" => 128 ]);
    $interpretation = new StatsInterpretation($stats, $limits);
    
    Assert::equal(FALSE, $interpretation->doesMeetAllCriteria());
  }


}

# Testing methods run
$testCase = new TestStatsInterpretation;
$testCase->run();
