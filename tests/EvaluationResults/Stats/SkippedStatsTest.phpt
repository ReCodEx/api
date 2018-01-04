<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\EvaluationResults\SkippedStats;
use App\Helpers\JobConfig\Limits;

/**
 * @testCase
 */
class TestSkippedStats extends Tester\TestCase
{
  static $sample = [
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

  public function testParseStats() {
    $stats = new SkippedStats();
    Assert::equal(SkippedStats::EXIT_CODE_UNKNOWN, $stats->getExitCode());
    Assert::equal(0, $stats->getUsedMemory());
    Assert::equal(0.0, $stats->getUsedWallTime());
    Assert::equal(0.0, $stats->getUsedCpuTime());
    Assert::false($stats->wasKilled());
    Assert::equal("SKIPPED", (string) $stats);
    Assert::false($stats->doesMeetAllCriteria(new Limits([ 'hw-group-id' => 'X', 'time' => 0.0, 'memory' => 0 ])));
    Assert::false($stats->isMemoryOK(0));
    Assert::false($stats->isWallTimeOK(0.0));
  }
}

# Testing methods run
$testCase = new TestSkippedStats;
$testCase->run();
