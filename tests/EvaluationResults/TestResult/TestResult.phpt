<?php

include '../../bootstrap.php';

use App\Exceptions\JobConfigLoadingException;
use App\Exceptions\ResultsLoadingException;
use Tester\Assert;
use App\Helpers\JobConfig\Loader as JobConfigBuilder;
use App\Helpers\EvaluationResults\EvaluationTaskResult;
use App\Helpers\EvaluationResults\ExecutionTaskResult;
use App\Helpers\EvaluationResults\TestResult as TR;
use App\Helpers\EvaluationResults\TaskResult;
use App\Helpers\JobConfig\Tasks\Task;
use App\Helpers\JobConfig\Tasks\EvaluationTaskType;
use App\Helpers\JobConfig\Tasks\ExecutionTaskType;
use App\Helpers\JobConfig\TestConfig;


/**
 * @testCase
 */
class TestTestResult extends Tester\TestCase
{

  static $evalCfg = [
    "task-id" => "X",
    "test-id" => "A",
    "type" => EvaluationTaskType::TASK_TYPE,
    "priority" => 1,
    "fatal-failure" => false,
    "cmd" => [ "bin" => "a.out" ]
  ];

  static $execCfg = [
    "task-id" => "Y",
    "test-id" => "A",
    "type" => ExecutionTaskType::TASK_TYPE,
    "priority" => 2,
    "fatal-failure" => false,
    "cmd" => [ "bin" => "a.out" ],
    "sandbox" => [
      "name" => "isolate",
      "limits" => [
        [
          "hw-group-id" => "A",
          "memory" => 8096,
          "time" => 2.0,
          "wall-time" => 1.0
        ]
      ]
    ]
  ];

