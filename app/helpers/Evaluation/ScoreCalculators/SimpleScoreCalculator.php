<?php

namespace App\Helpers\Evaluation;

use App\Exceptions\SubmissionEvaluationFailedException;
use App\Exceptions\ExerciseConfigException;
use App\Helpers\Evaluation\IScoreCalculator;
use App\Helpers\Yaml;
use App\Helpers\YamlException;

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
   * Internal function that safely retrieves score config weights.
   * @param string $scoreConfig
   * @return array|null Null if the config is invalid, name => weight array otherwise.
   */
  private function getTestWeights(string $scoreConfig): ?array {
    try {
      $config = Yaml::parse($scoreConfig);
      $normalizedWeights = [];

      if (isset($config['testWeights']) && is_array($config['testWeights'])) {
        foreach ($config['testWeights'] as $name => $value) {
          if (!is_integer($value)) {
            return null;
          }
          $normalizedWeights[trim($name)] = $value;
        }
      } else {
        return null;
      }
    } catch (YamlException $e) {
      return null;
    }

    return $normalizedWeights;
  }

  /**
   * Function that computes the resulting score from simple YML config and test results score
   * @param string $scoreConfig
   * @param array $testResults Results of individual tests, array of test-id => float score
   * @return float Percentage of total points assigned to the solution
   * @throws SubmissionEvaluationFailedException
   */
  public function computeScore(string $scoreConfig, array $testResults): float {
    $weights = $this->getTestWeights($scoreConfig);
    if ($weights === null) {
      throw new SubmissionEvaluationFailedException("Assignment score configuration is invalid");
    }

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

    return $weightsSum == 0 ? 0.0 : $sum / $weightsSum;
  }

  /**
   * @param string $scoreConfig YAML configuration of the weights
   * @return bool If the configuration is valid or not
   */
  public function isScoreConfigValid(string $scoreConfig): bool {
    return $this->getTestWeights($scoreConfig) !== null;
  }

  /**
   * Performs validation and normalization on config string.
   * This should be used instead of validation when the score config is processed as API input.
   * @param string $scoreConfig YAML configuration for the score calculator
   * @return string Normalized and polished YAML with score configuration
   * @throws ExerciseConfigException
   */
  public function validateAndNormalizeScore(string $scoreConfig): string {
    $weights = $this->getTestWeights($scoreConfig);
    if ($weights === null) {
      throw new ExerciseConfigException("Exercise score configuration is not valid");
    }
    return Yaml::dump([ 'testWeights' => $weights ]);
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
