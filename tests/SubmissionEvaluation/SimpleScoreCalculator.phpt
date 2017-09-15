<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\SimpleScoreCalculator;
use Doctrine\Common\Collections\ArrayCollection;

use App\Model\Entity\TestResult;

/**
 * @testCase
 */
class TestSimpleScoreCalculator extends Tester\TestCase
{
  private $scoreConfig = "testWeights:
  a: 300 # number between 1 and 1000
  b: 200 # sum of all numbers must be 1000
  c: 100
  d: 100
  e: 100
  f: 200";
  private $testNames = ["a", "b", "c", "d", "e", "f"];

  private function getCalc() { return new SimpleScoreCalculator(); }

  private function computeScore(array $scoreList) {
    return $this->getCalc()->computeScore($this->scoreConfig, $this->getCfg($scoreList));
  }

  private function getCfg(array $scoreList) {
    $scores = [];
    for ($i = 0; $i < count($scoreList); $i++) {
      $scores[$this->testNames[$i]] = $scoreList[$i];
    }

    return $scores;
  }

  private function isScoreConfigValid($scoreConfig) {
    return SimpleScoreCalculator::isScoreConfigValid($scoreConfig);
  }

  public function testInvalidYamlScoreConfig() {
    $cfg = "\"asd";
    Assert::false(SimpleScoreCalculator::isScoreConfigValid($cfg));
  }

  public function testScoreConfigNonIntegerWeights() {
    $cfg = "testWeights:\n  a: a";
    Assert::false(SimpleScoreCalculator::isScoreConfigValid($cfg));
  }

  public function testScoreConfigDifferentWeightCount() {
    $cfg = "testWeights:\n  a: 1";
    $calc = new SimpleScoreCalculator($cfg);
    Assert::exception(function() use ($calc) { $calc->computeScore($this->scoreConfig, [ "a" => 0.5, "b" => 1 ]); }, \InvalidArgumentException::CLASS);
  }

  public function testScoreConfigWrongTestName() {
    $cfg = "testWeights:\n  b: 1";
    Assert::true(SimpleScoreCalculator::isScoreConfigValid($cfg));
  }

  public function testAllPassed() {
    Assert::equal(1.0, $this->computeScore([1, 1, 1, 1, 1, 1]));
  }

  public function testAllFailed() {
    Assert::equal(0.0, $this->computeScore([0, 0, 0, 0, 0, 0]));
  }

  public function testHalfPassed() {
    Assert::equal(0.6, $this->computeScore([1, 1, 1, 0, 0, 0]));
  }

  public function testScoreConfigValid() {
    Assert::true(SimpleScoreCalculator::isScoreConfigValid($this->scoreConfig));
  }
}

$testCase = new TestSimpleScoreCalculator;
$testCase->run();