  static $evalRes = [
    "task-id" => "X",
    "status" => TaskResult::STATUS_OK,
    "output" => ["stdout" => "0.123" ]
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

  /** @var JobConfigBuilder */
  private $builder;

  public function __construct() {
    $this->builder = new JobConfigBuilder();
  }


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
    $cfg = new TestConfig(
      "some ID",
      [
          (new Task())->setId("A"),
          $this->builder->loadTask(self::$execCfg),
          (new Task())->setId("C"),
          $this->builder->loadTask(self::$evalCfg),
          (new Task())->setId("D")
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
    Assert::true($res->isMemoryOK());
    Assert::true($res->isWallTimeOK());
    Assert::true($res->isCpuTimeOK());
    Assert::same($execRes[0]->getExitCode(), $res->getExitCode());
    Assert::same(8096, $res->getUsedMemoryLimit());
    Assert::same(6032, $res->getUsedMemory());
    Assert::same(1.0, $res->getUsedWallTimeLimit());
    Assert::same(0.092, $res->getUsedWallTime());
    Assert::same(2.0, $res->getUsedCpuTimeLimit());
    Assert::same(0.037, $res->getUsedCpuTime());
    Assert::same("This is a random message", $res->getMessage());
    Assert::equal("0.123", $res->getJudgeOutput());
  }

  public function testFailedTestBecauseOfLimits() {
    $execCfg = self::$execCfg;
    $execCfg["sandbox"]["limits"][0]["memory"] = 1024;
    $execCfg["sandbox"]["limits"][0]["wall-time"] = 0.01;
    $execCfg["sandbox"]["limits"][0]["time"] = 0.02;

    $cfg = new TestConfig(
      "some ID",
      [
          (new Task())->setId("A"),
          $this->builder->loadTask($execCfg),
          (new Task())->setId("C"),
          $this->builder->loadTask(self::$evalCfg),
          (new Task())->setId("D")
      ]
    );

    $execRes = [ new ExecutionTaskResult(self::$execRes) ];
    $evalRes = new EvaluationTaskResult(self::$evalRes);
    $res = new TR($cfg, $execRes, $evalRes, "A");

    Assert::equal("some ID", $res->getId());
    Assert::equal(TR::STATUS_FAILED, $res->getStatus());
    Assert::equal(0.0, $res->getScore());
    Assert::false($res->didExecutionMeetLimits());
    Assert::false($res->isMemoryOK());
    Assert::false($res->isWallTimeOK());
    Assert::false($res->isCpuTimeOK());
    Assert::same($execRes[0]->getExitCode(), $res->getExitCode());
    Assert::same(1024, $res->getUsedMemoryLimit());
    Assert::same(6032, $res->getUsedMemory());
    Assert::same(0.01, $res->getUsedWallTimeLimit());
    Assert::same(0.092, $res->getUsedWallTime());
    Assert::same(0.02, $res->getUsedCpuTimeLimit());
    Assert::same(0.037, $res->getUsedCpuTime());
    Assert::same("This is a random message", $res->getMessage());
    Assert::equal("0.123", $res->getJudgeOutput());
  }

  public function testFailedTestBecauseOfFailedExecution() {
    $cfg = new TestConfig(
      "some ID",
      [
          (new Task())->setId("A"),
          $this->builder->loadTask(self::$execCfg),
          (new Task())->setId("C"),
          $this->builder->loadTask(self::$evalCfg),
          (new Task())->setId("D")
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

      Assert::equal($execStatus === TaskResult::STATUS_SKIPPED ? false : true, $res->didExecutionMeetLimits());
      Assert::equal($result, $res->getStatus());
      Assert::equal(0.0, $res->getScore());
    }
  }

  //////////////////////////////////////
  /// Tests from original StatsInterpretation class

  public function testWallTimeUnused() {
    $execCfg = self::$execCfg;
    $execCfg["sandbox"]["limits"][0]["wall-time"] = 0.1;
    $res = $this->createDefaultTestResult($execCfg);

    Assert::equal(true, $res->isWallTimeOK());
    Assert::equal(0.1, $res->getUsedWallTimeLimit());
    Assert::equal(0.092, $res->getUsedWallTime());
  }

  public function testWallTimeSame() {
    $execCfg = self::$execCfg;
    $execCfg["sandbox"]["limits"][0]["wall-time"] = 0.092;
    $res = $this->createDefaultTestResult($execCfg);

    Assert::equal(true, $res->isWallTimeOK());
    Assert::equal(0.092, $res->getUsedWallTimeLimit());
    Assert::equal(0.092, $res->getUsedWallTime());
  }

  public function testWallTimeExceeded() {
    $execCfg = self::$execCfg;
    $execCfg["sandbox"]["limits"][0]["wall-time"] = 0.05;
    $res = $this->createDefaultTestResult($execCfg);

    Assert::equal(false, $res->isWallTimeOK());
    Assert::equal(0.05, $res->getUsedWallTimeLimit());
    Assert::equal(0.092, $res->getUsedWallTime());
  }

  public function testCpuTimeUnused() {
    $execCfg = self::$execCfg;
    $execCfg["sandbox"]["limits"][0]["time"] = 0.1;
    $res = $this->createDefaultTestResult($execCfg);

    Assert::equal(true, $res->isCpuTimeOK());
    Assert::equal(0.1, $res->getUsedCpuTimeLimit());
    Assert::equal(0.037, $res->getUsedCpuTime());
  }

  public function testCpuTimeSame() {
    $execCfg = self::$execCfg;
    $execCfg["sandbox"]["limits"][0]["time"] = 0.037;
    $res = $this->createDefaultTestResult($execCfg);

    Assert::equal(true, $res->isCpuTimeOK());
    Assert::equal(0.037, $res->getUsedCpuTimeLimit());
    Assert::equal(0.037, $res->getUsedCpuTime());
  }

  public function testCpuTimeExceeded() {
    $execCfg = self::$execCfg;
    $execCfg["sandbox"]["limits"][0]["time"] = 0.01;
    $res = $this->createDefaultTestResult($execCfg);

    Assert::equal(false, $res->isCpuTimeOK());
    Assert::equal(0.01, $res->getUsedCpuTimeLimit());
    Assert::equal(0.037, $res->getUsedCpuTime());
  }

  public function testMemoryUnused() {
    $execCfg = self::$execCfg;
    $execCfg["sandbox"]["limits"][0]["memory"] = 10000;
    $res = $this->createDefaultTestResult($execCfg);

    Assert::equal(true, $res->isMemoryOK());
    Assert::equal(10000, $res->getUsedMemoryLimit());
    Assert::equal(6032, $res->getUsedMemory());
  }

  public function testMemorySame() {
    $execCfg = self::$execCfg;
    $execCfg["sandbox"]["limits"][0]["memory"] = 6032;
    $res = $this->createDefaultTestResult($execCfg);

    Assert::equal(false, $res->isMemoryOK());
    Assert::equal(6032, $res->getUsedMemoryLimit());
    Assert::equal(6032, $res->getUsedMemory());
  }

  public function testMemoryExceeded() {
    $execCfg = self::$execCfg;
    $execCfg["sandbox"]["limits"][0]["memory"] = 1024;
    $res = $this->createDefaultTestResult($execCfg);

    Assert::equal(false, $res->isMemoryOK());
    Assert::equal(1024, $res->getUsedMemoryLimit());
    Assert::equal(6032, $res->getUsedMemory());
  }

  public function testAllOK() {
    $execCfg = self::$execCfg;
    $execCfg["sandbox"]["limits"][0]["wall-time"] = 1.0;
    $execCfg["sandbox"]["limits"][0]["time"] = 1.0;
    $execCfg["sandbox"]["limits"][0]["memory"] = 10000;
    $res = $this->createDefaultTestResult($execCfg);

    Assert::equal(true, $res->didExecutionMeetLimits());
  }

  public function testAllExceeded() {
    $execCfg = self::$execCfg;
    $execCfg["sandbox"]["limits"][0]["wall-time"] = 0.01;
    $execCfg["sandbox"]["limits"][0]["time"] = 0.01;
    $execCfg["sandbox"]["limits"][0]["memory"] = 1024;
    $res = $this->createDefaultTestResult($execCfg);

    Assert::equal(false, $res->didExecutionMeetLimits());
  }

  public function testOnlyWallTimeExceeded() {
    $execCfg = self::$execCfg;
    $execCfg["sandbox"]["limits"][0]["wall-time"] = 0.01;
    $execCfg["sandbox"]["limits"][0]["time"] = 1.0;
    $execCfg["sandbox"]["limits"][0]["memory"] = 10000;
    $res = $this->createDefaultTestResult($execCfg);

    Assert::equal(false, $res->didExecutionMeetLimits());
  }

  public function testOnlyCpuTimeExceeded() {
    $execCfg = self::$execCfg;
    $execCfg["sandbox"]["limits"][0]["wall-time"] = 1.0;
    $execCfg["sandbox"]["limits"][0]["time"] = 0.01;
    $execCfg["sandbox"]["limits"][0]["memory"] = 10000;
    $res = $this->createDefaultTestResult($execCfg);

    Assert::equal(false, $res->didExecutionMeetLimits());
  }

  public function testOnlyMemoryExceeded() {
    $execCfg = self::$execCfg;
    $execCfg["sandbox"]["limits"][0]["wall-time"] = 1.0;
    $execCfg["sandbox"]["limits"][0]["time"] = 1.0;
    $execCfg["sandbox"]["limits"][0]["memory"] = 1024;
    $res = $this->createDefaultTestResult($execCfg);

    Assert::equal(false, $res->didExecutionMeetLimits());
  }


  /**
   * Create TestResult class with given execution task configuration.
   * @param $execCfg
   * @return TR
   * @throws JobConfigLoadingException
   * @throws ResultsLoadingException
   */
  private function createDefaultTestResult($execCfg): TR {
    $cfg = new TestConfig("id", [
      $this->builder->loadTask($execCfg),
      $this->builder->loadTask(self::$evalCfg)
    ]);
    return new TR($cfg, [ new ExecutionTaskResult(self::$execRes) ], new EvaluationTaskResult(self::$evalRes), "A");
  }
}

# Testing methods run
$testCase = new TestTestResult();
$testCase->run();
