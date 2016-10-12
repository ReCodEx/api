<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\Tasks\ExternalTask;
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

  public function testMissingRequiredFields() {
    Assert::exception(function() { new ExternalTask([]); }, JobConfigLoadingException::class);
  }

  public function testMissingSandbox() {
    Assert::exception(function() {
      $data = self::$basic;
      unset($data["sandbox"]);
      new ExternalTask($data);
    }, JobConfigLoadingException::class);
  }

  public function testBasicTask() {
    $task = new ExternalTask(self::$basic);
    Assert::equal("A", $task->getId());
    Assert::equal(1, $task->getPriority());
    Assert::equal(true, $task->getFatalFailure());
    Assert::equal([], $task->getDependencies());
    Assert::equal("cmdA", $task->getCommandBinary());
    Assert::equal([], $task->getCommandArguments());
    Assert::equal(NULL, $task->getType());
    Assert::equal(NULL, $task->getTestId());
    Assert::equal("isolate", $task->getSandboxConfig()->getName());

    Assert::isEqual(self::$basic, $task->toArray());
  }
}

# Testing methods run
$testCase = new TestExternalTask;
$testCase->run();
