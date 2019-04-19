<?php

namespace App\Helpers\Evaluation;
use App\Exceptions\SubmissionEvaluationFailedException;
use App\Exceptions\ExerciseConfigException;

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
  public function isScoreConfigValid(string $scoreConfig): bool;

  /**
   * Performs validation and normalization on config string.
   * This should be used instead of validation when the score config is processed as API input.
   * @param string $scoreConfig YAML configuration for the score calculator
   * @return string Normalized and polished YAML with score configuration
   * @throws ExerciseConfigException
   */
  public function validateAndNormalizeScore(string $scoreConfig): string;

  /**
   * Make default configuration for array of test names. Each test will
   * have the same priority as others.
   * @param array $tests List of string names of tests
   * @return string Default configuration for given tests
   */
  public function getDefaultConfig(array $tests): string;
}
