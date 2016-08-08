<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Model\Entity\SubmissionEvaluation;
use App\Model\Helpers\ResultsTransform;

class TestSubmissionEvaluation extends Tester\TestCase
{
  static $jobConfig = [
    "tasks" => [
      [ "task-id" => "X", "test-id" => "A", "type" => "evaluation" ],
      [
        "task-id" => "Y", "test-id" => "A", "type" => "execution",
        "sandbox" => [
          "limits" => [ "memory" => 123, "time" => 456 ]
        ]
      ]
    ]
  ];

  static $results = [
    "results" => [
      [
        "task-id" => "X",
        "status"   => "OK",
        "score"   => SubmissionEvaluation::MAX_SCORE
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

  public function testSimplifyJobConfig() {
    $expected = [
      [ "test-id" => "A", "task-id" => "X", "type" => "evaluation" ],
      [
        "test-id" => "A", "task-id" => "Y", "type" => "execution",
        "limits" => [ "memory" => 123, "time" => 456 ]
      ]
    ];

    $simplified = ResultsTransform::simplifyConfig(self::$jobConfig);
    Assert::same($expected, $simplified);
  }

  public function testSimplifyJobConfigRemoveUnnecessaryTasks() {
    $input = [
      "tasks" => [
        [ "task-id" => "X", "status"  => "OK" ],
        [ "task-id" => "Y", "test-id" => "A", "type" => "evaluation", "status" => "OK" ],
        [
          "task-id" => "Z", "test-id" => "B", "type" => "execution", "status" => "FAILED",
          "sandbox" => [ "limits" => "..." ]
        ]
      ]
    ];

    $expected = [
      [ "test-id" => "A", "task-id" => "Y", "type" => "evaluation" ],
      [ "test-id" => "B", "task-id" => "Z", "type" => "execution", "limits" => "..." ]
    ];

    $simplified = ResultsTransform::simplifyConfig($input);
    Assert::same(2, count($simplified));
    Assert::same($expected, $simplified);
  }

  public function testCreateAssocArray() {
    $assoc = ResultsTransform::createAssocResults(self::$results["results"]);
    Assert::same(2, count($assoc));
    Assert::same(["X", "Y"], array_keys($assoc));
    Assert::same(self::$results["results"][0], $assoc["X"]);
    Assert::same(self::$results["results"][1], $assoc["Y"]);
  }

  public function testExtractScoreSimple() {
    $score = 123;
    $result = [ "score" => $score ];
    Assert::same($score, ResultsTransform::extractScore($result));
  }

  public function testExtractScoreNoScore() {
    $result = [ "status" => "OK" ];
    Assert::same(SubmissionEvaluation::MAX_SCORE, ResultsTransform::extractScore($result));
  }

  public function testExtractScoreNoScoreFailed() {
    $result = [ "status" => "FAILED" ];
    Assert::same(0, ResultsTransform::extractScore($result));
  }

  public function testExtractScoreNoScoreSkipped() {
    $result = [ "status" => "SKIPPED" ];
    Assert::same(0, ResultsTransform::extractScore($result));
  }

  public function testExtractStatusOK_OK() {
    Assert::same("OK", ResultsTransform::extractStatus("OK", "OK"));
  }

  public function testExtractStatusSKIPPED_SKIPPED() {
    Assert::same("SKIPPED", ResultsTransform::extractStatus("SKIPPED", "SKIPPED"));
  }

  public function testExtractStatusFAILED_FAILED() {
    Assert::same("FAILED", ResultsTransform::extractStatus("FAILED", "FAILED"));
  }

  public function testExtractStatusSKIPPED_OK() {
    Assert::same("SKIPPED", ResultsTransform::extractStatus("SKIPPED", "OK"));
  }

  public function testExtractStatusOK_SKIPPED() {
    Assert::same("SKIPPED", ResultsTransform::extractStatus("OK", "SKIPPED"));
  }

  public function testExtractStatusOK_FAILED() {
    Assert::same("FAILED", ResultsTransform::extractStatus("OK", "FAILED"));
  }

  public function testExtractStatusFAILED_OK() {
    Assert::same("FAILED", ResultsTransform::extractStatus("FAILED", "OK"));
  }

  public function testExtractStatusSKIPPED_FAILED() {
    Assert::same("FAILED", ResultsTransform::extractStatus("SKIPPED", "FAILED"));
  }

  public function testExtractStatusFAILED_SKIPPED() {
    Assert::same("FAILED", ResultsTransform::extractStatus("FAILED", "SKIPPED"));
  }

  public function testSimpleMapAndReduceResults() {
    $transformed = ResultsTransform::transformLowLevelInformation(self::$jobConfig, self::$results);
    $expected = [
      "A" => [
        "status"    => "OK",
        "score"     => SubmissionEvaluation::MAX_SCORE,
        "stats"     => [
          "exitcode"  => 0,
          "max-rss"   => 19696,
          "memory"    => 6032,
          "wall-time" => 0.092,
          "exitsig"   => 0,
          "message"   => "",
          "status"    => "OK",
          "time"      => 0.037,
          "killed"    => false
        ],
        "limits" => [ "memory" => 123, "time" => 456 ]
      ]
    ];

    Assert::same($expected, $transformed);
  }

}

# Testing methods run
$testCase = new TestSubmissionEvaluation;
$testCase->run();
