<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\Tasks\Task;
use App\Helpers\JobConfig\Builder;
use App\Exceptions\JobConfigLoadingException;


class TestExternalTask extends Tester\TestCase
{
  static $basic = [
    "task-id" => "A",
    "priority" => "1",
    "fatal-failure" => "true",
    "cmd" => [
      "bin" => "cmdA"
    ],
    "sandbox" => [ "name" => "isolate", "limits" => [ [ "hw-group-id" => "A", "memory" => 123, "time" => 456 ] ] ],
    "forward" => "compatibility"
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
    Assert::true($task->isSandboxedTask());
    Assert::equal("isolate", $task->getSandboxConfig()->getName());

    Assert::isEqual(self::$basic, $task->toArray());
  }
}

# Testing methods run
$testCase = new TestExternalTask;
$testCase->run();
