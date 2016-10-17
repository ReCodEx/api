<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\Tasks\ExternalTask;
use App\Helpers\JobConfig\Tasks\ExecutionTaskType;
use App\Helpers\JobConfig\Tasks\EvaluationTaskType;
use App\Helpers\JobConfig\Tasks\InitiationTaskType;
use App\Exceptions\JobConfigLoadingException;

class FakeExternalTask extends ExternalTask {
  public function __construct(array $data) {
    $data["task-id"] = "id";
    $data["priority"] = 1;
    $data["fatal-failure"] = true;
    $data["cmd"] = [];
    $data["cmd"]["bin"] = "cmd";

    $data["sandbox"] = [];
    $data["sandbox"]["name"] = "sandboxName";
    $data["sandbox"]["limits"] = [];
    $data["sandbox"]["limits"][] = [ "hw-group-id" => "groupA", "time" => 1 ];
    $data["sandbox"]["limits"][] = [ "hw-group-id" => "groupB", "time" => 2 ];

    parent::__construct($data);
  }
}


class TestTaskTypes extends Tester\TestCase
{

  public function testBadTaskTypes() {
    Assert::exception(function() {
      new InitiationTaskType(new FakeExternalTask(["type" => "execution"]));
    }, JobConfigLoadingException::class);

    Assert::exception(function() {
      new ExecutionTaskType(new FakeExternalTask(["type" => "evaluation"]));
    }, JobConfigLoadingException::class);

    Assert::exception(function() {
      new EvaluationTaskType(new FakeExternalTask(["type" => "initiation"]));
    }, JobConfigLoadingException::class);
  }

  public function testParsingInitEval() {
    $initiation = new InitiationTaskType(new FakeExternalTask(["type" => "initiation"]));
    Assert::true($initiation->getTask()->isInitiationTask());

    $evaluation = new EvaluationTaskType(new FakeExternalTask(["type" => "evaluation"]));
    Assert::true($evaluation->getTask()->isEvaluationTask());
  }

  public function testParsingExecution() {
    $execution = new ExecutionTaskType(new FakeExternalTask(["type" => "execution"]));
    Assert::true($execution->getTask()->isExecutionTask());

    Assert::equal("groupA", $execution->getLimits("groupA")->getId());
    Assert::equal(1.0, $execution->getLimits("groupA")->getTimeLimit());

    Assert::equal("groupB", $execution->getLimits("groupB")->getId());
    Assert::equal(2.0, $execution->getLimits("groupB")->getTimeLimit());
  }

}

# Testing methods run
$testCase = new TestTaskTypes;
$testCase->run();
