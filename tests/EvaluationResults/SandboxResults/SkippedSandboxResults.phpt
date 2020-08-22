<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\EvaluationResults\SkippedSandboxResults;
use App\Helpers\JobConfig\Limits;

/**
 * @testCase
 */
class TestSkippedSandboxResults extends Tester\TestCase
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
        $stats = new SkippedSandboxResults();
        Assert::equal(SkippedSandboxResults::EXIT_CODE_UNKNOWN, $stats->getExitCode());
        Assert::equal(0, $stats->getUsedMemory());
        Assert::equal(0.0, $stats->getUsedWallTime());
        Assert::equal(0.0, $stats->getUsedCpuTime());
        Assert::false($stats->wasKilled());
        Assert::equal("SKIPPED", (string)$stats);
    }
}

# Testing methods run
$testCase = new TestSkippedSandboxResults();
$testCase->run();
