<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\EvaluationResults\SkippedStats;
use App\Helpers\JobConfig\Limits;

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
    "killed"    => false,
    "output"    => ""
];

  public function testParseStats() {
    $stats = new SkippedStats();
    Assert::equal(SkippedStats::EXIT_CODE, $stats->getExitCode());
    Assert::equal(0, $stats->getUsedMemory());
    Assert::equal(0.0, $stats->getUsedTime());
    Assert::false($stats->wasKilled());
    Assert::equal("SKIPPED", (string) $stats);
    Assert::false($stats->doesMeetAllCriteria(new Limits([ 'hw-group-id' => 'X', 'time' => 0.0, 'memory' => 0 ])));
    Assert::false($stats->isMemoryOK(0));
    Assert::false($stats->isTimeOK(0.0));
    Assert::equal("", $stats->getOutput());
  }
}

# Testing methods run
$testCase = new TestSkippedStats;
$testCase->run();
