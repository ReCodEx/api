<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\Builder;
use App\Helpers\JobConfig\SandboxConfig;
use App\Helpers\JobConfig\Limits;
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
    $header = $job->getSubmissionHeader();

    Assert::equal("bla bla bla", $header->getId());
    Assert::equal("student", $header->getType());
    Assert::equal("url://url.url", $header->getFileCollector());
    Assert::equal(TRUE, $header->getLog());
    Assert::equal(["A"], $header->getHardwareGroups());

    Assert::count(2, $job->getTasks());
    $task1 = $job->getTasks()[0];
    $task2 = $job->getTasks()[1];

    Assert::equal("X", $task1->getId());
    Assert::equal("A", $task1->getTestId());
    Assert::equal("evaluation", $task1->getType());
    Assert::equal(1, $task1->getPriority());
    Assert::equal(false, $task1->getFatalFailure());
    Assert::equal("x.out", $task1->getCommandBinary());
    Assert::equal([], $task1->getCommandArguments());
    Assert::false($task1->isSandboxedTask());
    Assert::equal(NULL, $task1->getSandboxConfig());

    Assert::equal("Y", $task2->getId());
    Assert::equal("A", $task2->getTestId());
    Assert::equal("execution", $task2->getType());
    Assert::equal(2, $task2->getPriority());
    Assert::equal(true, $task2->getFatalFailure());
    Assert::equal("y.out", $task2->getCommandBinary());
    Assert::equal(["arg1", "arg2"], $task2->getCommandArguments());
    Assert::true($task2->isSandboxedTask());

    $sandboxConfig = $task2->getSandboxConfig();
    Assert::type(SandboxConfig::class, $sandboxConfig);
    Assert::equal("isolate", $sandboxConfig->getName());
    Assert::equal("instd", $sandboxConfig->getStdin());
    Assert::equal("outstd", $sandboxConfig->getStdout());
    Assert::equal("errstd", $sandboxConfig->getStderr());
    Assert::count(1, $sandboxConfig->getLimitsArray());
    Assert::true($sandboxConfig->hasLimits("A"));

    $limits = $sandboxConfig->getLimits("A");
    Assert::type(Limits::class, $limits);
    Assert::equal("A", $limits->getId());
    Assert::equal(123, $limits->getMemoryLimit());
    Assert::equal(456.0, $limits->getTimeLimit());
  }

  static $jobConfig = [
    "submission" => [
      "job-id" => "student_bla bla bla",
      "file-collector" => "url://url.url",
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
        "cmd" => [ "bin" => "x.out" ]
      ],
      [
        "task-id" => "Y",
        "test-id" => "A",
        "type" => "execution",
        "priority" => 2,
        "fatal-failure" => "true",
        "cmd" => [ "bin" => "y.out", "args" => [ "arg1", "arg2" ] ],
        "sandbox" => [
          "name" => "isolate",
          "stdin" => "instd",
          "stdout" => "outstd",
          "stderr" => "errstd",
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
