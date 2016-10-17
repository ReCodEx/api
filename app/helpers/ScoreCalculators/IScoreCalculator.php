<?php

namespace App\Helpers;

/**
 * Interface for score computations. Purpose is to merge scores of all
 * tests into one number, the final score (points for users).
 */
interface IScoreCalculator {
  /**
   * Compute the score from tests results
   * @param array $testResults Array of scores (float) indexed by test ids
   * @return float Percentage of points, that should be assigned to the solution
   */
  public function computeScore(array $testResults): float;

  /**
   * Validate score configuration from database.
   * @param string $scoreConfig Configuration of score loaded from database
   * @return bool If the config is valid or not
   */
  public static function isScoreConfigValid(string $scoreConfig): bool;
}
