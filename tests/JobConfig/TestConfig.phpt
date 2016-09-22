<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\TestConfig;
use App\Helpers\JobConfig\TaskConfig;
use App\Helpers\JobConfig\ExecutionTaskConfig;
use App\Helpers\JobConfig\EvaluationTaskConfig;
use App\Exceptions\JobConfigLoadingException;

class TestTestResult extends Tester\TestCase
{

  static $evaluation = [ "task-id" => "X", "test-id" => "A", "type" => "evaluation" ];
  static $execution = [
    "task-id" => "Y", "test-id" => "A", "type" => "execution",
    "sandbox" => [
      "limits" => [
        [ "hw-group-id" => "A", "memory" => 123, "time" => 456 ]
      ]
    ]
  ];

  public function testMissingExecutionOrEvaluationTask() {
    Assert::exception(function () {
      new TestConfig(
        "some ID",
        [
          new TaskConfig([ "task-id" => "A" ]),
          new TaskConfig([ "task-id" => "B" ]),
          new TaskConfig([ "task-id" => "C" ]),
          new TaskConfig([ "task-id" => "D" ])
        ]
      );
    }, JobConfigLoadingException::CLASS);
    
    Assert::exception(function () {
      new TestConfig(
        "some ID",
        [
          new TaskConfig([ "task-id" => "A" ]),
          new ExecutionTaskConfig(self::$execution),
          new TaskConfig([ "task-id" => "C" ]),
          new TaskConfig([ "task-id" => "D" ])
        ]
      );
    }, JobConfigLoadingException::CLASS);

    Assert::exception(function () {
      new TestConfig(
        "some ID",
        [
          new TaskConfig([ "task-id" => "A" ]),
          new TaskConfig([ "task-id" => "B" ]),
          new TaskConfig(self::$evaluation),
          new TaskConfig([ "task-id" => "D" ])
        ]
      );
    }, JobConfigLoadingException::CLASS);
  }

  public function testBothExecutionOrEvaluationTasksPresent() {
    $cfg = new TestConfig(
      "some ID",
      [
        new TaskConfig([ "task-id" => "A" ]),
        new ExecutionTaskConfig(self::$execution),
        new TaskConfig([ "task-id" => "C" ]),
        new TaskConfig(self::$evaluation),
        new TaskConfig([ "task-id" => "D" ])
      ]
    );

    Assert::equal("some ID", $cfg->getId());
  }

  public function testExecutionOrEvaluationTasksAvailability() {
    $exec = new ExecutionTaskConfig(self::$execution);
    $eval = new TaskConfig(self::$evaluation);

    $cfg = new TestConfig(
      "some ID",
      [
          new TaskConfig([ "task-id" => "A" ]),
          $exec,
          new TaskConfig([ "task-id" => "C" ]),
          $eval,
          new TaskConfig([ "task-id" => "D" ])
      ]
    );

    Assert::type(ExecutionTaskConfig::CLASS, $cfg->getExecutionTask());
    Assert::equal("Y", $cfg->getExecutionTask()->getId());
    Assert::equal($exec->getLimits("A"), $cfg->getLimits("A"));
    Assert::equal("X", $cfg->getEvaluationTask()->getId());
  }


}

# Testing methods run
$testCase = new TestTestResult;
$testCase->run();
