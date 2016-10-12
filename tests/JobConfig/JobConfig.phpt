<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Exceptions\JobConfigLoadingException;
use App\Model\Entity\SubmissionEvaluation;
use App\Helpers\JobConfig\JobConfig;
use Symfony\Component\Yaml\Yaml;
use App\Helpers\JobConfig\UndefinedLimits;
use App\Helpers\JobConfig\Limits;

class TestJobConfig extends Tester\TestCase
{
  static $jobConfig = [
    "submission" => [
      "job-id" => "student_bla bla bla",
      "file-collector" => "url://url.url",
      "language" => "cpp",
      "log" => "true"
    ],
    "tasks" => [
      [ "task-id" => "X", "test-id" => "A", "type" => "evaluation", "priority" => 1, "fatal-failure" => "false", "cmd" => [ "bin" => "a.out" ] ],
      [
        "task-id" => "Y", "test-id" => "A", "type" => "execution", "priority" => 1, "fatal-failure" => "false", "cmd" => [ "bin" => "a.out" ],
        "sandbox" => [ "name" => "isolate", "limits" => [ [ "hw-group-id" => "A", "memory" => 123, "time" => 456 ] ] ]
      ]
    ]
  ];

  public function testSerialization() {
    $jobConfig = new JobConfig(self::$jobConfig);
    $data = Yaml::parse((string) $jobConfig);
    Assert::type("array", $data["submission"]);
    Assert::type("array", $data["tasks"]);
    Assert::equal(2, count($data["tasks"]));
  }

  public function testUpdateJobId() {
    $jobConfig = new JobConfig(self::$jobConfig);
    Assert::equal("student", $jobConfig->getType());
    Assert::equal("bla bla bla", $jobConfig->getId());
    Assert::equal("student_bla bla bla", $jobConfig->getJobId());
    $jobConfig->setJobId("reference", "ratataId");
    Assert::equal("reference", $jobConfig->getType());
    Assert::equal("ratataId", $jobConfig->getId());
    Assert::equal("reference_ratataId", $jobConfig->getJobId());
  }

  public function testInvalidJobType() {
    $jobConfig = new JobConfig(self::$jobConfig);
    Assert::exception(function() use ($jobConfig) {
      $jobConfig->setJobId("XY_Z", "ratataId");
    }, JobConfigLoadingException::CLASS);
  }

  public function testUpdateJobIdInSerializedConfig() {
    $jobConfig = new JobConfig(self::$jobConfig);
    $jobConfig->setJobId("reference", "ratataId");
    $data = Yaml::parse((string) $jobConfig);
    Assert::equal("reference_ratataId", $data["submission"]["job-id"]);
  }

  public function testUpdateFileCollector() {
    $jobConfig = new JobConfig(self::$jobConfig);
    Assert::equal("url://url.url", $jobConfig->getFileCollector());
    $jobConfig->setFileCollector("url://file.collector.recodex");
    Assert::equal("url://file.collector.recodex", $jobConfig->getFileCollector());
  }

  public function testUpdateFileCollectorInSerializedConfig() {
    $jobConfig = new JobConfig(self::$jobConfig);
    $jobConfig->setFileCollector("url://file.collector.recodex");
    $data = Yaml::parse((string) $jobConfig);
    Assert::equal("url://file.collector.recodex", $data["submission"]["file-collector"]);
  }

  public function testTasksCount() {
    $jobConfig = new JobConfig(self::$jobConfig);
    Assert::equal(2, $jobConfig->getTasksCount());
  }

  public function testGetTasks() {
    $jobConfig = new JobConfig(self::$jobConfig);
    $tasks = $jobConfig->getTasks();
    Assert::equal(2, count($tasks));
  }

  public function testGetTests() {
    $jobConfig = new JobConfig(self::$jobConfig);
    $tests = $jobConfig->getTests();
    Assert::equal(1, count($tests));
  }

  public function testRemoveLimits() {
    $hwGroup = "A";

    $jobConfig = new JobConfig(self::$jobConfig);
    $jobConfig->removeLimits($hwGroup);

    // test for infinite limits which are set in remove limits
    foreach ($jobConfig->getTasks() as $task) {
      if ($task->isExecutionTask()) {
        Assert::equal(new UndefinedLimits($hwGroup), $task->getSandboxConfig()->getLimits($hwGroup));
      }
    }
  }

  public function testSetLimits() {
    $testId = "A";
    $hwGroup = "another";
    $limits = [ "hw-group-id" => $hwGroup, "time" => "987", "memory" => "654" ];
    $testLimits = [ $testId => $limits ];

    $jobConfig = new JobConfig(self::$jobConfig);
    $jobConfig->setLimits($hwGroup, $testLimits);

    // test for expected limits which should be set
    foreach ($jobConfig->getTests() as $test) {
      Assert::equal(new Limits($testLimits[$testId]), $test->getLimits($hwGroup));
    }
  }

}

# Testing methods run
$testCase = new TestJobConfig;
$testCase->run();
