<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Model\Entity\SubmissionEvaluation;
use App\Helpers\JobConfig\JobConfig;

class TestSubmissionEvaluation extends Tester\TestCase
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

  static $results = [
    "results" => [
      [
        "task-id" => "X",
        "status"   => "OK",
        "score"   => 1
      ],
      [
        "task-id" => "Y",
        "status"  => "OK",
        "test-id" => "A",
        "type"    => "execution",
        "sandbox_results" => [
          "exitcode"  => 0,
          "max-rss"   => 19696,
          "memory"    => 6032,
          "wall-time" => 0.092,
          "exitsig"   => 0,
          "message"   => "",
          "status"    => "OK",
          "time"      => 0.037,
          "killed"    => false
        ]
      ]
    ]
  ];

  

  public function testUpdateJobId() {
    $jobConfig = new JobConfig("ratataId", self::$jobConfig);
    Assert::equal("ratataId", $jobConfig->getJobId());
  }
  

}

# Testing methods run
$testCase = new TestSubmissionEvaluation;
$testCase->run();
