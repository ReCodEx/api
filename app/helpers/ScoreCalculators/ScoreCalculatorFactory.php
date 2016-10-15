<?php

namespace App\Helpers;

use App\Exceptions\SubmissionEvaluationFailedException;

/**
 * Factory for getting instances of various score calculators
 */
class ScoreCalculatorFactory {

  /**
   * Creates a score calculator based on configuration string
   * @param string $scoreConfig Score configuration string
   * @return IScoreCalculator Instance of matching score calculator
   * @throws SubmissionEvaluationFailed if calculator cannot be found or score config is corrupted
   */
  public static function create(string $scoreConfig): IScoreCalculator {
    $calculator = self::getCalculatorClass($scoreConfig);
    if (!$calculator) {
      throw new SubmissionEvaluationFailedException("There is no suitable calculator for the given score computation schema.");
    }

    if (!$calculator::isScoreConfigValid($scoreConfig, TRUE)) {
      throw new SubmissionEvaluationFailedException("Assignment score configuration is invalid");
    }

    return new $calculator($scoreConfig);
  }

  /**
   * Determines which calculator to use for given score computation schema
   * @param string $scoreConfig Score computation schema
   * @return class One of IScoreCalculator implementation classes or NULL
   */
  public static function getCalculatorClass(string $scoreConfig) {
    // so far there is only one calculator type,
    return SimpleScoreCalculator::CLASS;
  }

}
