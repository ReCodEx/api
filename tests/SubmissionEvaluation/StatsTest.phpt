<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Model\Helpers\EvaluationResults\Stats;

class TestStats extends Tester\TestCase
{

  public function testParseStats() {
    $stats = new Stats([
      "exitcode"  => 0,
      "max-rss"   => 19696,
      "memory"    => 6032,
      "wall-time" => 0.092,
      "exitsig"   => 0,
      "message"   => "This is a random message",
      "status"    => "OK",
      "time"      => 0.037,
      "killed"    => false
    ]);

    Assert::equal(0, $stats->getExitCode());
    Assert::equal(6032, $stats->getUsedMemory());
    Assert::equal(0.037, $stats->getUsedTime());
    Assert::equal("This is a random message", $stats->getMessage());
  }

  public function testTimeLimit() {
    $stats = new Stats([ "time" => 0.5 ]);
    Assert::equal(TRUE, $stats->isTimeOK(1));
    Assert::equal(FALSE, $stats->isTimeOK(0.4));
  }

  public function testMemoryLimit() {
    $stats = new Stats([ "memory" => 100 ]);
    Assert::equal(TRUE, $stats->isMemoryOK(200));
    Assert::equal(FALSE, $stats->isMemoryOK(50));    
  }
  
  public function testSerialization() {
    $stats = new Stats([
      "exitcode"  => 0,
      "max-rss"   => 19696,
      "memory"    => 6032,
      "wall-time" => 0.092,
      "exitsig"   => 0,
      "message"   => "This is a random message",
      "status"    => "OK",
      "time"      => 0.037,
      "killed"    => false
    ]);

    $json = json_encode([
      "exitcode"  => 0,
      "max-rss"   => 19696,
      "memory"    => 6032,
      "wall-time" => 0.092,
      "exitsig"   => 0,
      "message"   => "This is a random message",
      "status"    => "OK",
      "time"      => 0.037,
      "killed"    => false
    ]);

    Assert::equal($json, (string) $stats);
  }

  public function testJudgeOutput() {
    $stats = new Stats([ ]);
    Assert::equal(FALSE, $stats->hasJudgeOutput());
    $stats = new Stats([ "judge_output" => "123 abc" ]);
    Assert::equal(TRUE, $stats->hasJudgeOutput());
    Assert::equal(123.0, $stats->getJudgeOutput());
  }

}

# Testing methods run
$testCase = new TestStats;
$testCase->run();
