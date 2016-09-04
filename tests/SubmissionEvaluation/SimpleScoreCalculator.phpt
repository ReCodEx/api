<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\SimpleScoreCalculator;
use Doctrine\Common\Collections\ArrayCollection;

use App\Model\Entity\TestResult;

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

  private function computeScore(array $scoreList, $scoreConfig = NULL) {
    if ($scoreConfig === NULL) {
      $scoreConfig = $this->scoreConfig;
    }
    $scores = [];
    for ($i = 0; $i < count($scoreList); $i++) {
      $scores[$this->testNames[$i]] = $scoreList[$i];
    }
    $calc = new SimpleScoreCalculator($scoreConfig);
    return $calc->computeScore($scores);
  }

  private function isScoreConfigValid($scoreConfig) {
    return SimpleScoreCalculator::isScoreConfigValid($scoreConfig);
  }

  public function testInvalidYamlScoreConfig() {
    $cfg = "\"asd";
    Assert::exception(
      function () use ($cfg) { $this->computeScore([1, 1, 1, 1, 1, 1], $cfg); },
      "InvalidArgumentException",
      "Supplied score config is not a valid YAML."
    );
    Assert::same(FALSE, $this->isScoreConfigValid($cfg));
  }

  public function testScoreConfigNonIntegerWeights() {
    $cfg = "testWeights:\n  a: a";
    Assert::exception(
      function () use ($cfg) { $this->computeScore([1, 1, 1, 1, 1, 1], $cfg); },
      "InvalidArgumentException",
      "Test weights must be integers."
    );
    Assert::same(FALSE, $this->isScoreConfigValid($cfg));
  }

  public function testScoreConfigNoWeights() {
    $cfg = "testWeight:\n  a: 1";
    Assert::exception(
      function () use ($cfg) { $this->computeScore([1, 1, 1, 1, 1, 1], $cfg); },
      "InvalidArgumentException",
      "Score config is missing 'testWeights' array parameter."
    );
    Assert::same(FALSE, $this->isScoreConfigValid($cfg));
  }

  public function testScoreConfigDifferentWeightCount() {
    $cfg = "testWeights:\n  a: 1";
    Assert::exception(
      function () use ($cfg) { $this->computeScore([1, 1, 1, 1, 1, 1], $cfg); },
      "InvalidArgumentException",
      "Score config has different number of test weights than the number of test results."
    );
    Assert::same(TRUE, $this->isScoreConfigValid($cfg));
  }

  public function testScoreConfigWrongTestName() {
    $cfg = "testWeights:\n  b: 1";
    Assert::exception(
      function () use ($cfg) { $this->computeScore([1], $cfg); },
      "InvalidArgumentException",
      "There is no weight for a test with name 'a' in score config."
    );
    Assert::same(TRUE, $this->isScoreConfigValid($cfg));
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
    Assert::same(TRUE, $this->isScoreConfigValid($this->scoreConfig));
  }
}

$testCase = new TestSimpleScoreCalculator;
$testCase->run();