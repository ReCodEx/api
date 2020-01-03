<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\Loader;
use App\Exceptions\JobConfigLoadingException;


class TestTaskBase extends Tester\TestCase
{
    static $basic = [
        "task-id" => "A",
        "priority" => 1,
        "fatal-failure" => true,
        "cmd" => [
            "bin" => "cmdA"
        ],
        "forward" => "compatibility"
    ];
    static $initiation = [
        "task-id" => "B",
        "test-id" => "X",
        "priority" => 2,
        "fatal-failure" => false,
        "cmd" => [
            "bin" => "cmdB"
        ],
        "type" => "initiation"
    ];
    static $evaluation = [
        "task-id" => "C",
        "test-id" => "Y",
        "priority" => 3,
        "fatal-failure" => true,
        "cmd" => [
            "bin" => "cmdC"
        ],
        "type" => "evaluation"
    ];
    static $execution = [
        "task-id" => "D",
        "test-id" => "Z",
        "priority" => 4,
        "fatal-failure" => false,
        "cmd" => [
            "bin" => "cmdD"
        ],
        "type" => "execution"
    ];
    static $optional = [
        "task-id" => "optional",
        "test-id" => "testOptional",
        "priority" => 5,
        "fatal-failure" => true,
        "dependencies" => [
            "depA",
            "depB",
            "depC"
        ],
        "cmd" => [
            "bin" => "cmdOptional",
            "args" => [
                "argA",
                "argB",
                "argC"
            ]
        ],
        "type" => "execution"
    ];

    /** @var Loader */
    private $builder;

    public function __construct()
    {
        $this->builder = new Loader();
    }

    public function testMissingRequiredFields()
    {
        Assert::exception(
            function () {
                $this->builder->loadTask([]);
            },
            JobConfigLoadingException::class
        );
    }

    public function testBasicTask()
    {
        $task = $this->builder->loadTask(self::$basic);
        Assert::equal("A", $task->getId());
        Assert::equal(1, $task->getPriority());
        Assert::equal(true, $task->getFatalFailure());
        Assert::equal([], $task->getDependencies());
        Assert::equal("cmdA", $task->getCommandBinary());
        Assert::equal([], $task->getCommandArguments());
        Assert::equal(null, $task->getType());
        Assert::equal(null, $task->getTestId());

        Assert::equal(self::$basic, $task->toArray());
    }

    public function testInitiationTask()
    {
        $task = $this->builder->loadTask(self::$initiation);
        Assert::equal("B", $task->getId());
        Assert::equal(2, $task->getPriority());
        Assert::equal(false, $task->getFatalFailure());
        Assert::equal([], $task->getDependencies());
        Assert::equal("cmdB", $task->getCommandBinary());
        Assert::equal([], $task->getCommandArguments());
        Assert::true($task->isInitiationTask());
        Assert::equal("X", $task->getTestId());

        Assert::equal(self::$initiation, $task->toArray());
    }

    public function testEvaluationTask()
    {
        $task = $this->builder->loadTask(self::$evaluation);
        Assert::equal("C", $task->getId());
        Assert::equal(3, $task->getPriority());
        Assert::equal(true, $task->getFatalFailure());
        Assert::equal([], $task->getDependencies());
        Assert::equal("cmdC", $task->getCommandBinary());
        Assert::equal([], $task->getCommandArguments());
        Assert::true($task->isEvaluationTask());
        Assert::equal("Y", $task->getTestId());

        Assert::equal(self::$evaluation, $task->toArray());
    }

    public function testExecutionTask()
    {
        $task = $this->builder->loadTask(self::$execution);
        Assert::equal("D", $task->getId());
        Assert::equal(4, $task->getPriority());
        Assert::equal(false, $task->getFatalFailure());
        Assert::equal([], $task->getDependencies());
        Assert::equal("cmdD", $task->getCommandBinary());
        Assert::equal([], $task->getCommandArguments());
        Assert::true($task->isExecutionTask());
        Assert::equal("Z", $task->getTestId());

        Assert::equal(self::$execution, $task->toArray());
    }

    public function testOptionalTask()
    {
        $task = $this->builder->loadTask(self::$optional);
        Assert::equal("optional", $task->getId());
        Assert::equal(5, $task->getPriority());
        Assert::equal(true, $task->getFatalFailure());
        Assert::equal(["depA", "depB", "depC"], $task->getDependencies());
        Assert::equal("cmdOptional", $task->getCommandBinary());
        Assert::equal(["argA", "argB", "argC"], $task->getCommandArguments());
        Assert::equal("execution", $task->getType());
        Assert::equal("testOptional", $task->getTestId());

        Assert::equal(self::$optional, $task->toArray());
    }
}

# Testing methods run
$testCase = new TestTaskBase();
$testCase->run();
