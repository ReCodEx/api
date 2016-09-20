<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\EvaluationResults\EvaluationTaskResult;
use App\Helpers\EvaluationResults\Stats;
use App\Exceptions\ResultsLoadingException;

class TestEvaluationTask extends Tester\TestCase
{

  public function testMissingRequiredParams() {
    Assert::exception(function() { new EvaluationTaskResult([]); }, ResultsLoadingException::CLASS);
    Assert::exception(function() { new EvaluationTaskResult([ 'task-id' => 'ABC' ]); }, ResultsLoadingException::CLASS);
    Assert::exception(function() { new EvaluationTaskResult([ 'status' => 'XYZ' ]); }, ResultsLoadingException::CLASS);
    Assert::noError(function() { new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'judge_output' => NULL ]); });
    Assert::noError(function() { new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'judge_output' => "" ]); });
    Assert::noError(function() { new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'judge_output' => "123" ]); });
  }

  public function testWrongJudgeOutputDetection() {
    Assert::exception(function() { new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'judge_output' => "abc" ]); }, ResultsLoadingException::CLASS);
    Assert::exception(function() { new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'judge_output' => "a1" ]); }, ResultsLoadingException::CLASS);
    Assert::exception(function() { new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'judge_output' => "1a" ]); }, ResultsLoadingException::CLASS);
    Assert::exception(function() { new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'judge_output' => "1e" ]); }, ResultsLoadingException::CLASS);
    Assert::exception(function() { new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'judge_output' => "1,0" ]); }, ResultsLoadingException::CLASS);
    Assert::noError(function() { new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'judge_output' => "1.0" ]); });
    Assert::noError(function() { new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'judge_output' => "10" ]); });
    Assert::noError(function() { new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'judge_output' => "1" ]); });
    Assert::noError(function() { new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'judge_output' => "0123" ]); });
    Assert::noError(function() { new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'judge_output' => "0123" ]); });
    Assert::noError(function() { new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'judge_output' => "-123" ]); });
  }

  public function testParsingParams() {
    $result = new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'judge_output' => "123" ]);
    Assert::same("ABC", $result->getId());
    Assert::same("XYZ", $result->getStatus());
    Assert::equal("123", $result->getJudgeOutput());
  }

  public function testScoreCalculation() {
    $judgeToScore = [
      "0.123" => 0.123,
      "0.456000" => 0.456,
      "0.123 ahoj" => 0.123,
      "123" => 1.0,
      "-0.123" => 0.0
    ];

    foreach ($judgeToScore as $judgeOutput => $score) {
      $result = new EvaluationTaskResult([ 'task-id' => 'ABC', 'status' => 'XYZ', 'judge_output' => $judgeOutput ]);
      Assert::same($score, $result->getScore());
    }
  }

}

# Testing methods run
$testCase = new TestEvaluationTask;
$testCase->run();
