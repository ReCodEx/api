<?php

include '../../bootstrap.php';

use Tester\Assert;

use App\Helpers\JobConfig\Builder;
use App\Helpers\JobConfig\JobConfig;
use App\Helpers\JobConfig\Tasks\InitiationTaskType;
use App\Helpers\JobConfig\Tasks\ExecutionTaskType;
use App\Helpers\JobConfig\Tasks\EvaluationTaskType;

use App\Helpers\EvaluationResults\EvaluationResults;
use App\Helpers\EvaluationResults\TestResult;

use App\Exceptions\ResultsLoadingException;

class TestEvaluationResults extends Tester\TestCase
{

  static $jobConfig = [
    "submission" => [
      "job-id" => "student_bla bla bla",
      "file-collector" => "https://collector",
      "language" => "cpp",
      "hw-groups" => [ "A" ]
    ],
    "tasks" => [
      [
        "task-id" => "W",
        "priority" => 1,
        "fatal-failure" => true,
        "type" => InitiationTaskType::TASK_TYPE,
        "cmd" => [ "bin" => "cmdW" ]
      ],
      [
        "task-id" => "X",
        "priority" => 2,
        "fatal-failure" => false,
        "test-id" => "A",
        "type" => ExecutionTaskType::TASK_TYPE,
        "cmd" => [ "bin" => "cmdX" ],
        "sandbox" => [
          "name" => "isolate",
          "limits" => [[ "hw-group-id" => "A", "memory" => 123, "time" => 456 ]]
        ]
      ],
      [
        "task-id" => "Y",
        "priority" => 3,
        "fatal-failure" => true,
        "test-id" => "A",
        "type" => EvaluationTaskType::TASK_TYPE,
        "cmd" => [ "bin" => "cmdY" ]
      ]
    ]
  ];

  /** @var Builder */
  private $builder;

  public function __construct() {
    $this->builder = new Builder;
  }

  public function testMissingParams() {
    $jobConfig = $this->builder->buildJobConfig(self::$jobConfig);

    // empty document
    Assert::exception(function () use ($jobConfig) {
      new EvaluationResults([], $jobConfig);
    }, ResultsLoadingException::CLASS);

    // job id different from the one in config
    Assert::exception(function () use ($jobConfig) {
      new EvaluationResults([ "job-id" => "student_ratata" ], $jobConfig);
    }, ResultsLoadingException::CLASS);

    // missing task results
    Assert::exception(function () use ($jobConfig) {
      new EvaluationResults([
        "job-id" => "student_bla bla bla"
      ], $jobConfig);
    }, ResultsLoadingException::CLASS);

    // missing task results vol. 2
    Assert::exception(function () use ($jobConfig) {
      new EvaluationResults([
        "job-id" => "student_bla bla bla",
        "results" => NULL
      ], $jobConfig);
    }, ResultsLoadingException::CLASS);

    // missing hardware group id
    Assert::exception(function () use ($jobConfig) {
      new EvaluationResults([
        "job-id" => "student_bla bla bla",
        "results" => []
      ], $jobConfig);
    }, ResultsLoadingException::class);

    // this should be fine
    Assert::noError(function () use ($jobConfig) {
      new EvaluationResults([
        "job-id" => "student_bla bla bla",
        "hw-group" => "somegroup",
        "results" => []
      ], $jobConfig);
    });

    // missing type ("prefix_") in job id
    Assert::exception(function () use ($jobConfig) {
      new EvaluationResults([
        "job-id" => "bla bla bla",
        "results" => [ [ "a" => "b" ] ]
      ], $jobConfig);
    }, ResultsLoadingException::CLASS);

    // wrong result format
    Assert::exception(function () use ($jobConfig) {
      new EvaluationResults([
        "job-id" => "student_bla bla bla",
        "results" => [ [ "a" => "b" ] ]
      ], $jobConfig);
    }, ResultsLoadingException::CLASS);
  }

  public function testInitialisationOK() {
    $jobConfig = $this->builder->buildJobConfig(self::$jobConfig);
    $results = new EvaluationResults([
      "job-id" => "student_bla bla bla",
      "hw-group" => "whatever",
      "results" => [
        [ "task-id" => "W", "status" => "OK" ],
        [ "task-id" => "X", "status" => "OK" ],
        [ "task-id" => "Y", "status" => "OK" ]
      ]
    ], $jobConfig);

    Assert::true($results->initOK());
  }

