<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Exceptions\JobConfigLoadingException;
use App\Helpers\JobConfig\TaskFactory;
use App\Helpers\JobConfig\Tasks\InternalTask;
use App\Helpers\JobConfig\Tasks\ExternalTask;


class TestTaskFactory extends Tester\TestCase
{
  static $internalTaskConfig = [
    "task-id" => "student_123456",
    "file-collector" => "https://collector",
    "language" => "cpp",
    "priority" => "1",
    "fatal-failure" => "false",
    "cmd" => [ "bin" => "fetch" ]
  ];

  static $externalTaskConfig = [
    "task-id" => "student_123456",
    "file-collector" => "https://collector",
    "language" => "cpp",
    "priority" => "1",
    "fatal-failure" => "false",
    "cmd" => [ "bin" => "fetch" ],
    "sandbox" => [
      "name" => "isolate",
      "limits" => [ 0 => [ "hw-group-id" => "group1", "time" => "1", "memory" => "1000" ] ]
    ]
  ];

  public function testInternalTask() {
    $task = TaskFactory::create(self::$internalTaskConfig);
    Assert::type(InternalTask::CLASS, $task);
  }

  public function testExternalTask() {
    $task = TaskFactory::create(self::$externalTaskConfig);
    Assert::type(ExternalTask::CLASS, $task);
  }
}


# Testing methods run
$testCase = new TestTaskFactory;
$testCase->run();
