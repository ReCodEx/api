<?php

include '../../bootstrap.php';

use App\Helpers\EvaluationResults\EvaluationTaskResult;
use App\Helpers\EvaluationResults\ExecutionTaskResult;
use App\Helpers\EvaluationResults\SkippedSandboxResults;
use App\Helpers\EvaluationResults\TaskResult;
use Tester\Assert;
use App\Helpers\EvaluationResults\TestResult;
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
        "cmd" => ["bin" => "a.out"]
    ];

    static $execCfg = [
        "task-id" => "Y",
        "test-id" => "A",
        "type" => ExecutionTaskType::TASK_TYPE,
        "priority" => 2,
        "fatal-failure" => false,
        "cmd" => ["bin" => "a.out"],
        "sandbox" => [
            "name" => "isolate",
            "limits" => [
                [
                    "hw-group-id" => "A",
                    "memory" => 10000,
                    "wall-time" => 1.0
                ]
            ]
        ]
    ];

    static $evalRes = [
        "task-id" => "X",
        "status" => TaskResult::STATUS_SKIPPED
    ];

    static $execRes = [
        "task-id" => "Y",
        "status" => TaskResult::STATUS_FAILED,
        "sandbox_results" => [
            "exitcode" => 10,
            "max-rss" => 19696,
            "memory" => 8000,
            "wall-time" => 0.092,
            "exitsig" => 0,
            "message" => "This is a random message",
            "status" => "OK",
            "time" => 0.037,
            "killed" => false
        ]
    ];

    /** @var Loader */
    private $builder;

    public function __construct()
    {
        $this->builder = new Loader();
    }

    public function testOKTest()
    {
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

        $execRes = [new ExecutionTaskResult(self::$execRes)];
        $evalRes = new EvaluationTaskResult(self::$evalRes);

        $res = new TestResult($cfg, $execRes, $evalRes, "A");
        Assert::equal("some ID", $res->getId());
        Assert::equal(TestResult::STATUS_FAILED, $res->getStatus());
        Assert::equal(0.0, $res->getScore());
        Assert::true($res->didExecutionMeetLimits());
        Assert::true($res->isMemoryOK());
        Assert::true($res->isWallTimeOK());
        Assert::true($res->isCpuTimeOK());
        Assert::same(10, $res->getExitCode());
        Assert::same(8000, $res->getUsedMemory());
        Assert::same(10000, $res->getUsedMemoryLimit());
        Assert::same(0.092, $res->getUsedWallTime());
        Assert::same(1.0, $res->getUsedWallTimeLimit());
        Assert::same(0.037, $res->getUsedCpuTime());
        Assert::same(0.0, $res->getUsedCpuTimeLimit());
        Assert::same("This is a random message", $res->getMessage());
        Assert::same("", $res->getJudgeOutput());
    }

}

# Testing methods run
$testCase = new TestFailedTestResult();
$testCase->run();
