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


class TestSkippedTestResult extends Tester\TestCase
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
                    "memory" => 8096,
                    "time" => 1.0
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
        "status" => TaskResult::STATUS_SKIPPED
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
        Assert::equal(TestResult::STATUS_SKIPPED, $res->getStatus());
        Assert::equal(0.0, $res->getScore());
        Assert::false($res->didExecutionMeetLimits());
        Assert::false($res->isMemoryOK());
        Assert::false($res->isWallTimeOK());
        Assert::false($res->isCpuTimeOK());
        Assert::same(\App\Helpers\EvaluationResults\ISandboxResults::EXIT_CODE_UNKNOWN, $res->getExitCode());
        Assert::same(0, $res->getUsedMemory());
        Assert::same(8096, $res->getUsedMemoryLimit());
        Assert::same(0.0, $res->getUsedWallTime());
        Assert::same(0.0, $res->getUsedWallTimeLimit());
        Assert::same(0.0, $res->getUsedCpuTime());
        Assert::same(1.0, $res->getUsedCpuTimeLimit());
        Assert::same("", $res->getMessage());
        Assert::same("", $res->getJudgeStdout());
    }

}

# Testing methods run
$testCase = new TestSkippedTestResult();
$testCase->run();
