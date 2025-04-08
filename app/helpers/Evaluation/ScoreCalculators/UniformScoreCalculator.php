<?php

namespace App\Helpers\Evaluation;

use App\Exceptions\ExerciseConfigException;
use App\Helpers\Evaluation\IScoreCalculator;

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

    public function computeScore($scoreConfig, array $testResults): float
    {
        $sum = 0.0;
        foreach ($testResults as $testResult) {
            $sum += $testResult->getScore();
        }

        return count($testResults) === 0 ? 0.0 : $sum / (float)count($testResults);
    }

    public function isScoreConfigValid($scoreConfig, array $testNames = []): bool
    {
        return $scoreConfig === null;
    }

    public function validateAndNormalizeScore($scoreConfig, array $testNames = [])
    {
        if ($scoreConfig !== null) {
            throw new ExerciseConfigException("Uniform score calculator does not require any configuration.");
        }
        return null;
    }

    public function getDefaultConfig(array $testNames)
    {
        return null;
    }
}
