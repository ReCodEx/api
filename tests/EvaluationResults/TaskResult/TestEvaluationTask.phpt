<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\EvaluationResults\EvaluationTaskResult;
use App\Helpers\EvaluationResults\Stats;
use App\Exceptions\ResultsLoadingException;

/**
 * @testCase
 */
class TestEvaluationTask extends Tester\TestCase
{

  public function testMissingRequiredParams() {
    Assert::exception(function() { new EvaluationTaskResult([]); }, ResultsLoadingException::CLASS);
    Assert::exception(function() { new EvaluationTaskResult([ 'task-id' => 'ABC' ]); }, ResultsLoadingException::CLASS);
    Assert::exception(function() { new EvaluationTaskResult([ 'status' => 'XYZ' ]); }, ResultsLoadingException::CLASS);
    Assert::noError(function() { new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'output' => null ]); });
    Assert::noError(function() { new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'output' => "" ]); });
    Assert::noError(function() { new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'output' => "123" ]); });
  }

  public function testParsingParams() {
    $result = new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'output' => [ "stdout" => "123", "stderr" => "456" ] ]);
    Assert::same("ABC", $result->getId());
    Assert::same("XYZ", $result->getStatus());
    Assert::equal("123\n456", $result->getOutput());
  }

  public function testScoreCalculation() {
    $judgeToScore = [
      "abc" => 0.0,
      "a" => 0.0,
      "abc 1.0" => 0.0,
      "0.123" => 0.123,
      "0.456000" => 0.456,
      "0.456000\nHello world" => 0.456,
      "0.456000\twhoosh" => 0.456,
      "0.123 ahoj" => 0.123,
      "123" => 1.0,
      "-0.123" => 0.0,
      "" => 0.0,
      "FALSE" => 0.0,
      "TRUE" => 0.0
    ];

    foreach ($judgeToScore as $judgeOutput => $score) {
      $result = new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'output' => [ "stdout" => $judgeOutput ] ]);
      Assert::same($score, $result->getScore());
    }
  }

}

# Testing methods run
$testCase = new TestEvaluationTask;
$testCase->run();
