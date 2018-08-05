<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\Tasks\Task;
use App\Helpers\JobConfig\Loader;
use App\Exceptions\JobConfigLoadingException;


class TestInternalTask extends Tester\TestCase
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

  /** @var Loader */
  private $builder;

  public function __construct() {
    $this->builder = new Loader();
  }

  public function testMissingRequiredFields() {
    Assert::exception(function() { $this->builder->loadTask([]); }, JobConfigLoadingException::class);
  }

  public function testBasicTask() {
    $task = $this->builder->loadTask(self::$basic);
    Assert::equal("A", $task->getId());
    Assert::equal(1, $task->getPriority());
    Assert::equal(true, $task->getFatalFailure());
    Assert::equal([], $task->getDependencies());
    Assert::equal("cmdA", $task->getCommandBinary());
    Assert::equal([], $task->getCommandArguments());
    Assert::equal(null, $task->getType());
    Assert::equal(null, $task->getTestId());
    Assert::false($task->isSandboxedTask());

    Assert::isEqual(self::$basic, $task->toArray());
  }
}

# Testing methods run
$testCase = new TestInternalTask();
$testCase->run();
