<?php

namespace App\Helpers;
use App\Exceptions\SubmissionEvaluationFailedException;

/**
 * Interface for score computations. Purpose is to merge scores of all
 * tests into one number, the final score (points for users).
 */
interface IScoreCalculator {
  /**
   * Compute the score from tests results
   * @param string $scoreConfig Configuration of the calculator (format depends on implementation)
   * @param array $testResults Array of scores (float) indexed by test ids
   * @return float Percentage of points, that should be assigned to the solution
   * @throws SubmissionEvaluationFailedException
   */
  public function computeScore(string $scoreConfig, array $testResults): float;

  /**
   * Validate score configuration from database.
   * @param string $scoreConfig Configuration of score loaded from database
   * @return bool If the config is valid or not
   */
  public static function isScoreConfigValid(string $scoreConfig): bool;

  /**
   * Make default configuration for array of test names. Each test will
   * have the same priority as others.
   * @param array List of string names of tests
   * @return string Default configuration for given tests
   */
  public static function getDefaultConfig(array $tests): string;
}
