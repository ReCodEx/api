<?php

include '../../bootstrap.php';

use Tester\Assert;

use App\Helpers\JobConfig\JobConfig;
use App\Helpers\JobConfig\TaskConfig;

use App\Helpers\EvaluationResults\EvaluationResults;
use App\Helpers\EvaluationResults\TaskResult;
use App\Helpers\EvaluationResults\TestResult;

use App\Exceptions\JobConfigLoadingException;
use App\Exceptions\ResultsLoadingException;

class TestEvaluationResults extends Tester\TestCase
{

  static $jobConfig = [
    "submission" => [
      "job-id" => "bla bla bla"
    ],
    "tasks" => [
      [ "task-id" => "W", "type" => TaskConfig::TYPE_INITIATION ],
      [
        "task-id" => "X", "test-id" => "A", "type" => TaskConfig::TYPE_EXECUTION,
        "sandbox" => [ "limits" => [[ "hw-group-id" => "A", "memory" => 123, "time" => 456 ]] ]
      ],
      [ "task-id" => "Y", "test-id" => "A", "type" => TaskConfig::TYPE_EVALUATION ]
    ]
  ];

  public function testMissingParams() {
    $jobConfig = new JobConfig(self::$jobConfig);
    
    Assert::exception(function () use ($jobConfig) {
      new EvaluationResults([], $jobConfig);
    }, ResultsLoadingException::CLASS);
    
    Assert::exception(function () use ($jobConfig) {
      new EvaluationResults([ "job-id" => "ratata" ], $jobConfig);
    }, ResultsLoadingException::CLASS);
    
    Assert::exception(function () use ($jobConfig) {
      new EvaluationResults([
        "job-id" => "bla bla bla"
      ], $jobConfig);
    }, ResultsLoadingException::CLASS);
    
    Assert::exception(function () use ($jobConfig) {
      new EvaluationResults([
        "job-id" => "bla bla bla",
        "results" => NULL
      ], $jobConfig);
    }, ResultsLoadingException::CLASS);
    
    Assert::noError(function () use ($jobConfig) {
      new EvaluationResults([
        "job-id" => "bla bla bla",
        "results" => []
      ], $jobConfig);
    });
    
    Assert::exception(function () use ($jobConfig) {
      new EvaluationResults([
        "job-id" => "bla bla bla",
        "results" => [ [ "a" => "b" ] ]
      ], $jobConfig);
    }, ResultsLoadingException::CLASS);
  }

  public function testInitialisationOK() {
    $jobConfig = new JobConfig(self::$jobConfig);
    $results = new EvaluationResults([
      "job-id" => "bla bla bla",
      "results" => [
        [ "task-id" => "W", "status" => "OK" ],
        [ "task-id" => "X", "status" => "OK" ],
        [ "task-id" => "Y", "status" => "OK" ]
      ]
    ], $jobConfig);

    Assert::true($results->initOK());
  }

  public function testInitialisationFailedBecauseOfSkippedTask() {
    $jobConfig = new JobConfig([
      "submission" => [
        "job-id" => "bla bla bla"
      ],
      "tasks" => [
        [ "task-id" => "A", "type" => TaskConfig::TYPE_INITIATION ],
        [ "task-id" => "B", "type" => TaskConfig::TYPE_INITIATION ]
      ]
    ]);
    $results = new EvaluationResults([
      "job-id" => "bla bla bla",
      "results" => [
        [ "task-id" => "A", "status" => "OK" ],
        [ "task-id" => "B", "status" => "SKIPPED" ]
      ]
    ], $jobConfig);

    Assert::false($results->initOK());
  }

  public function testInitialisationFailedBecauseOfFailedTask() {
    $jobConfig = new JobConfig([
      "submission" => [
        "job-id" => "bla bla bla"
      ],
      "tasks" => [
        [ "task-id" => "A", "type" => TaskConfig::TYPE_INITIATION ],
        [ "task-id" => "B", "type" => TaskConfig::TYPE_INITIATION ]
      ]
    ]);
    $results = new EvaluationResults([
      "job-id" => "bla bla bla",
      "results" => [
        [ "task-id" => "A", "status" => "OK" ],
        [ "task-id" => "B", "status" => "FAILED" ]
      ]
    ], $jobConfig);

    Assert::false($results->initOK());
  }

  public function testInitialisationFailedBecauseOfMissingTaskInitResult() {
    $jobConfig = new JobConfig([
      "submission" => [
        "job-id" => "bla bla bla"
      ],
      "tasks" => [
        [ "task-id" => "A", "type" => TaskConfig::TYPE_INITIATION ],
        [ "task-id" => "B", "type" => TaskConfig::TYPE_INITIATION ]
      ]
    ]);
    $results = new EvaluationResults([
      "job-id" => "bla bla bla",
      "results" => [
        [ "task-id" => "A", "status" => "OK" ]
      ]
    ], $jobConfig);

    Assert::false($results->initOK());
  }


  public function testSimpleGetTestResult() {
    $jobConfig = new JobConfig(self::$jobConfig);
    $initRes = [ "task-id" => "W", "status" => "OK" ];
    $execRes = [
      "task-id" => "X",
      "status" => "OK",
      "sandbox_results" => [
        "exitcode"  => 0,
        "max-rss"   => 19696,
        "memory"    => 100,
        "wall-time" => 0.092,
        "exitsig"   => 0,
        "message"   => "This is a random message",
        "status"    => "OK",
        "time"      => 0.037,
        "killed"    => false
      ]
    ];
    $evalRes = [ "task-id" => "Y", "status" => "OK", "judge_output" => "0.456" ]; 
    $results = new EvaluationResults([ "job-id" => "bla bla bla", "results" => [ $initRes, $evalRes, $execRes ] ], $jobConfig); 
    $testConfig = $jobConfig->getTests()["A"];

    $testResult = $results->getTestResult($testConfig, "A");
    Assert::type(TestResult::CLASS, $testResult);
    Assert::equal("A", $testResult->getId());
    Assert::equal("OK", $testResult->getStatus());
    Assert::equal(TRUE, $testResult->isMemoryOK());
    Assert::equal(TRUE, $testResult->isTimeOK());
    Assert::equal(TRUE, $testResult->didExecutionMeetLimits());
    Assert::equal("0.456", $testResult->getJudgeOutput());
    Assert::equal(0.456, $testResult->getScore());

    Assert::equal(1, count($results->getTestsResults("A")));
  }

}

# Testing methods run
$testCase = new TestEvaluationResults;
$testCase->run();
