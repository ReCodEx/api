<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\Builder;
use App\Exceptions\JobConfigLoadingException;

/**
 * Job configuration builder is mostly tested in components which are
 * constructed/built by it (JobConfig, SubmissionHeader, etc...).
 * This is only general test which tests only simple cases.
 */
class TestJobConfigBuilder extends Tester\TestCase
{
  /** @var Builder */
  private $builder;

  public function __construct() {
    $this->builder = new Builder;
  }

  public function testBadFormat() {
    Assert::exception(function () {
      $this->builder->buildJobConfig([]);
    }, JobConfigLoadingException::class);

    Assert::exception(function () {
      $this->builder->buildJobConfig("");
    }, JobConfigLoadingException::class);

    Assert::exception(function () {
      $this->builder->buildJobConfig(NULL);
    }, JobConfigLoadingException::class);
  }

  public function testCorrectBuild() {
    $job = $this->builder->buildJobConfig(self::$jobConfig);
    Assert::equal("bla bla bla", $job->getSubmissionHeader()->getId());
  }

  static $jobConfig = [
    "submission" => [
      "job-id" => "student_bla bla bla",
      "file-collector" => "url://url.url",
      "language" => "cpp",
      "log" => "true",
      "hw-groups" => [ "A" ]
    ],
    "tasks" => [
      [
        "task-id" => "X",
        "test-id" => "A",
        "type" => "evaluation",
        "priority" => 1,
        "fatal-failure" => "false",
        "cmd" => [ "bin" => "a.out" ]
      ],
      [
        "task-id" => "Y",
        "test-id" => "A",
        "type" => "execution",
        "priority" => 1,
        "fatal-failure" => "false",
        "cmd" => [ "bin" => "a.out" ],
        "sandbox" => [
          "name" => "isolate",
          "limits" => [[
              "hw-group-id" => "A",
              "memory" => 123,
              "time" => 456
          ]]
        ]
      ]
    ]
  ];
}

# Testing methods run
$testCase = new TestJobConfigBuilder;
$testCase->run();
