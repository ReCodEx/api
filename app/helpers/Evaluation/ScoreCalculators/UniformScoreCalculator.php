<?php

namespace App\Helpers\Evaluation;

use App\Exceptions\SubmissionEvaluationFailedException;
use App\Exceptions\ExerciseConfigException;
use App\Helpers\Evaluation\IScoreCalculator;
use App\Helpers\Yaml;
use App\Helpers\YamlException;

/**
 * Simple uniform score calculator. It does not have a config, computes arithmetic average of all tests.
 */
class UniformScoreCalculator implements IScoreCalculator
{
    public const ID = 'uniform';

    public function getId(): string
    {
        return self::ID;
    }

    /**
     * Function that computes the resulting score from simple YML config and test results score
     * @param string|null $scoreConfig
     * @param array $testResults array of TestResult entities indexed by test ids
     * @return float Percentage of total points assigned to the solution
     * @throws SubmissionEvaluationFailedException
     */
    public function computeScore(?string $scoreConfig, array $testResults): float
    {
        $sum = 0.0;
        foreach ($testResults as $testResult) {
            $sum += $testResult->getScore();
        }

        return count($testResults) === 0 ? 0.0 : $sum / (float)count($testResults);
    }

    public function isScoreConfigValid(?string $scoreConfig): bool
    {
        return $scoreConfig === null;
    }

    public function validateAndNormalizeScore(?string $scoreConfig): ?string
    {
        if ($scoreConfig !== null) {
            throw new ExerciseConfigException("Uniform score calculator does not require any configuration.");
        }
        return null;
    }

    public function getDefaultConfig(array $tests): ?string
    {
        return null;
    }
}
