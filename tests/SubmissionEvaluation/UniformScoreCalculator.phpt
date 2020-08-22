<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/TestResultMock.php';

use Tester\Assert;
use App\Helpers\Evaluation\UniformScoreCalculator;

/**
 * @testCase
 */
class TestUniformScoreCalculator extends Tester\TestCase
{
    private function getCalc()
    {
        return new UniformScoreCalculator();
    }

    public function testValidScoreConfig()
    {
        Assert::true($this->getCalc()->isScoreConfigValid(null));
    }

    public function testInvalidScoreConfig1()
    {
        Assert::false($this->getCalc()->isScoreConfigValid(""));
    }

    public function testInvalidScoreConfig2()
    {
        Assert::false($this->getCalc()->isScoreConfigValid("testWeights:\n  a: 100"));
    }

    public function testScoreConfigCompute1()
    {
        $score = $this->getCalc()->computeScore(null, [
            "a" => new TestResultMock(0),
            "b" => new TestResultMock(1),
        ]);
        Assert::equal(0.5, $score);
    }

    public function testScoreConfigCompute2()
    {
        $score = $this->getCalc()->computeScore(null, [
            "a" => new TestResultMock(1),
            "b" => new TestResultMock(0),
            "c" => new TestResultMock(0.2),
            "d" => new TestResultMock(0.3),
            "e" => new TestResultMock(0.5),
        ]);
        Assert::equal(0.4, $score);
    }

    public function testScoreConfigComputeAllPassed()
    {
        $score = $this->getCalc()->computeScore(null, [
            "a" => new TestResultMock(1),
            "b" => new TestResultMock(1),
            "c" => new TestResultMock(1),
            "d" => new TestResultMock(1),
            "e" => new TestResultMock(1),
        ]);
        Assert::equal(1.0, $score);
    }

    public function testScoreConfigComputeAllFailed()
    {
        $score = $this->getCalc()->computeScore(null, [
            "a" => new TestResultMock(0),
            "b" => new TestResultMock(0),
            "c" => new TestResultMock(0),
            "d" => new TestResultMock(0),
            "e" => new TestResultMock(0),
        ]);
        Assert::equal(0.0, $score);
    }

    public function testDefaultConfig()
    {
        Assert::null($this->getCalc()->getDefaultConfig(["A test", "B", "test C", "Test D"]));
    }

}

$testCase = new TestUniformScoreCalculator();
$testCase->run();
