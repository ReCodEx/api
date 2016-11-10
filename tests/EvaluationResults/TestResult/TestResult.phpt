<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\EvaluationResults\EvaluationTaskResult;
use App\Helpers\EvaluationResults\ExecutionTaskResult;
use App\Helpers\EvaluationResults\TestResult as TR;
use App\Helpers\EvaluationResults\TaskResult;
use App\Helpers\JobConfig\Tasks\TaskBase;
use App\Helpers\JobConfig\Tasks\ExternalTask;
use App\Helpers\JobConfig\Tasks\EvaluationTaskType;
use App\Helpers\JobConfig\Tasks\ExecutionTaskType;
use App\Helpers\JobConfig\TestConfig;
use App\Exceptions\ResultsLoadingException;


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


class TestTestResult extends Tester\TestCase
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

  static $evalRes = [
    "task-id" => "X",
    "status" => TaskResult::STATUS_OK,
    "judge_output" => "0.123"
  ];

  static $execRes = [
    "task-id" => "Y",
    "status" => TaskResult::STATUS_OK,
    "sandbox_results" => [
      "exitcode"  => 0,
      "max-rss"   => 19696,
      "memory"    => 6032,
      "wall-time" => 0.092,
      "exitsig"   => 0,
      "message"   => "This is a random message",
      "status"    => "OK",
      "time"      => 0.037,
      "killed"    => false
    ]
  ];


  public function testCalculateStatus() {
    Assert::equal(TR::STATUS_OK,        TR::calculateStatus(TR::STATUS_OK, TR::STATUS_OK));
    Assert::equal(TR::STATUS_SKIPPED,   TR::calculateStatus(TR::STATUS_OK, TR::STATUS_SKIPPED));
    Assert::equal(TR::STATUS_FAILED,    TR::calculateStatus(TR::STATUS_OK, TR::STATUS_FAILED));
    Assert::equal(TR::STATUS_SKIPPED,   TR::calculateStatus(TR::STATUS_SKIPPED, TR::STATUS_OK)); // this should never happen in real world
    Assert::equal(TR::STATUS_FAILED,    TR::calculateStatus(TR::STATUS_FAILED, TR::STATUS_OK)); // this should never happen in real world
    Assert::equal(TR::STATUS_SKIPPED,   TR::calculateStatus(TR::STATUS_SKIPPED, TR::STATUS_FAILED)); // this should never happen in real world
    Assert::equal(TR::STATUS_FAILED,    TR::calculateStatus(TR::STATUS_FAILED, TR::STATUS_SKIPPED));
    Assert::equal(TR::STATUS_SKIPPED,   TR::calculateStatus(TR::STATUS_SKIPPED, TR::STATUS_SKIPPED));
    Assert::equal(TR::STATUS_FAILED,    TR::calculateStatus(TR::STATUS_FAILED, TR::STATUS_FAILED));
  }

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

    $execRes = [ new ExecutionTaskResult(self::$execRes) ];
    $evalRes = new EvaluationTaskResult(self::$evalRes);

    $res = new TR($cfg, $execRes, $evalRes, "A");
    Assert::equal("some ID", $res->getId());
    Assert::equal(TR::STATUS_OK, $res->getStatus());
    Assert::equal($execRes[0]->getStats(), $res->getStats()[0]);
    Assert::equal(0.123, $res->getScore());
    Assert::true($res->didExecutionMeetLimits());
    Assert::same($execRes[0]->getExitCode(), $res->getExitCode());
    Assert::same(6032.0/8096.0, $res->getUsedMemoryRatio());
    Assert::same(0.037/1.0, $res->getUsedTimeRatio());
    Assert::same("This is a random message", $res->getMessage());
  }

  public function testFailedTestBecauseOfLimits() {
    $execCfg = self::$execCfg;
    $execCfg["sandbox"]["limits"][0]["memory"] = 1024;
    $execCfg["sandbox"]["limits"][0]["time"] = 0.01;

    $exec = new FakeExternalTask($execCfg);
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

    $execRes = [ new ExecutionTaskResult(self::$execRes) ];
    $evalRes = new EvaluationTaskResult(self::$evalRes);

    $res = new TR($cfg, $execRes, $evalRes, "A");
    Assert::false($res->didExecutionMeetLimits());
    Assert::equal("some ID", $res->getId());
    Assert::equal(TR::STATUS_FAILED, $res->getStatus());
    Assert::equal(0.0, $res->getScore());
    Assert::same($execRes[0]->getExitCode(), $res->getExitCode());
    Assert::same(6032.0/1024.0, $res->getUsedMemoryRatio());
    Assert::same(0.037/0.01, $res->getUsedTimeRatio());
    Assert::same("This is a random message", $res->getMessage());
  }

  public function testFailedTestBecauseOfFailedExecution() {
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

    foreach ([
      [TaskResult::STATUS_SKIPPED, TaskResult::STATUS_SKIPPED, TR::STATUS_SKIPPED],
      [TaskResult::STATUS_FAILED, TaskResult::STATUS_FAILED, TR::STATUS_FAILED],
      [TaskResult::STATUS_FAILED, TaskResult::STATUS_SKIPPED, TR::STATUS_FAILED],
    ] as $statuses) {
      list($execStatus, $evalStatus, $result) = $statuses;
      $execRes = self::$execRes;
      $execRes["status"] = $execStatus;
      $evalRes = self::$evalRes;
      $evalRes["status"] = $evalStatus;

      $execRes = new ExecutionTaskResult($execRes);
      $evalRes = new EvaluationTaskResult($evalRes);
      $res = new TR($cfg, [ $execRes ], $evalRes, "A");
      Assert::false($res->didExecutionMeetLimits());
      Assert::equal($result, $res->getStatus());
      Assert::equal(0.0, $res->getScore());
    }
  }

}

# Testing methods run
$testCase = new TestTestResult;
$testCase->run();
