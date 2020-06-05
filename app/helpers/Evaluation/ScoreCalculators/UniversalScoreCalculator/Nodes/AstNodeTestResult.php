<?php

namespace App\Helpers\Evaluation;

/**
 * Class representing reference to a test result. Test results are the input of the whole expression.
 */
class AstNodeTestResult extends AstNodeLeaf
{
    public static $TYPE_NAME = 'test-result';

    private const KEY_TEST = 'test';
    private $testName = null;

    /**
     * Get the name of the test.
     * @return string
     * @throws AstNodeException if the name is not set
     */
    public function getTestName(): string
    {
        $this->internalValidation(); // throws if the name is not set
        return $this->testName;
    }

    /**
     * Set the name of the test.
     * @param string $testName
     */
    public function setTestName(string $testName)
    {
        $this->testName = $testName;
    }

    /*
     * AstNode interface implementation/overrides
     */

    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if ($config) {
            if (!array_key_exists(self::KEY_TEST, $config)) {
                throw new AstNodeException("The test result AST node does not hold the referenced test name.");
            }
            $this->setTestName((string)$config[self::KEY_TEST]);
        }
    }

    protected function internalValidation(array $testNames = [])
    {
        if ($this->testName === null) {
            throw new AstNodeException("The test result AST node does not have the referenced test name set.");
        }

        if ($testNames && !in_array($this->testName, $testNames)) { // skipped if no test names are given
            throw new AstNodeException("Test name '$this->testName' does not exist.");
        }
    }

    public function evaluate(array $testResults): float
    {
        $this->internalValidation(array_keys($testResults));
        return $testResults[$this->testName]->getScore();
    }

    public function serialize()
    {
        $res = parent::serialize();
        $res[self::KEY_TEST] = $this->getTestName();
        return $res;
    }
}
