<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/TestResultMock.php';

use Tester\Assert;
use App\Helpers\Evaluation\WeightedScoreCalculator;

/**
 * @testCase
 */
class TestWeightedScoreCalculator extends Tester\TestCase
{
    private $scoreConfig = [
        "testWeights" => [
            "a" => 300,
            "b" => 200,
            "c" => 100,
            "d" => 100,
            "e" => 100,
            "f" => 200,
        ]
    ];
    private $testNames = ["a", "b", "c", "d", "e", "f"];

    private function getCalc()
    {
        return new WeightedScoreCalculator();
    }

    private function computeScore(array $scoreList)
    {
        return $this->getCalc()->computeScore($this->scoreConfig, $this->getCfg($scoreList));
    }

    private function getCfg(array $scoreList)
    {
        $scores = [];
        for ($i = 0; $i < count($scoreList); $i++) {
            $scores[$this->testNames[$i]] = new TestResultMock($scoreList[$i]);
        }

        return $scores;
    }

    public function testInvalidScoreConfig()
    {
        $cfg = [ 'foo' => 'bar' ];
        Assert::false($this->getCalc()->isScoreConfigValid($cfg));
    }

    public function testScoreConfigNonIntegerWeights()
    {
        $cfg = ['testWeights' => ['a' => 'a']];
        Assert::false($this->getCalc()->isScoreConfigValid($cfg));
    }

    public function testScoreConfigDifferentWeightCount()
    {
        $calc = new WeightedScoreCalculator();
        $score = $calc->computeScore($this->scoreConfig, [
            "a" => new TestResultMock(0.5),
            "b" => new TestResultMock(1),
        ]);
        Assert::equal(0.7, $score);
    }

    public function testScoreConfigWrongTestName()
    {
        $cfg = ['testWeights' => [ 'b' => 1 ]];
        Assert::true($this->getCalc()->isScoreConfigValid($cfg));
    }

    public function testAllPassed()
    {
        Assert::equal(1.0, $this->computeScore([1, 1, 1, 1, 1, 1]));
    }

    public function testAllFailed()
    {
        Assert::equal(0.0, $this->computeScore([0, 0, 0, 0, 0, 0]));
    }

    public function testHalfPassed()
    {
        Assert::equal(0.6, $this->computeScore([1, 1, 1, 0, 0, 0]));
    }

    public function testEmptyWeights()
    {
        $calc = new WeightedScoreCalculator();
        $cfg = $this->getCfg([0]);
        $score = $calc->computeScore(["testWeights" => []], $cfg);
        Assert::equal(0.0, $score);
    }

    public function testScoreConfigValid()
    {
        Assert::true($this->getCalc()->isScoreConfigValid($this->scoreConfig));
    }

    public function testDefaultConfig()
    {
        $config = $this->getCalc()->getDefaultConfig(["A test", "B", "test C", "Test D"]);
        Assert::equal(['testWeights' => ['A test' => 100, 'B' => 100, 'test C' => 100, 'Test D' => 100]], $config);
    }

    public function testEmptyDefaultConfig()
    {
        $config = $this->getCalc()->getDefaultConfig([]);
        Assert::equal(['testWeights' => []], $config);
    }

    public function testValidateEmptyWeights()
    {
        $calc = $this->getCalc();
        $config = $calc->getDefaultConfig([]);
        Assert::true($calc->isScoreConfigValid($config));
    }
}

$testCase = new TestWeightedScoreCalculator();
$testCase->run();
