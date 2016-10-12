<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\TestConfig;
use App\Helpers\JobConfig\Tasks\TaskBase;
use App\Helpers\JobConfig\Tasks\ExternalTask;
use App\Exceptions\JobConfigLoadingException;


class FakeTask extends TaskBase {
  public function __construct(array $data) {
    $data["priority"] = 1;
    $data["fatal-failure"] = true;
    $data["cmd"] = [];
    $data["cmd"]["bin"] = "cmd";

    parent::__construct($data);
  }
}

class FakeExternalTask extends ExternalTask {
  public function __construct(array $data) {
    $data["priority"] = 1;
    $data["fatal-failure"] = true;
    $data["cmd"] = [];
    $data["cmd"]["bin"] = "cmd";

    parent::__construct($data);
  }
}


class TestTestResult extends Tester\TestCase
{

  static $evaluation = [
    "task-id" => "X",
    "test-id" => "A",
    "type" => "evaluation"
  ];
  static $execution = [
    "task-id" => "Y",
    "test-id" => "A",
    "type" => "execution",
    "sandbox" => [
      "name" => "sandboxName",
      "limits" => []
    ]
  ];

  public function testMissingExecutionOrEvaluationTask() {
    Assert::exception(function () {
      new TestConfig(
        "some ID",
        [
          new FakeTask([ "task-id" => "A" ]),
          new FakeTask([ "task-id" => "B" ]),
          new FakeTask([ "task-id" => "C" ]),
          new FakeTask([ "task-id" => "D" ])
        ]
      );
    }, JobConfigLoadingException::CLASS);

    Assert::exception(function () {
      new TestConfig(
        "some ID",
        [
          new FakeTask([ "task-id" => "A" ]),
          new FakeExternalTask(self::$execution),
          new FakeTask([ "task-id" => "C" ]),
          new FakeTask([ "task-id" => "D" ])
        ]
      );
    }, JobConfigLoadingException::CLASS);

    Assert::exception(function () {
      new TestConfig(
        "some ID",
        [
          new FakeTask([ "task-id" => "A" ]),
          new FakeTask([ "task-id" => "B" ]),
          new FakeTask(self::$evaluation),
          new FakeTask([ "task-id" => "D" ])
        ]
      );
    }, JobConfigLoadingException::CLASS);
  }

  public function testBothExecutionOrEvaluationTasksPresent() {
    $cfg = new TestConfig(
      "some ID",
      [
        new FakeTask([ "task-id" => "A" ]),
        new FakeExternalTask(self::$execution),
        new FakeTask([ "task-id" => "C" ]),
        new FakeTask(self::$evaluation),
        new FakeTask([ "task-id" => "D" ])
      ]
    );

    Assert::equal("some ID", $cfg->getId());
  }

  public function testExecutionOrEvaluationTasksAvailability() {
    $exec = new FakeExternalTask(self::$execution);
    $eval = new FakeTask(self::$evaluation);

    $cfg = new TestConfig(
      "some ID",
      [
          new FakeTask([ "task-id" => "A" ]),
          $exec,
          new FakeTask([ "task-id" => "C" ]),
          $eval,
          new FakeTask([ "task-id" => "D" ])
      ]
    );

    Assert::true($cfg->getExecutionTask()->isExecutionTask());
    Assert::equal("Y", $cfg->getExecutionTask()->getId());
    Assert::equal("X", $cfg->getEvaluationTask()->getId());
  }


}

# Testing methods run
$testCase = new TestTestResult;
$testCase->run();
