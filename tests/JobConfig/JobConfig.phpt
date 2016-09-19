<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Model\Entity\SubmissionEvaluation;
use App\Helpers\JobConfig\JobConfig;
use Symfony\Component\Yaml\Yaml;

class TestJobConfig extends Tester\TestCase
{
  static $jobConfig = [
    "submission" => [
      "job-id" => "bla bla bla"
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
    Assert::equal("bla bla bla", $jobConfig->getJobId());
    $jobConfig->setJobId("ratataId");
    Assert::equal("ratataId", $jobConfig->getJobId());
  }

  public function testUpdateJobIdInSerializedConfig() {
    $jobConfig = new JobConfig(self::$jobConfig);
    $jobConfig->setJobId("ratataId");
    $data = Yaml::parse((string) $jobConfig);
    Assert::equal("ratataId", $data["submission"]["job-id"]);
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