  public function testInitialisationFailedBecauseOfSkippedTask() {
    $jobConfig = $this->builder->buildJobConfig([
      "submission" => [
        "job-id" => "student_bla bla bla",
        "file-collector" => "https://collector",
        "language" => "php",
        "hw-groups" => [ "group1" ]
      ],
      "tasks" => [
        [
          "task-id" => "A",
          "priority" => 1,
          "fatal-failure" => true,
          "cmd" => [ "bin" => "cmdA" ],
          "type" => InitiationTaskType::TASK_TYPE
        ],
        [
          "task-id" => "B",
          "priority" => 2,
          "fatal-failure" => false,
          "cmd" => [ "bin" => "cmdB" ],
          "type" => InitiationTaskType::TASK_TYPE
        ]
      ]
    ]);
    $results = new EvaluationResults([
      "job-id" => "student_bla bla bla",
      "hw-group" => "whatever",
      "results" => [
        [ "task-id" => "A", "status" => "OK" ],
        [ "task-id" => "B", "status" => "SKIPPED" ]
      ]
    ], $jobConfig);

    Assert::false($results->initOK());
  }

  public function testInitialisationFailedBecauseOfFailedTask() {
    $jobConfig = $this->builder->buildJobConfig([
      "submission" => [
        "job-id" => "student_bla bla bla",
        "file-collector" => "https://collector",
        "language" => "php",
        "hw-groups" => [ "group1" ]
      ],
      "tasks" => [
        [
          "task-id" => "A",
          "priority" => 1,
          "fatal-failure" => true,
          "cmd" => [ "bin" => "cmdA" ],
          "type" => InitiationTaskType::TASK_TYPE
        ],
        [
          "task-id" => "B",
          "priority" => 2,
          "fatal-failure" => false,
          "cmd" => [ "bin" => "cmdB" ],
          "type" => InitiationTaskType::TASK_TYPE
        ]
      ]
    ]);
    $results = new EvaluationResults([
      "job-id" => "student_bla bla bla",
      "hw-group" => "whatever",
      "results" => [
        [ "task-id" => "A", "status" => "OK" ],
        [ "task-id" => "B", "status" => "FAILED" ]
      ]
    ], $jobConfig);

    Assert::false($results->initOK());
  }

  public function testInitialisationFailedBecauseOfMissingTaskInitResult() {
    $jobConfig = $this->builder->buildJobConfig([
      "submission" => [
        "job-id" => "student_bla bla bla",
        "file-collector" => "https://collector",
        "language" => "php",
        "hw-groups" => [ "group1" ]
      ],
      "tasks" => [
        [
          "task-id" => "A",
          "priority" => 1,
          "fatal-failure" => true,
          "cmd" => [ "bin" => "cmdA" ],
          "type" => InitiationTaskType::TASK_TYPE
        ],
        [
          "task-id" => "B",
          "priority" => 2,
          "fatal-failure" => false,
          "cmd" => [ "bin" => "cmdB" ],
          "type" => InitiationTaskType::TASK_TYPE
        ]
      ]
    ]);
    $results = new EvaluationResults([
      "job-id" => "student_bla bla bla",
      "hw-group" => "whatever",
      "results" => [
        [ "task-id" => "A", "status" => "OK" ]
      ]
    ], $jobConfig);

    Assert::false($results->initOK());
  }


  public function testSimpleGetTestResult() {
    $jobConfig = $this->builder->buildJobConfig(self::$jobConfig);
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
    $results = new EvaluationResults([
      "job-id" => "student_bla bla bla",
      "hw-group" => "A",
      "results" => [ $initRes, $evalRes, $execRes ]
    ], $jobConfig);
    $testConfig = $jobConfig->getTests()["A"];

    $testResult = $results->getTestResult($testConfig);
    Assert::type(TestResult::CLASS, $testResult);
    Assert::equal("A", $testResult->getId());
    Assert::equal("OK", $testResult->getStatus());
    Assert::equal(TRUE, $testResult->isMemoryOK());
    Assert::equal(TRUE, $testResult->isTimeOK());
    Assert::equal(TRUE, $testResult->didExecutionMeetLimits());
    Assert::equal("0.456", $testResult->getJudgeOutput());
    Assert::equal(0.456, $testResult->getScore());

    Assert::equal(1, count($results->getTestsResults()));
  }

}

# Testing methods run
$testCase = new TestEvaluationResults;
$testCase->run();
