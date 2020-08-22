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
     * @param mixed $scoreConfig Configuration of the calculator (format depends on implementation)
     * @param array $testResults Array of TestResult entities indexed by test ids
     * @return float Percentage of points, that should be assigned to the solution
     * @throws SubmissionEvaluationFailedException
     */
    public function computeScore($scoreConfig, array $testResults): float;

    /**
     * Validate score configuration
     * @param mixed $scoreConfig Serialized score configuration loaded from the database
     * @param array $testNames List of known test names (if empty, no check on names is performed)
     * @return bool If the config is valid or not
     */
    public function isScoreConfigValid($scoreConfig, array $testNames = []): bool;

    /**
     * Performs validation and normalization on config string.
     * This should be used instead of validation when the score config is processed as API input.
     * @param mixed $scoreConfig Configuration for the score calculator
     * @param array $testNames List of known test names (if empty, no check on names is performed)
     * @return mixed Normalized and polished score configuration
     * @throws ExerciseConfigException
     */
    public function validateAndNormalizeScore($scoreConfig, array $testNames = []);

    /**
     * Make default configuration for given list of tests.
     * @param array $testNames List of string names of tests
     * @return mixed Default configuration for given tests
     */
    public function getDefaultConfig(array $testNames);
}
