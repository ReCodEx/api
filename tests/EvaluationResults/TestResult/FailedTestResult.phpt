<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\EvaluationResults\TestResult;
use App\Helpers\EvaluationResults\FailedTestResult;
use App\Helpers\JobConfig\Tasks\TaskBase;
use App\Helpers\JobConfig\Tasks\ExternalTask;
use App\Helpers\JobConfig\Tasks\EvaluationTaskType;
use App\Helpers\JobConfig\Tasks\ExecutionTaskType;
use App\Helpers\JobConfig\TestConfig;


class FakeTask extends TaskBase {
  public function __construct(array $data) {
    $data["priority"] = 1;
    $data["fatal-failure"] = true;
    $data["cmd"] = [];
    $data["cmd"]["bin"] = "cmd";

    parent::__construct($data);
  }
}

class FakeExternalTask extends ExternalTask {
  public function __construct(array $data) {
    $data["priority"] = 1;
    $data["fatal-failure"] = true;
    $data["cmd"] = [];
    $data["cmd"]["bin"] = "cmd";

    parent::__construct($data);
  }
}


class TestFailedTestResult extends Tester\TestCase
{

  static $evalCfg = [
    "task-id" => "X",
    "test-id" => "A",
    "type" => EvaluationTaskType::TASK_TYPE
  ];

  static $execCfg = [
    "task-id" => "Y",
    "test-id" => "A",
    "type" => ExecutionTaskType::TASK_TYPE,
    "sandbox" => [
      "name" => "isolate",
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
    $exec = new FakeExternalTask(self::$execCfg);
    $eval = new FakeTask(self::$evalCfg);
    $cfg = new TestConfig(
      "some ID",
      [
          new FakeTask([ "task-id" => "A" ]),
          $exec,
          new FakeTask([ "task-id" => "C" ]),
          $eval,
          new FakeTask([ "task-id" => "D" ])
      ]
    );

    $res = new FailedTestResult($cfg);
    Assert::equal("some ID", $res->getId());
    Assert::equal(TestResult::STATUS_FAILED, $res->getStatus());
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
$testCase = new TestFailedTestResult;
$testCase->run();
