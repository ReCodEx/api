<?php

namespace App\Helpers\Evaluation;

use App\Exceptions\SubmissionEvaluationFailedException;
use App\Exceptions\ExerciseConfigException;
use App\Helpers\Evaluation\IScoreCalculator;
use App\Helpers\Yaml;
use App\Helpers\YamlException;

/**
 * Weighted score calculator. It expect config in structured format such as:
 *  testWeights:
 *    A: 200
 *    B: 800
 * The meaning is, that for test with ID "A" will be assigned 20% of exercise
 * points, test "B" will be assigned 80% of all points. Total sum of test
 * weights can be arbitrary, what matters are ratios.
 */
class WeightedScoreCalculator implements IScoreCalculator
{
    public const ID = 'weighted';

    public function getId(): string
    {
        return self::ID;
    }

    /**
     * Internal function that safely retrieves score config weights.
     * @param array $scoreConfig
     * @return array|null Null if the config is invalid, name => weight array otherwise.
     */
    private function getTestWeights(array $config): ?array
    {
        if (isset($config['testWeights']) && is_array($config['testWeights'])) {
            $normalizedWeights = [];
            foreach ($config['testWeights'] as $name => $value) {
                if (!is_integer($value)) {
                    return null;
                }
                $normalizedWeights[trim($name)] = $value;
            }
            return $normalizedWeights;
        } else {
            return null;
        }
    }

    public function computeScore($scoreConfig, array $testResults): float
    {
        if ($scoreConfig === null) {
            throw new SubmissionEvaluationFailedException("Assignment score configuration is invalid");
        }

        $weights = $this->getTestWeights($scoreConfig);
        if ($weights === null) {
            throw new SubmissionEvaluationFailedException("Assignment score configuration is invalid");
        }

        // assign zero ratio to all tests which does not have specified value
        foreach ($testResults as $name => $_) {
            if (!array_key_exists($name, $weights)) {
                $weights[$name] = 0;
            }
        }

        // now work out the score
        $sum = 0.0;
        $weightsSum = 0.0;
        foreach ($testResults as $name => $testResult) {
            $weight = $weights[$name];
            $sum += $testResult->getScore() * $weight;
            $weightsSum += $weight;
        }

        return $weightsSum == 0 ? 0.0 : $sum / $weightsSum;
    }

    /**
     * @param mixed $scoreConfig Structure containing configuration of the weights
     * @return bool If the configuration is valid or not
     */
    public function isScoreConfigValid($scoreConfig): bool
    {
        return $scoreConfig !== null && $this->getTestWeights($scoreConfig) !== null;
    }

    /**
     * Performs validation and normalization on config string.
     * This should be used instead of validation when the score config is processed as API input.
     * @param mixed $scoreConfig Structure containing configuration of the weights
     * @return mixed Normalized and polished score configuration
     * @throws ExerciseConfigException
     */
    public function validateAndNormalizeScore($scoreConfig)
    {
        if ($scoreConfig === null) {
            throw new ExerciseConfigException("Exercise score configuration is not valid");
        }

        $weights = $this->getTestWeights($scoreConfig);
        if ($weights === null) {
            throw new ExerciseConfigException("Exercise score configuration is not valid");
        }
        return ['testWeights' => $weights];
    }

    /**
     * Make default configuration for array of test names. Each test will
     * have the same priority as others.
     * @param array $tests of string names of tests
     * @return mixed Default configuration for given tests
     */
    public function getDefaultConfig(array $tests)
    {
        $weights = [];
        foreach ($tests as $test) {
            $weights[$test] = 100;
        }
        return ['testWeights' => $weights];
    }
}
