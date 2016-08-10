<?php

namespace App\Model\Helpers;

use App\Model\Entity\SubmissionEvaluation;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * @author  Marek Lisý <marek.lisy.hk@gmail.com>
 */
class SimpleScoreCalculator implements IScoreCalculator {
  
  /**
   * Function that computes the resulting score from simple YML config and test results score
   * @param string            $scoreConfig   YAML config of the weights
   * @param ArrayCollection   $testResults   Results of individual tests
   * @return float
   */
  public function computeScore(string $scoreConfig, ArrayCollection $testResults): float {
    // first validate both arguments
    if (!$this->isScoreConfigValid($scoreConfig, TRUE)) {
      return 0.0;
    }

    $config = Yaml::parse($scoreConfig);
    $weights = $config['testWeights'];

    if (count($weights) != count($testResults)) {
      throw new \InvalidArgumentException("Score config has different number of test weights than the number of test results.");
    }

    foreach ($testResults as $result) {
      if (!array_key_exists($result->getTestName(), $weights)) {
        $testName = $result->getTestName();
        throw new \InvalidArgumentException("There is no weight for a test with name '$testName' in score config.");
      }
    }
    
    // now work out the score
    $sum = 0.0;
    $weightsSum = 0.0;
    foreach ($testResults as $result) {
      $weight = $weights[$result->getTestName()];
      $sum += $result->getScore() * $weight;
      $weightsSum += $weight;
    }

    return $sum / $weightsSum;
  }

  /**
   * @param string  $scoreConfig     YAML configuration of the weights
   * @param bool    $throwExceptions Throw exceptions when the config is invalid
   * @return bool
   */
  public function isScoreConfigValid(string $scoreConfig, bool $throwExceptions = FALSE) {
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

      return $config;
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
