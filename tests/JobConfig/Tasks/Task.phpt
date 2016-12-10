<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\Builder;
use App\Exceptions\JobConfigLoadingException;


class TestTaskBase extends Tester\TestCase
{
  static $basic = [
    "task-id" => "A",
    "priority" => "1",
    "fatal-failure" => "true",
    "cmd" => [
      "bin" => "cmdA"
    ],
    "forward" => "compatibility"
  ];
  static $initiation = [
    "task-id" => "B",
    "test-id" => "X",
    "priority" => "2",
    "fatal-failure" => "false",
    "cmd" => [
      "bin" => "cmdB"
    ],
    "type" => "initiation"
  ];
  static $evaluation = [
    "task-id" => "C",
    "test-id" => "Y",
    "priority" => "3",
    "fatal-failure" => "true",
    "cmd" => [
      "bin" => "cmdC"
    ],
    "type" => "evaluation"
  ];
  static $execution = [
    "task-id" => "D",
    "test-id" => "Z",
    "priority" => "4",
    "fatal-failure" => "false",
    "cmd" => [
      "bin" => "cmdD"
    ],
    "type" => "execution"
  ];
  static $optional = [
    "task-id" => "optional",
    "test-id" => "testOptional",
    "priority" => "5",
    "fatal-failure" => "true",
    "dependencies" => [
      "depA", "depB", "depC"
    ],
    "cmd" => [
      "bin" => "cmdOptional",
      "args" => [
        "argA", "argB", "argC"
      ]
    ],
    "type" => "execution"
  ];

  /** @var Builder */
  private $builder;

  public function __construct() {
    $this->builder = new Builder;
  }

  public function testMissingRequiredFields() {
    Assert::exception(function() { $this->builder->buildTask([]); }, JobConfigLoadingException::class);
  }

  public function testBasicTask() {
    $task = $this->builder->buildTask(self::$basic);
    Assert::equal("A", $task->getId());
    Assert::equal(1, $task->getPriority());
    Assert::equal(true, $task->getFatalFailure());
    Assert::equal([], $task->getDependencies());
    Assert::equal("cmdA", $task->getCommandBinary());
    Assert::equal([], $task->getCommandArguments());
    Assert::equal(NULL, $task->getType());
    Assert::equal(NULL, $task->getTestId());

    Assert::isEqual(self::$basic, $task->toArray());
  }

  public function testInitiationTask() {
    $task = $this->builder->buildTask(self::$initiation);
    Assert::equal("B", $task->getId());
    Assert::equal(2, $task->getPriority());
    Assert::equal(false, $task->getFatalFailure());
    Assert::equal([], $task->getDependencies());
    Assert::equal("cmdB", $task->getCommandBinary());
    Assert::equal([], $task->getCommandArguments());
    Assert::true($task->isInitiationTask());
    Assert::equal("X", $task->getTestId());

    Assert::isEqual(self::$initiation, $task->toArray());
  }

  public function testEvaluationTask() {
    $task = $this->builder->buildTask(self::$evaluation);
    Assert::equal("C", $task->getId());
    Assert::equal(3, $task->getPriority());
    Assert::equal(true, $task->getFatalFailure());
    Assert::equal([], $task->getDependencies());
    Assert::equal("cmdC", $task->getCommandBinary());
    Assert::equal([], $task->getCommandArguments());
    Assert::true($task->isEvaluationTask());
    Assert::equal("Y", $task->getTestId());

    Assert::isEqual(self::$evaluation, $task->toArray());
  }

  public function testExecutionTask() {
    $task = $this->builder->buildTask(self::$execution);
    Assert::equal("D", $task->getId());
    Assert::equal(4, $task->getPriority());
    Assert::equal(false, $task->getFatalFailure());
    Assert::equal([], $task->getDependencies());
    Assert::equal("cmdD", $task->getCommandBinary());
    Assert::equal([], $task->getCommandArguments());
    Assert::true($task->isExecutionTask());
    Assert::equal("Z", $task->getTestId());

    Assert::isEqual(self::$execution, $task->toArray());
  }

  public function testOptionalTask() {
    $task = $this->builder->buildTask(self::$optional);
    Assert::equal("optional", $task->getId());
    Assert::equal(5, $task->getPriority());
    Assert::equal(true, $task->getFatalFailure());
    Assert::isEqual(["depA", "depB", "depC"], $task->getDependencies());
    Assert::equal("cmdOptional", $task->getCommandBinary());
    Assert::isEqual(["argA", "argB", "argC"], $task->getCommandArguments());
    Assert::equal("execution", $task->getType());
    Assert::equal("testOptional", $task->getTestId());

    Assert::isEqual(self::$optional, $task->toArray());
  }
}

# Testing methods run
$testCase = new TestTaskBase;
$testCase->run();
