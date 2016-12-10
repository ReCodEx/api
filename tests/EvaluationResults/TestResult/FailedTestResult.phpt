<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\EvaluationResults\TestResult;
use App\Helpers\EvaluationResults\FailedTestResult;
use App\Helpers\JobConfig\Loader;
use App\Helpers\JobConfig\Tasks\Task;
use App\Helpers\JobConfig\Tasks\EvaluationTaskType;
use App\Helpers\JobConfig\Tasks\ExecutionTaskType;
use App\Helpers\JobConfig\TestConfig;


class TestFailedTestResult extends Tester\TestCase
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
          "time" => 1.0
        ]
      ]
    ]
  ];

  /** @var Loader */
  private $builder;

  public function __construct() {
    $this->builder = new Loader;
  }

  public function testOKTest() {
    $cfg = new TestConfig(
      "some ID",
      [
          (new Task)->setId("A"),
          $this->builder->loadTask(self::$execCfg),
          (new Task)->setId("C"),
          $this->builder->loadTask(self::$evalCfg),
          (new Task)->setId("D")
      ]
    );

    $res = new FailedTestResult($cfg);
    Assert::equal("some ID", $res->getId());
    Assert::equal(TestResult::STATUS_FAILED, $res->getStatus());
    Assert::equal([], $res->getStats());
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
