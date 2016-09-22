<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Exceptions\JobConfigLoadingException;
use App\Model\Entity\SubmissionEvaluation;
use App\Helpers\JobConfig\JobConfig;
use Symfony\Component\Yaml\Yaml;

class TestJobConfig extends Tester\TestCase
{
  static $jobConfig = [
    "submission" => [
      "job-id" => "ABC_bla bla bla"
    ],
    "tasks" => [
      [ "task-id" => "X", "test-id" => "A", "type" => "evaluation" ],
      [
        "task-id" => "Y", "test-id" => "A", "type" => "execution",
        "sandbox" => ["limits" => [[ "hw-group-id" => "A", "memory" => 123, "time" => 456 ]]
        ]
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
    Assert::equal("ABC", $jobConfig->getType());
    Assert::equal("bla bla bla", $jobConfig->getId());
    Assert::equal("ABC_bla bla bla", $jobConfig->getJobId());
    $jobConfig->setJobId("XYZ", "ratataId");
    Assert::equal("XYZ", $jobConfig->getType());
    Assert::equal("ratataId", $jobConfig->getId());
    Assert::equal("XYZ_ratataId", $jobConfig->getJobId());
  }

  public function testInvalidJobType() {
    $jobConfig = new JobConfig(self::$jobConfig);
    Assert::exception(function() use ($jobConfig) {
      $jobConfig->setJobId("XY_Z", "ratataId");
    }, JobConfigLoadingException::CLASS);
  }

  public function testUpdateJobIdInSerializedConfig() {
    $jobConfig = new JobConfig(self::$jobConfig);
    $jobConfig->setJobId("XYZ", "ratataId");
    $data = Yaml::parse((string) $jobConfig);
    Assert::equal("XYZ_ratataId", $data["submission"]["job-id"]);
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
    $tests = $jobConfig->getTests("A");
    Assert::equal(1, count($tests));
  }

}

# Testing methods run
$testCase = new TestJobConfig;
$testCase->run();
