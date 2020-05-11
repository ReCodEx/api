<?php

namespace App\Helpers\Evaluation;

use App\Exceptions\SubmissionEvaluationFailedException;
use App\Exceptions\ExerciseConfigException;

/**
 * Interface for score computations. Purpose is to merge scores of all
 * tests into one number, the final score (points for users).
 */
interface IScoreCalculator
{
    /**
     * @return string The calculator's unique identification
     */
    public function getId(): string;

    /**
     * Compute the score from tests results
     * @param string|null $scoreConfig Configuration of the calculator (format depends on implementation)
     * @param array $testResults Array of TestResult entities indexed by test ids
     * @return float Percentage of points, that should be assigned to the solution
     * @throws SubmissionEvaluationFailedException
     */
    public function computeScore(?string $scoreConfig, array $testResults): float;

    /**
     * Validate score configuration
     * @param string|null $scoreConfig Serialized score configuration loaded from the database
     * @return bool If the config is valid or not
     */
    public function isScoreConfigValid(?string $scoreConfig): bool;

    /**
     * Performs validation and normalization on config string.
     * This should be used instead of validation when the score config is processed as API input.
     * @param string|null $scoreConfig Serialized configuration for the score calculator
     * @return string|null Normalized and polished serialized score configuration
     * @throws ExerciseConfigException
     */
    public function validateAndNormalizeScore(?string $scoreConfig): ?string;

    /**
     * Make default configuration for given list of tests.
     * @param array $tests List of string names of tests
     * @return string|null Default configuration for given tests
     */
    public function getDefaultConfig(array $tests): ?string;
}
