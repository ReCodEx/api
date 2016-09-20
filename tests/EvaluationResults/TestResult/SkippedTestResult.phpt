<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\EvaluationResults\TestResult;
use App\Helpers\EvaluationResults\SkippedTestResult;
use App\Helpers\JobConfig\ExecutionTaskConfig;
use App\Helpers\JobConfig\TaskConfig;
use App\Helpers\JobConfig\TestConfig;

class TestSkippedTestResult extends Tester\TestCase
{

  static $evalCfg = [
    "task-id" => "X",
    "test-id" => "A",
    "type" => TaskConfig::TYPE_EVALUATION
  ];

  static $execCfg = [
    "task-id" => "Y",
    "test-id" => "A",
    "type" => TaskConfig::TYPE_EXECUTION,
    "sandbox" => [
      "limits" => [
        [
          "hw-group-id" => "A",
          "memory" => 8096,
          "time" => 1.0
        ]
      ]
    ]
  ];

  public function testOKTest() {
    $exec = new ExecutionTaskConfig(self::$execCfg);
    $eval = new TaskConfig(self::$evalCfg);
    $cfg = new TestConfig(
      "some ID",
      [
          new TaskConfig([ "task-id" => "A" ]),
          $exec,
          new TaskConfig([ "task-id" => "C" ]),
          $eval,
          new TaskConfig([ "task-id" => "D" ])
      ]
    );

    $res = new SkippedTestResult($cfg);
    Assert::equal("some ID", $res->getId());
    Assert::equal(TestResult::STATUS_SKIPPED, $res->getStatus());
    Assert::equal(NULL, $res->getStats());
    Assert::equal(0.0, $res->getScore());
    Assert::false($res->didExecutionMeetLimits());
    Assert::same(0, $res->getExitCode());
    Assert::same(0.0, $res->getUsedMemoryRatio());
    Assert::same(0.0, $res->getUsedTimeRatio());
    Assert::same("", $res->getMessage());
  }

}

# Testing methods run
$testCase = new TestSkippedTestResult;
$testCase->run();
