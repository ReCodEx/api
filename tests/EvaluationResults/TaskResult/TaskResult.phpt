<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\EvaluationResults\TaskResult;
use App\Exceptions\ResultsLoadingException;

class TestTaskResult extends Tester\TestCase
{

  public function testMissingRequiredParams() {
    Assert::exception(function() { new TaskResult([]); }, ResultsLoadingException::CLASS);
    Assert::exception(function() { new TaskResult([ 'task-id' => 'ABC' ]); }, ResultsLoadingException::CLASS);
    Assert::exception(function() { new TaskResult([ 'status' => 'XYZ' ]); }, ResultsLoadingException::CLASS);
    Assert::noError(function() { new TaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ' ]); });
  }

  public function testParsingParams() {
    $result = new TaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ' ]);
    Assert::same("ABC", $result->getId());
    Assert::same("XYZ", $result->getStatus());
  }

  public function testStatusOK() {
    $result = new TaskResult([ 'task-id' => 'ABC', 'status' => TaskResult::STATUS_OK ]);
    Assert::true($result->isOK());
    Assert::equal(TaskResult::MAX_SCORE, $result->getScore());
  }

  public function testStatusAny() {
    $result = new TaskResult([ 'task-id' => 'ABC', 'status' => 'AnyOther' ]);
    Assert::false($result->isOK());
    Assert::equal(TaskResult::MIN_SCORE, $result->getScore());
  }

  public function testStatusFailed() {
    $result = new TaskResult([ 'task-id' => 'ABC', 'status' => TaskResult::STATUS_FAILED ]);
    Assert::false($result->isOK());
    Assert::equal(TaskResult::MIN_SCORE, $result->getScore());
  }

  public function testStatusSkipped() {
    $result = new TaskResult([ 'task-id' => 'ABC', 'status' => TaskResult::STATUS_SKIPPED ]);
    Assert::false($result->isOK());
    Assert::equal(TaskResult::MIN_SCORE, $result->getScore());
  }

}

# Testing methods run
$testCase = new TestTaskResult();
$testCase->run();
