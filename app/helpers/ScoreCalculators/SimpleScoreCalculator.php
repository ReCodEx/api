<?php

namespace App\Helpers;

use App\Exceptions\SubmissionEvaluationFailedException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Simple score calculator. It expect config in YAML format such as:
 *  testWeights:
 *    A: 200
 *    B: 800
 * The meaning is, that for test with ID "A" will be assigned 20% of exercise
 * points, test "B" will be assigned 80% of all points. Total sum of test
 * weights can be arbitrary, what matters are ratios.
 */
class SimpleScoreCalculator implements IScoreCalculator {

  /**
   * Function that computes the resulting score from simple YML config and test results score
   * @param string $scoreConfig
   * @param array $testResults Results of individual tests, array of test-id => float score
   * @return float Percentage of total points assigned to the solution
   * @throws SubmissionEvaluationFailedException
   */
  public function computeScore(string $scoreConfig, array $testResults): float {
    if (!$this->isScoreConfigValid($scoreConfig)) {
      throw new SubmissionEvaluationFailedException("Assignment score configuration is invalid");
    }

    $config = Yaml::parse($scoreConfig);
    $weights = $config['testWeights'];

    // assign zero ratio to all tests which does not have specified value
    foreach ($testResults as $name => $score) {
      if (!array_key_exists($name, $weights)) {
        $weights[$name] = 0;
      }
    }

    // now work out the score
    $sum = 0.0;
    $weightsSum = 0.0;
    foreach ($testResults as $name => $score) {
      $weight = $weights[$name];
      $sum += $score * $weight;
      $weightsSum += $weight;
    }

    return $sum / $weightsSum;
  }

  /**
   * @param string $scoreConfig YAML configuration of the weights
   * @return bool If the configuration is valid or not
   */
  public function isScoreConfigValid(string $scoreConfig): bool {
    try {
      $config = Yaml::parse($scoreConfig);

      if (isset($config['testWeights']) && is_array($config['testWeights'])) {
        foreach ($config['testWeights'] as $value) {
          if (!is_integer($value)) {
            return false;
          }
        }
      } else {
        return false;
      }
    } catch (ParseException $e) {
      return false;
    }

    return true;
  }

  /**
   * Make default configuration for array of test names. Each test will
   * have the same priority as others.
   * @param array $tests of string names of tests
   * @return string Default configuration for given tests
   */
  public function getDefaultConfig(array $tests): string {
    $weights = [];
    foreach ($tests as $test) {
      $weights[$test] = 100;
    }
    $config = [];
    $config["testWeights"] = $weights;

    return Yaml::dump($config);
  }
}
