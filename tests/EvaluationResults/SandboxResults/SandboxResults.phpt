<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\EvaluationResults\SandboxResults;

/**
 * @testCase
 */
class TestSandboxResults extends Tester\TestCase
{
    static $sample = [
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

    public function testParseStats()
    {
        $stats = new SandboxResults(self::$sample);
        Assert::equal(0, $stats->getExitCode());
        Assert::equal(6032, $stats->getUsedMemory());
        Assert::equal(0.092, $stats->getUsedWallTime());
        Assert::equal(0.037, $stats->getUsedCpuTime());
        Assert::equal("This is a random message", $stats->getMessage());
    }

    public function testSerialization()
    {
        $stats = new SandboxResults(self::$sample);
        $json = json_encode(self::$sample);
        Assert::equal($json, (string)$stats);
    }

    public function testMissingExitcode()
    {
        $data = self::$sample;
        unset($data["exitcode"]);
        Assert::exception(
            function () use ($data) {
                new SandboxResults($data);
            },
            'App\Exceptions\ResultsLoadingException',
            "Submission Evaluation Failed - Results loading or parsing failed - Sandbox results do not include the 'exitcode' field."
        );
    }

    public function testMissingMemory()
    {
        $data = self::$sample;
        unset($data["memory"]);
        Assert::exception(
            function () use ($data) {
                new SandboxResults($data);
            },
            'App\Exceptions\ResultsLoadingException',
            "Submission Evaluation Failed - Results loading or parsing failed - Sandbox results do not include the 'memory' field."
        );
    }

    public function testMissingCpuTime()
    {
        $data = self::$sample;
        unset($data["time"]);
        Assert::exception(
            function () use ($data) {
                new SandboxResults($data);
            },
            'App\Exceptions\ResultsLoadingException',
            "Submission Evaluation Failed - Results loading or parsing failed - Sandbox results do not include the 'time' field."
        );
    }

    public function testMissingWallTime()
    {
        $data = self::$sample;
        unset($data["wall-time"]);
        Assert::exception(
            function () use ($data) {
                new SandboxResults($data);
            },
            'App\Exceptions\ResultsLoadingException',
            "Submission Evaluation Failed - Results loading or parsing failed - Sandbox results do not include the 'wall-time' field."
        );
    }

    public function testMissingMessage()
    {
        $data = self::$sample;
        unset($data["message"]);
        Assert::exception(
            function () use ($data) {
                new SandboxResults($data);
            },
            'App\Exceptions\ResultsLoadingException',
            "Submission Evaluation Failed - Results loading or parsing failed - Sandbox results do not include the 'message' field."
        );
    }

    public function testMissingKilled()
    {
        $data = self::$sample;
        unset($data["killed"]);
        Assert::exception(
            function () use ($data) {
                new SandboxResults($data);
            },
            'App\Exceptions\ResultsLoadingException',
            "Submission Evaluation Failed - Results loading or parsing failed - Sandbox results do not include the 'killed' field."
        );
    }
}

# Testing methods run
$testCase = new TestSandboxResults();
$testCase->run();
