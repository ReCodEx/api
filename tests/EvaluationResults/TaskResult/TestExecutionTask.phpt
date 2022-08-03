<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\EvaluationResults\ExecutionTaskResult;
use App\Helpers\EvaluationResults\SandboxResults;
use App\Exceptions\ResultsLoadingException;

class TestExecutionTask extends Tester\TestCase
{

    static $sampleStats = [
        "exitcode" => 0,
        "max-rss" => 19696,
        "memory" => 6032,
        "wall-time" => 0.092,
        "exitsig" => 0,
        "message" => "This is a random message",
        "status" => "OK",
        "time" => 0.037,
        "killed" => false
    ];

    public function testMissingRequiredParams()
    {
        Assert::exception(
            function () {
                new ExecutionTaskResult([]);
            },
            ResultsLoadingException::class
        );
        Assert::exception(
            function () {
                new ExecutionTaskResult(['task-id' => 'ABC']);
            },
            ResultsLoadingException::class
        );
        Assert::exception(
            function () {
                new ExecutionTaskResult(['status' => 'XYZ']);
            },
            ResultsLoadingException::class
        );
        Assert::exception(
            function () {
                new ExecutionTaskResult(['task-id' => 'ABC', 'status' => 'OK']);
            },
            ResultsLoadingException::class
        );
        Assert::exception(
            function () {
                new ExecutionTaskResult(['task-id' => 'ABC', 'status' => 'OK', 'sample_stats' => null]);
            },
            ResultsLoadingException::class
        );
        Assert::noError(
            function () {
                new ExecutionTaskResult(
                    ['task-id' => 'ABC', 'status' => 'XYZ', 'sandbox_results' => self::$sampleStats]
                );
            }
        );
    }

    public function testParsingParams()
    {
        $result = new ExecutionTaskResult(
            ['task-id' => 'ABC', 'status' => 'OK', 'sandbox_results' => self::$sampleStats]
        );
        Assert::same("ABC", $result->getId());
        Assert::same("OK", $result->getStatus());
        Assert::true($result->isOk());
        Assert::equal(new SandboxResults(self::$sampleStats), $result->getSandboxResults());
    }
}

# Testing methods run
$testCase = new TestExecutionTask();
$testCase->run();
