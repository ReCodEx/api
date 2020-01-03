<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\Loader;
use App\Helpers\JobConfig\SandboxConfig;
use App\Helpers\JobConfig\Tasks\Task;
use App\Helpers\JobConfig\Tasks\ExecutionTaskType;
use App\Helpers\JobConfig\Tasks\EvaluationTaskType;
use App\Helpers\JobConfig\Tasks\InitiationTaskType;
use App\Exceptions\JobConfigLoadingException;


class TestTaskTypes extends Tester\TestCase
{
    static $cfg = [
        "task-id" => "A",
        "priority" => "1",
        "fatal-failure" => "true",
        "cmd" => [
            "bin" => "cmdA"
        ],
        "sandbox" => [
            "name" => "isolate",
            "limits" => [
                ["hw-group-id" => "groupA", "time" => 1],
                ["hw-group-id" => "groupB", "time" => 2]
            ]
        ],
        "forward" => "compatibility"
    ];

    /** @var Loader */
    private $builder;

    public function __construct()
    {
        $this->builder = new Loader();
    }

    public function testBadTaskTypes()
    {
        Assert::exception(
            function () {
                new InitiationTaskType($this->builder->loadTask(self::$cfg)->setType("execution"));
            },
            JobConfigLoadingException::class
        );

        Assert::exception(
            function () {
                new ExecutionTaskType($this->builder->loadTask(self::$cfg)->setType("evaluation"));
            },
            JobConfigLoadingException::class
        );

        Assert::exception(
            function () {
                new EvaluationTaskType($this->builder->loadTask(self::$cfg)->setType("initiation"));
            },
            JobConfigLoadingException::class
        );
    }

    public function testParsingInitEval()
    {
        $initiation = new InitiationTaskType($this->builder->loadTask(self::$cfg)->setType("initiation"));
        Assert::true($initiation->getTask()->isInitiationTask());

        $evaluation = new EvaluationTaskType($this->builder->loadTask(self::$cfg)->setType("evaluation"));
        Assert::true($evaluation->getTask()->isEvaluationTask());
    }

    public function testParsingExecution()
    {
        $execution = new ExecutionTaskType($this->builder->loadTask(self::$cfg)->setType("execution"));
        Assert::true($execution->getTask()->isExecutionTask());

        Assert::equal("groupA", $execution->getLimits("groupA")->getId());
        Assert::equal(1.0, $execution->getLimits("groupA")->getTimeLimit());

        Assert::equal("groupB", $execution->getLimits("groupB")->getId());
        Assert::equal(2.0, $execution->getLimits("groupB")->getTimeLimit());
    }

}

# Testing methods run
$testCase = new TestTaskTypes();
$testCase->run();
