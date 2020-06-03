<?php

namespace App\Helpers\Evaluation;

use App\Exceptions\SubmissionEvaluationFailedException;
use App\Exceptions\ExerciseConfigException;
use App\Helpers\Evaluation\IScoreCalculator;
use App\Helpers\Yaml;
use App\Helpers\YamlException;

/**
 * Universal score calculator.
 * Its configuration is AST representation of an expression that computes
 * the score (internal nodes are functions, leafs are literals and test results).
 */
class UniversalScoreCalculator implements IScoreCalculator
{
    public const ID = 'universal';

    public function getId(): string
    {
        return self::ID;
    }

    public function computeScore($scoreConfig, array $testResults): float
    {
        // TODO

        return 0.0;
    }

    /**
     * @param mixed $scoreConfig AST structure containing the expression that computes score
     * @return bool If the configuration is valid or not
     */
    public function isScoreConfigValid($scoreConfig): bool
    {
        // TODO

        return false;
    }

    /**
     * Performs validation and normalization on config string.
     * This should be used instead of validation when the score config is processed as API input.
     * @param mixed $scoreConfig AST structure containing the expression that computes score
     * @return mixed Normalized and polished score configuration
     * @throws ExerciseConfigException
     */
    public function validateAndNormalizeScore($scoreConfig)
    {
        // TODO
        
        return null;
    }

    /**
     * Make default configuration for array of test names.
     * @param array $tests of string names of tests
     * @return mixed Default configuration for given tests
     */
    public function getDefaultConfig(array $tests)
    {
        // TODO

        return null;
    }
}
