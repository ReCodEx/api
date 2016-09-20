<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\JobConfig;
use App\Helpers\JobConfig\TaskConfig;
use App\Helpers\EvaluationResults\EvaluationResults;
use App\Exceptions\JobConfigLoadingException;
use App\Exceptions\ResultsLoadingException;

class TestEvaluationResults extends Tester\TestCase
{

  static $jobConfig = [
    "submission" => [
      "job-id" => "bla bla bla"
    ],
    "tasks" => [
      [ "task-id" => "X", "test-id" => "A", "type" => "evaluation" ],
      [
        "task-id" => "Y", "test-id" => "A", "type" => "execution",
        "sandbox" => [ "limits" => [[ "hw-group-id" => "A", "memory" => 123, "time" => 456 ]] ]
      ]
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
        [ "task-id" => "A", "type" => TaskConfig::TYPE_INITIATION, "status" => "OK" ],
        [ "task-id" => "B", "type" => TaskConfig::TYPE_INITIATION, "status" => "OK" ],
        [ "task-id" => "C", "type" => TaskConfig::TYPE_INITIATION, "status" => "OK" ],
        [ "task-id" => "D", "type" => TaskConfig::TYPE_INITIATION, "status" => "OK" ],
        [ "task-id" => "E", "type" => TaskConfig::TYPE_INITIATION, "status" => "OK" ]
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


  // @todo: Test 'getTestResults($hardwareGroupId)' function

}

# Testing methods run
$testCase = new TestEvaluationResults;
$testCase->run();
