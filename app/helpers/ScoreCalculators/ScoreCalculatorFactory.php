<?php

namespace App\Helpers;

use App\Exceptions\SubmissionEvaluationFailedException;

/**
 * @author  Marek Lisý <marek.lisy.hk@gmail.com>
 */
class ScoreCalculatorFactory {

  /**
   * Creates a score calculator based on configuration string.
   *
   */
  public static function create(string $scoreConfig) {
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
   * Determines which calculator to use for given score computation schema.
   * @param string $scoreConfig Score computation schema
   * @return class
   */
  public static function getCalculatorClass(string $scoreConfig) {
    // so far there is only one calculator type,
    return SimpleScoreCalculator::CLASS;
  }

}
