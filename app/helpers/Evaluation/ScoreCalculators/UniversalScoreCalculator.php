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
        try {
            $rootNode = AstNode::createFromConfig($scoreConfig);
            return $rootNode->evaluate($testResults);
        } catch (AstNodeException $e) {
            throw new SubmissionEvaluationFailedException("Score configuration is not valid: " . $e->getMessage());
        }
    }

    /**
     * @param mixed $scoreConfig AST structure containing the expression that computes score
     * @return bool If the configuration is valid or not
     */
    public function isScoreConfigValid($scoreConfig, array $testNames = []): bool
    {
        try {
            $rootNode = AstNode::createFromConfig($scoreConfig);
            $rootNode->validate($testNames);
            return true;
        } catch (AstNodeException $e) {
            return false;
        }
    }

    /**
     * Performs validation and normalization on config string.
     * This should be used instead of validation when the score config is processed as API input.
     * @param mixed $scoreConfig AST structure containing the expression that computes score
     * @return mixed Normalized and polished score configuration
     * @throws ExerciseConfigException
     */
    public function validateAndNormalizeScore($scoreConfig, array $testNames = [])
    {
        try {
            $rootNode = AstNode::createFromConfig($scoreConfig);
            $rootNode->validate($testNames);
            return $rootNode->serialize();
        } catch (AstNodeException $e) {
            throw new ExerciseConfigException("Score configuration is not valid: " . $e->getMessage());
        }
    }

    /**
     * Make default configuration for array of test names.
     * @param array $testNames
     * @return mixed Default configuration for given tests
     */
    public function getDefaultConfig(array $testNames)
    {
        if (!$testNames) {
            $rootNode = new AstNodeValue();
            $rootNode->setValue(1.0);
        } else {
            // bulild an expression that does the same as uniform calculator
            $avgNode = new AstNodeAverage();
            foreach ($testNames as $name) {
                $testNode = new AstNodeTestResult();
                $testNode->setTestName($name);
                $avgNode->addChild($testNode);
            }

            $rootNode = new AstNodeClamp();
            $rootNode->addChild($avgNode);
        }
        return $rootNode->serialize();
    }
}
