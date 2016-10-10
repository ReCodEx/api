<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Model\Entity\SubmissionEvaluation;
use App\Helpers\JobConfig\TaskConfig;
use App\Helpers\JobConfig\ExecutionTaskConfig;
use App\Helpers\JobConfig\EvaluationTaskConfig;
use App\Helpers\JobConfig\Limits;
use App\Helpers\JobConfig\InfiniteLimits;
use App\Exceptions\JobConfigLoadingException;
use Symfony\Component\Yaml\Yaml;

class TestTaskConfig extends Tester\TestCase
{
  static $basic = [ "task-id" => "B" ];
  static $initiation = [ "task-id" => "W", "type" => "initiation" ];
  static $evaluation = [ "task-id" => "X", "test-id" => "A", "type" => "evaluation" ];
  static $execution = [
    "task-id" => "Y", "test-id" => "A", "type" => "execution",
    "sandbox" => [
      "name" => "isolate",
      "limits" => [
        [ "hw-group-id" => "A", "memory" => 123, "time" => 456 ]
      ]
    ]
  ];

  public function testMissingRequiredFields() {
    Assert::exception(function() { new TaskConfig([]); }, JobConfigLoadingException::class);
  }

  public function testBasicTask() {
    $task = new TaskConfig(self::$basic);
    Assert::equal("B", $task->getId());
    Assert::equal(NULL, $task->getTestId());
    Assert::equal(FALSE, $task->isInitiationTask());
    Assert::equal(FALSE, $task->isExecutionTask());
    Assert::equal(FALSE, $task->isEvaluationTask());
    Assert::exception(function() use ($task) { $task->getAsExecutionTask(); }, JobConfigLoadingException::class);
  }

  public function testInitiationTask() {
    $task = new TaskConfig(self::$initiation);
    Assert::equal("W", $task->getId());
    Assert::equal(NULL, $task->getTestId());
    Assert::equal(TRUE, $task->isInitiationTask());
    Assert::equal(FALSE, $task->isExecutionTask());
    Assert::equal(FALSE, $task->isEvaluationTask());
    Assert::exception(function() use ($task) { $task->getAsExecutionTask(); }, JobConfigLoadingException::class);
  }

  public function testEvaluationTask() {
    $task = new TaskConfig(self::$evaluation);
    Assert::equal("X", $task->getId());
    Assert::equal("A", $task->getTestId());
    Assert::equal(FALSE, $task->isInitiationTask());
    Assert::equal(FALSE, $task->isExecutionTask());
    Assert::equal(TRUE, $task->isEvaluationTask());
    Assert::exception(function() use ($task) { $task->getAsExecutionTask(); }, JobConfigLoadingException::class);
  }

  public function testExecutionTask() {
    $task = new TaskConfig(self::$execution);
    Assert::equal("Y", $task->getId());
    Assert::equal("A", $task->getTestId());
    Assert::equal(FALSE, $task->isInitiationTask());
    Assert::equal(TRUE, $task->isExecutionTask());
    Assert::equal(FALSE, $task->isEvaluationTask());

    $exec = $task->getAsExecutionTask();
    Assert::type(ExecutionTaskConfig::class, $exec);
    Assert::type(Limits::class, $exec->getLimits("A"));
    Assert::exception(function() use ($exec) { $exec->getLimits("B"); }, JobConfigLoadingException::class);
  }

  public function testRemoveLimits() {
    $task = new TaskConfig(self::$execution);
    $exec = $task->getAsExecutionTask();
    $exec->removeLimits("A");
    Assert::type(InfiniteLimits::class, $exec->getLimits("A"));
  }

  public function testRemovedLimitsSerialization() {
    $task = new TaskConfig(self::$execution);
    $exec = $task->getAsExecutionTask();
    $exec->removeLimits("A");

    $expected = [
      "task-id" => "Y", "test-id" => "A", "type" => "execution",
      "sandbox" => [
        "name" => "isolate",
        "limits" => [
          [
            "hw-group-id" => "A",
            "memory" => InfiniteLimits::INFINITE_MEMORY,
            "time" => InfiniteLimits::INFINITE_TIME
          ]
        ]
      ]
    ];

    $deserialized = Yaml::parse((string) $exec);
    Assert::equal($expected, $deserialized);
  }

  public function testSetLimits() {
    $task = new TaskConfig(self::$execution);
    $limits = new Limits([ "hw-group-id" => "another", "memory" => "987", "time" => "654" ]);

    $exec = $task->getAsExecutionTask();
    $exec->setLimits($limits->getId(), $limits);
    Assert::type(Limits::class, $exec->getLimits("A"));
    Assert::type(Limits::class, $exec->getLimits("another"));
    Assert::equal($limits, $exec->getLimits("another"));
  }

  public function testGetSandboxName() {
    $task = new TaskConfig(self::$execution);
    $exec = $task->getAsExecutionTask();
    Assert::equal("isolate", $exec->getSandboxName());
  }
}

# Testing methods run
$testCase = new TestTaskConfig;
$testCase->run();
