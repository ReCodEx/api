<?php

namespace App\Helpers;

use App\Exceptions\SubmissionEvaluationFailedException;
use App\Model\Entity\SubmissionEvaluation;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * @author  Marek Lisý <marek.lisy.hk@gmail.com>
 */
class SimpleScoreCalculator implements IScoreCalculator {
  
  /** @var array Associative array of all weights of named tests */
  private $weights;

  public function __construct(string $scoreConfig) {
    if (!self::isScoreConfigValid($scoreConfig, TRUE)) {
      throw new SubmissionEvaluationFailedException("Assignment score configuration is invalid");
    }

    $config = Yaml::parse($scoreConfig);
    $this->weights = $config['testWeights'];
  }

  /**
   * Function that computes the resulting score from simple YML config and test results score
   * @param ArrayCollection   $testResults   Results of individual tests
   * @return float
   */
  public function computeScore(array $testResults): float {    
    if (count($this->weights) != count($testResults)) {
      throw new \InvalidArgumentException("Score config has different number of test weights than the number of test results.");
    }

    foreach ($testResults as $name => $score) {
      if (!array_key_exists($name, $this->weights)) {
        throw new \InvalidArgumentException("There is no weight for a test with name '$name' in score config.");
      }
    }
    
    // now work out the score
    $sum = 0.0;
    $weightsSum = 0.0;
    foreach ($testResults as $name => $score) {
      $weight = $this->weights[$name];
      $sum += $score * $weight;
      $weightsSum += $weight;
    }

    return $sum / $weightsSum;
  }

  /**
   * @param string  $scoreConfig     YAML configuration of the weights
   * @param bool    $throwExceptions Throw exceptions when the config is invalid
   * @return bool
   */
  public static function isScoreConfigValid(string $scoreConfig, bool $throwExceptions = FALSE) {
    try {
      $config = Yaml::parse($scoreConfig);

      if (isset($config['testWeights']) && is_array($config['testWeights'])) {
        foreach ($config['testWeights'] as $value) {
          if (!is_integer($value)) {
            if ($throwExceptions) {
              throw new \InvalidArgumentException("Test weights must be integers.");
            } else {
              return FALSE;
            }
          }
        }
      } else {
        if ($throwExceptions) {
          throw new \InvalidArgumentException("Score config is missing 'testWeights' array parameter.");
        } else {
          return FALSE;
        }
      }
    } catch (ParseException $e) {
      if ($throwExceptions) {
        throw new \InvalidArgumentException("Supplied score config is not a valid YAML.");
      } else {
        return FALSE;
      }
    }

    return TRUE;
  }
}
