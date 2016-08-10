<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Model\Helpers\SimpleScoreCalculator;
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
    $testResults = new ArrayCollection;

    for ($i = 0; $i < count($scoreList); $i++) {
      $tr = new TestResult;
      $tr->setTestName($this->testNames[$i]);
      $tr->setScore($scoreList[$i]);
      $testResults->add($tr);
    }
    $calc = new SimpleScoreCalculator;
    return $calc->computeScore($scoreConfig, $testResults);
  }

  public function testInvalidYamlScoreConfig() {
    Assert::exception(function () { $this->computeScore([1, 1, 1, 1, 1, 1], "\"asd"); }, "InvalidArgumentException", "Supplied score config is not a valid YAML.");
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
}

$testCase = new TestSimpleScoreCalculator;
$testCase->run();